<?php

namespace App\OpenApi;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\Response;

final class SwaggerUiHtmlProcessor implements ProcessorInterface
{
    private const XML_PATH = '/nfe/consultas/consultar-com-chave-xml';

    public function __construct(private readonly ProcessorInterface $decorated)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $response = $this->decorated->process($data, $operation, $uriVariables, $context);
        if (!$response instanceof Response) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || !str_contains($content, 'id="swagger-data"')) {
            return $response;
        }

        $updatedContent = preg_replace_callback(
            '/<script id="swagger-data" type="application\/json">(.*?)<\/script>/s',
            function (array $matches): string {
                $payload = json_decode(html_entity_decode($matches[1]), true);
                if (!is_array($payload)) {
                    return $matches[0];
                }

                $mediaType = $payload['spec']['paths'][self::XML_PATH]['post']['requestBody']['content']['application/xml'] ?? null;
                if (!is_array($mediaType)) {
                    return $matches[0];
                }

                $xmlExample = $this->resolveXmlExample($mediaType);
                if (!is_string($xmlExample) || $xmlExample === '') {
                    return $matches[0];
                }

                $payload['spec']['paths'][self::XML_PATH]['post']['requestBody']['content']['application/xml'] = [
                    'example' => $xmlExample,
                    'examples' => [
                        'default' => [
                            'summary' => 'XML completo da NF-e',
                            'value' => $xmlExample,
                        ],
                    ],
                ];

                return '<script id="swagger-data" type="application/json">'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';
            },
            $content,
            1
        );

        if (is_string($updatedContent)) {
            $updatedContent = $this->injectSwaggerUiXmlPatch($updatedContent);
            $response->setContent($updatedContent);
        }

        return $response;
    }

    private function resolveXmlExample(array $mediaType): ?string
    {
        $xmlExample = $mediaType['example'] ?? $mediaType['examples']['default']['value'] ?? null;
        if (is_string($xmlExample) && $xmlExample !== '') {
            return $xmlExample;
        }

        $fixturePath = dirname(__DIR__, 2).'/testes_api_platform/fixtures/nfe_consulta_exemplo.xml';
        if (!is_file($fixturePath)) {
            return null;
        }

        $fixture = trim((string) file_get_contents($fixturePath));

        return $fixture !== '' ? $fixture : null;
    }

    private function injectSwaggerUiXmlPatch(string $html): string
    {
        $fixturePath = dirname(__DIR__, 2).'/testes_api_platform/fixtures/nfe_consulta_exemplo.xml';
        if (!is_file($fixturePath)) {
            return $html;
        }

        $xmlExample = trim((string) file_get_contents($fixturePath));
        if ($xmlExample === '') {
            return $html;
        }

        $script = <<<HTML
<script>
(function () {
  const xmlExample = %s;
  const targetPath = %s;
  const placeholderSnippet = 'root element name is undefined';

  function decodeEntities(value) {
    if (typeof value !== 'string' || value.indexOf('&') === -1) {
      return value;
    }

    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;

    return textarea.value;
  }

  function isTargetBlock(block) {
    const pathNode = block.querySelector('.opblock-summary-path');
    const methodNode = block.querySelector('.opblock-summary-method');
    const summary = block.querySelector('.opblock-summary');
    const blockText = (block.textContent || '').replace(/\s+/g, ' ');
    const methodText = (methodNode ? methodNode.textContent : '').trim().toLowerCase();
    const pathText = decodeEntities((pathNode ? pathNode.textContent : '').trim());
    const summaryAria = decodeEntities(summary ? (summary.getAttribute('aria-label') || '') : '');

    if (methodText !== 'post') {
      return false;
    }

    return pathText.includes(targetPath)
      || summaryAria.includes(targetPath)
      || blockText.includes('consultar-com-chave-xml');
  }

  function applyToBlock(block) {
    if (!isTargetBlock(block)) {
      return;
    }

    const normalizedXmlExample = decodeEntities(xmlExample);

    const mediaTypeSelect = block.querySelector('select.body-param-content-type');
    if (mediaTypeSelect && mediaTypeSelect.value && !/xml/i.test(mediaTypeSelect.value)) {
      return;
    }

    const preview = block.querySelector('.body-param__example');
    if (preview) {
      const previewText = decodeEntities(preview.textContent || '');
      if (!previewText.trim() || previewText.includes(placeholderSnippet) || previewText.includes('&lt;')) {
        preview.textContent = normalizedXmlExample;
      }
    }

    const textarea = block.querySelector('textarea.body-param__text');
    if (!textarea) {
      return;
    }

    const currentValue = decodeEntities(textarea.value || '');
    if (!currentValue.trim() || currentValue.includes(placeholderSnippet) || currentValue.includes('&lt;')) {
      textarea.value = normalizedXmlExample;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function scan() {
    document.querySelectorAll('.opblock').forEach(applyToBlock);
  }

  const observer = new MutationObserver(scan);

  function start() {
    scan();
    observer.observe(document.body, { childList: true, subtree: true });
    document.body.addEventListener('change', function (event) {
      const target = event.target;
      if (target instanceof HTMLSelectElement && target.classList.contains('body-param-content-type')) {
        window.setTimeout(scan, 0);
      }
    });
    document.body.addEventListener('click', function () {
      window.setTimeout(scan, 0);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
</script>
HTML;

        $script = sprintf($script, json_encode($xmlExample, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), json_encode(self::XML_PATH, JSON_UNESCAPED_SLASHES));

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script.'</body>', $html);
        }

        return $html.$script;
    }
}
