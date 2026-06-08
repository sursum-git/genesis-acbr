<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;

final class LegacyOptionalRequestBodyOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $openApi = $this->applySecuritySchemes($openApi);
        $paths = new Paths();

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            $paths->addPath($path, $this->normalizePathItem($path, $pathItem, $openApi));
        }

        $this->addRequestStatusPath($paths);

        return $openApi->withPaths($paths);
    }

    private function addRequestStatusPath(Paths $paths): void
    {
        $operation = new Operation(
            operationId: 'get_api_request_status',
            tags: ['Retorno assincrono'],
            responses: [
                '200' => new Response(
                    'Status e retorno da requisicao registrada.',
                    new \ArrayObject([
                        'application/json' => new MediaType(
                            new \ArrayObject([
                                'type' => 'object',
                                'properties' => new \ArrayObject([
                                    'u_c_request_id' => new \ArrayObject(['type' => 'string', 'format' => 'uuid']),
                                    'c_metodo' => new \ArrayObject(['type' => 'string', 'example' => 'POST']),
                                    'c_caminho' => new \ArrayObject(['type' => 'string', 'example' => '/nfe/consultas/status-servico']),
                                    'c_nome_operacao' => new \ArrayObject(['type' => 'string', 'example' => 'post_nfe_status_servico']),
                                    'c_modo_execucao' => new \ArrayObject(['type' => 'string', 'enum' => ['sincrono', 'assincrono']]),
                                    'c_cod_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'c_nome_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'c_versao_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'dt_hr_ult_atu_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'c_revisao_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'c_fonte_versao' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'c_caminho_fisico_programa' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'si_status_processamento' => new \ArrayObject([
                                        'type' => 'integer',
                                        'description' => '0 recebida, 1 enfileirada, 2 processando, 3 concluida, 4 falha, 5 nao autorizada.',
                                    ]),
                                    'si_status_http' => new \ArrayObject(['type' => 'integer', 'nullable' => true]),
                                    't_corpo_resposta' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    't_erro' => new \ArrayObject(['type' => 'string', 'nullable' => true]),
                                    'dt_hr_recebimento' => new \ArrayObject(['type' => 'string', 'format' => 'date-time']),
                                    'dt_hr_ini_processamento' => new \ArrayObject(['type' => 'string', 'format' => 'date-time', 'nullable' => true]),
                                    'dt_hr_fim_processamento' => new \ArrayObject(['type' => 'string', 'format' => 'date-time', 'nullable' => true]),
                                    'i_tempo_processamento_ms' => new \ArrayObject(['type' => 'integer', 'nullable' => true]),
                                ]),
                            ]),
                            [
                                'u_c_request_id' => '9f0d3656-33bb-4ef5-9a36-9bb174d4fd93',
                                'c_metodo' => 'POST',
                                'c_caminho' => '/nfe/consultas/status-servico',
                                'c_nome_operacao' => 'post_nfe_status_servico',
                                'c_modo_execucao' => 'assincrono',
                                'si_status_processamento' => 3,
                                'si_status_http' => 200,
                                't_corpo_resposta' => '{"resultado":"ok"}',
                                't_erro' => null,
                                'dt_hr_recebimento' => '2026-05-28T10:00:00+00:00',
                                'dt_hr_ini_processamento' => '2026-05-28T10:00:01+00:00',
                                'dt_hr_fim_processamento' => '2026-05-28T10:00:02+00:00',
                                'i_tempo_processamento_ms' => 1000,
                            ]
                        ),
                    ])
                ),
                '401' => new Response('Token nao identificado ou invalido.'),
                '404' => new Response('Requisicao nao encontrada para este token.'),
            ],
            summary: 'Consultar retorno por request_id',
            description: 'Use esta rota para consultar o status e o retorno de uma requisicao assincrona. O request_id e retornado na resposta inicial das APIs quando o processamento entra na fila de workers.',
            parameters: [
                new Parameter(
                    'requestId',
                    'path',
                    'Identificador retornado no campo request_id/u_c_request_id.',
                    true,
                    false,
                    null,
                    ['type' => 'string', 'format' => 'uuid'],
                    null,
                    null,
                    null,
                    '9f0d3656-33bb-4ef5-9a36-9bb174d4fd93'
                ),
            ],
            extensionProperties: [
                'x-apiplatform-tag' => ['cep', 'nfe', 'nfse'],
            ]
        );

        $paths->addPath('/requests/{requestId}', new PathItem(get: $this->applyTokenRequirements('/requests/{requestId}', $operation)));
    }

    private function applySecuritySchemes(OpenApi $openApi): OpenApi
    {
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();
        $securitySchemes['ApiTokenHeader'] = new SecurityScheme(
            'apiKey',
            'Informe o token no header X-Api-Token. Este e o metodo mais confiavel neste ambiente Apache.',
            'X-Api-Token',
            'header'
        );
        $securitySchemes['BearerToken'] = new SecurityScheme(
            'http',
            'Alternativa por Authorization: Bearer <token>. Alguns clientes HTTP ou proxies podem exigir esse formato.',
            null,
            null,
            'bearer',
            'Token'
        );

        $components = $components->withSecuritySchemes($securitySchemes);

        return $openApi
            ->withComponents($components)
            ->withSecurity([
                ['ApiTokenHeader' => []],
                ['BearerToken' => []],
            ]);
    }

    private function normalizePathItem(string $path, PathItem $pathItem, OpenApi $openApi): PathItem
    {
        if (!$this->supportsXmlPath($path) && !$this->isLegacyPath($path)) {
            return $pathItem;
        }

        return $pathItem
            ->withGet($this->normalizeOperation($path, $pathItem->getGet(), $openApi))
            ->withPost($this->normalizeOperation($path, $pathItem->getPost(), $openApi))
            ->withPut($this->normalizeOperation($path, $pathItem->getPut(), $openApi))
            ->withPatch($this->normalizeOperation($path, $pathItem->getPatch(), $openApi))
            ->withDelete($this->normalizeOperation($path, $pathItem->getDelete(), $openApi));
    }

    private function normalizeOperation(string $path, ?Operation $operation, OpenApi $openApi): ?Operation
    {
        if ($operation === null) {
            return $operation;
        }

        $operation = $this->applyTokenRequirements($path, $operation);

        if ($operation->getRequestBody() === null) {
            return $operation;
        }

        $requestBody = $operation->getRequestBody();

        $legacyJsonExample = $this->legacyJsonExampleForPath($path);
        if ($legacyJsonExample !== null) {
            $requestBody = $this->applyJsonExample($requestBody, $legacyJsonExample);
        }

        if ($this->supportsXmlPath($path)) {
            $requestBody = $this->addXmlMediaType($requestBody, $openApi);
        }

        if (in_array($path, [
            '/nfe/consultas/consultar-com-chave-xml',
            '/nfe/envio/enviar-sincrono-xml',
            '/nfe/envio/validar-regras-negocio',
            '/nfe/envio/imprimir-pdf',
        ], true)) {
            $requestBody = $this->applyRawXmlFixtureExample($requestBody, 'nfe_consulta_exemplo.xml', 'nfeProc');
        }

        if ($path === '/nfe/inutilizacao/imprimir-pdf') {
            $requestBody = $this->applyRawXmlFixtureExample($requestBody, 'nfe_inutilizacao_exemplo.xml', 'procInutNFe');
        }

        if ($path === '/nfe/envio/enviar-assincrono-xml') {
            $requestBody = $this->applyRawXmlFixtureExample($requestBody, 'nfe_envio_assincrono_exemplo.xml', 'nfeProc');
        }

        if ($this->isLegacyPath($path)) {
            $requestBody = $requestBody->withRequired(false);
        }

        return $operation->withRequestBody($requestBody);
    }

    private function applyTokenRequirements(string $path, Operation $operation): Operation
    {
        if (!$this->isManagedApiPath($path)) {
            return $operation;
        }

        $parameters = $operation->getParameters() ?? [];
        if (!$this->hasParameter($parameters, 'X-Api-Token', 'header')) {
            $parameters[] = new Parameter(
                'X-Api-Token',
                'header',
                'Token do assinante. Preferido neste ambiente. Exemplo: tok_xxx',
                false,
                false,
                null,
                ['type' => 'string'],
                null,
                null,
                null,
                'tok_exemplo_substituir'
            );
        }

        if (!$this->hasParameter($parameters, 'Authorization', 'header')) {
            $parameters[] = new Parameter(
                'Authorization',
                'header',
                'Alternativa via Bearer token. Exemplo: Bearer tok_xxx',
                false,
                false,
                null,
                ['type' => 'string'],
                null,
                null,
                null,
                'Bearer tok_exemplo_substituir'
            );
        }

        return $operation
            ->withParameters($parameters)
            ->withSecurity([
                ['ApiTokenHeader' => []],
                ['BearerToken' => []],
            ]);
    }

    private function legacyJsonExampleForPath(string $path): ?array
    {
        return match ($path) {
            '/nfse/padrao-nacional/enviar-evento' => [
                'payload' => [
                    'AInfEvento' => '/dados/nfse/evento.ini',
                ],
            ],
            '/nfse/padrao-nacional/consultar-dps-por-chave' => [
                'payload' => [
                    'AChaveDPS' => 'SUBSTITUIR_CHAVE_DPS',
                ],
            ],
            '/nfse/padrao-nacional/consultar-nfse-por-chave' => [
                'payload' => [
                    'AChaveNFSe' => 'SUBSTITUIR_CHAVE_NFSE',
                ],
            ],
            '/nfse/padrao-nacional/consultar-evento' => [
                'payload' => [
                    'AChave' => 'SUBSTITUIR_CHAVE_EVENTO',
                    'ATipoEvento' => '110111',
                    'ANumSeq' => '1',
                ],
            ],
            '/nfse/padrao-nacional/consultar-dfe' => [
                'payload' => [
                    'ANSU' => '000000000000001',
                ],
            ],
            '/nfse/padrao-nacional/obter-danfse' => [
                'payload' => [
                    'AChaveNFSe' => 'SUBSTITUIR_CHAVE_NFSE',
                ],
            ],
            '/nfse/padrao-nacional/consultar-parametros' => [
                'payload' => [
                    'ATipoParametroMunicipio' => '1',
                    'ACodigoServico' => '0107',
                    'ACompetencia' => '2026-05-01',
                    'ANumeroBeneficio' => '0',
                ],
            ],
            '/nfse/demais-provedores/cancelamento/cancelar' => [
                'payload' => [
                    'AInfCancelamentoNFSe' => '/dados/nfse/cancelamento.ini',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-situacao' => [
                'payload' => [
                    'AProtocolo' => 'SUBSTITUIR_PROTOCOLO',
                    'ANumLote' => '1',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-nfse-por-periodo' => [
                'payload' => [
                    'ADataInicial' => '2026-05-01',
                    'ADataFinal' => '2026-05-31',
                    'APagina' => '1',
                    'ANumeroLote' => '1',
                    'ATipoPeriodo' => '0',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-nfse-por-numero' => [
                'payload' => [
                    'ANumero' => '12345',
                    'APagina' => '1',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-nfse-por-rps' => [
                'payload' => [
                    'ANumeroRps' => '123',
                    'ASerie' => 'A1',
                    'ATipo' => '1',
                    'ACodigoVerificacao' => 'ABC123',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-nfse-generico' => [
                'payload' => [
                    'AInfConsultaNFSe' => '/dados/nfse/consulta_generica.ini',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-nfse-por-faixa' => [
                'payload' => [
                    'ANumeroInicial' => '100',
                    'ANumeroFinal' => '110',
                    'APagina' => '1',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-lote-rps' => [
                'payload' => [
                    'AProtocolo' => 'SUBSTITUIR_PROTOCOLO',
                    'ANumLote' => '1',
                ],
            ],
            '/nfse/demais-provedores/consultas/consultar-link-nfse' => [
                'payload' => [
                    'AInfConsultaLinkNFSe' => '/dados/nfse/consulta_link.ini',
                ],
            ],
            '/nfse/demais-provedores/envio/emitir-nota',
            '/nfse/demais-provedores/envio/enviar-lote-rps-assincrono',
            '/nfse/demais-provedores/envio/enviar-lote-rps-sincrono',
            '/nfse/demais-provedores/envio/enviar-um-rps' => [
                'payload' => [
                    'AeArquivoXmlOuIni' => '/dados/nfse/lote.ini',
                    'ALote' => '1',
                ],
            ],
            '/nfse/demais-provedores/envio/substituir-nfse' => [
                'payload' => [
                    'AeArquivoXmlOuIni' => '/dados/nfse/substituicao.ini',
                    'ANumeroNFSe' => '12345',
                    'ASerieNFSe' => 'A1',
                    'ACodigoCancelamento' => '1',
                    'AMotivoCancelamento' => 'Substituicao da NFSe',
                    'ANumeroLote' => '1',
                    'ACodigoVerificacao' => 'ABC123',
                ],
            ],
            '/nfse/demais-provedores/envio/enviar-email' => [
                'payload' => [
                    'AePara' => 'destinatario@exemplo.com',
                    'AeXmlNFSe' => '/dados/nfse/nfse.xml',
                    'AEnviaPDF' => 1,
                    'AeAssunto' => 'Envio de NFSe',
                    'AeCC' => '',
                    'AeAnexos' => '',
                    'AeMensagem' => 'Segue a NFSe em anexo.',
                ],
            ],
            '/nfse/demais-provedores/envio/link-nfse' => [
                'payload' => [
                    'ANumeroNFSe' => '12345',
                    'ACodigoVerificacao' => 'ABC123',
                    'AChaveAcesso' => 'SUBSTITUIR_CHAVE_ACESSO',
                    'AValorServico' => '1500.00',
                ],
            ],
            '/nfse/demais-provedores/envio/gerar-token' => [
                'payload' => [],
            ],
            '/nfse/demais-provedores/envio/salvar-pdf',
            '/nfse/demais-provedores/envio/imprimir-pdf' => [
                'payload' => [
                    'AeArquivoXml' => '/dados/nfse/nfse.xml',
                ],
            ],
            '/nfse/demais-provedores/servicos-prestados/por-numero',
            '/nfse/demais-provedores/servicos-tomados/por-numero' => [
                'payload' => [
                    'ANumero' => '12345',
                    'APagina' => '1',
                    'ADataInicial' => '2026-05-01',
                    'ADataFinal' => '2026-05-31',
                    'ATipoPeriodo' => '0',
                ],
            ],
            '/nfse/demais-provedores/servicos-prestados/por-tomador',
            '/nfse/demais-provedores/servicos-prestados/por-intermediario',
            '/nfse/demais-provedores/servicos-tomados/por-tomador',
            '/nfse/demais-provedores/servicos-tomados/por-intermediario' => [
                'payload' => [
                    'ACNPJ' => '06013812000158',
                    'AInscMun' => '123456',
                    'APagina' => '1',
                    'ADataInicial' => '2026-05-01',
                    'ADataFinal' => '2026-05-31',
                    'ATipoPeriodo' => '0',
                ],
            ],
            '/nfse/demais-provedores/servicos-prestados/por-periodo',
            '/nfse/demais-provedores/servicos-tomados/por-periodo' => [
                'payload' => [
                    'ADataInicial' => '2026-05-01',
                    'ADataFinal' => '2026-05-31',
                    'APagina' => '1',
                    'ATipoPeriodo' => '0',
                ],
            ],
            default => null,
        };
    }

    private function applyJsonExample(RequestBody $requestBody, array $example): RequestBody
    {
        $content = $requestBody->getContent();
        if ($content === null) {
            return $requestBody;
        }

        $updatedContent = new \ArrayObject(iterator_to_array($content));

        foreach (['application/ld+json', 'application/json'] as $mediaTypeName) {
            $mediaType = $updatedContent[$mediaTypeName] ?? null;
            if (!$mediaType instanceof MediaType) {
                continue;
            }

            $schema = $mediaType->getSchema();
            if ($schema instanceof \ArrayObject) {
                $schema['example'] = $example;
            }

            $updatedContent[$mediaTypeName] = new MediaType(
                $schema,
                $example,
                new \ArrayObject([
                    'default' => new \ArrayObject([
                        'summary' => 'Exemplo de payload',
                        'value' => $example,
                    ]),
                ])
            );
        }

        return $requestBody->withContent($updatedContent);
    }

    private function isLegacyPath(string $path): bool
    {
        return str_starts_with($path, '/nfe/') || str_starts_with($path, '/nfse/');
    }

    private function isManagedApiPath(string $path): bool
    {
        return $this->isLegacyPath($path)
            || str_starts_with($path, '/acbr-cep/')
            || str_starts_with($path, '/requests/');
    }

    private function supportsXmlPath(string $path): bool
    {
        return $this->isLegacyPath($path) || str_starts_with($path, '/acbr-cep/');
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function hasParameter(array $parameters, string $name, string $in): bool
    {
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $name && $parameter->getIn() === $in) {
                return true;
            }
        }

        return false;
    }

    private function addXmlMediaType(RequestBody $requestBody, OpenApi $openApi): RequestBody
    {
        $content = $requestBody->getContent();
        if ($content === null) {
            return $requestBody;
        }

        $jsonMediaType = $content['application/ld+json'] ?? $content['application/json'] ?? null;
        if ($jsonMediaType === null) {
            return $requestBody;
        }

        $xmlExample = $this->buildXmlExample($jsonMediaType, $openApi);
        $xmlSourceExample = $this->extractExample($jsonMediaType);
        if ($xmlSourceExample === null) {
            $xmlSourceExample = $this->buildExampleFromSchema($this->extractSchema($jsonMediaType), $openApi);
        }

        $xmlSchema = \is_array($xmlSourceExample)
            ? $this->buildXmlSchema($xmlSourceExample, 'request')
            : $this->extractSchema($jsonMediaType);

        if (\is_string($xmlExample) && $xmlExample !== '' && $xmlSchema instanceof \ArrayObject) {
            $xmlSchema['example'] = $xmlExample;
        }

        $updatedContent = new \ArrayObject(iterator_to_array($content));
        $updatedContent['application/xml'] = new MediaType($xmlSchema, $xmlExample);

        return $requestBody->withContent($updatedContent);
    }

    private function applyRawXmlFixtureExample(RequestBody $requestBody, string $fixtureName, string $rootName): RequestBody
    {
        $content = $requestBody->getContent();
        if ($content === null) {
            return $requestBody;
        }

        $fixturePath = dirname(__DIR__, 2).'/testes_api_platform/fixtures/'.$fixtureName;
        if (!is_file($fixturePath)) {
            return $requestBody;
        }

        $xmlExample = trim((string) file_get_contents($fixturePath));
        if ($xmlExample === '') {
            return $requestBody;
        }

        $updatedContent = new \ArrayObject(iterator_to_array($content));
        $updatedContent['application/xml'] = new MediaType(
            null,
            $xmlExample,
            new \ArrayObject([
                'default' => new \ArrayObject([
                    'summary' => 'XML completo da NF-e',
                    'value' => $xmlExample,
                ]),
            ])
        );

        return $requestBody->withContent($updatedContent);
    }

    private function buildXmlExample(mixed $mediaType, OpenApi $openApi): ?string
    {
        $example = $this->extractExample($mediaType);
        if ($example === null) {
            $example = $this->buildExampleFromSchema($this->extractSchema($mediaType), $openApi);
        }

        if (!\is_array($example) || $example === []) {
            return null;
        }

        return $this->arrayToXmlString($example);
    }

    private function buildExampleFromSchema(?\ArrayObject $schema, OpenApi $openApi): mixed
    {
        if ($schema === null) {
            return null;
        }

        $data = $schema->getArrayCopy();

        if (isset($data['example'])) {
            return $data['example'];
        }

        if (isset($data['$ref']) && \is_string($data['$ref'])) {
            $refName = $this->extractComponentName($data['$ref']);
            $schemas = $openApi->getComponents()->getSchemas();
            $referenced = $refName !== null && $schemas instanceof \ArrayObject ? ($schemas[$refName] ?? null) : null;

            if ($referenced instanceof \ArrayObject) {
                return $this->buildExampleFromSchema($referenced, $openApi);
            }
        }

        $type = $this->normalizeSchemaType($data['type'] ?? null);
        $properties = $data['properties'] ?? null;

        if ($type === 'object' && ($properties instanceof \ArrayObject || \is_array($properties))) {
            $example = [];

            foreach ($properties as $name => $propertySchema) {
                if ($propertySchema instanceof \ArrayObject || \is_array($propertySchema)) {
                    $example[$name] = $this->buildExampleFromSchema(
                        $propertySchema instanceof \ArrayObject ? $propertySchema : new \ArrayObject($propertySchema),
                        $openApi
                    );
                }
            }

            return $example;
        }

        $items = $data['items'] ?? null;
        if ($type === 'array' && ($items instanceof \ArrayObject || \is_array($items))) {
            return [$this->buildExampleFromSchema($items instanceof \ArrayObject ? $items : new \ArrayObject($items), $openApi)];
        }

        return match ($type) {
            'integer', 'number' => 0,
            'boolean' => false,
            default => 'string',
        };
    }

    private function extractComponentName(string $ref): ?string
    {
        $prefix = '#/components/schemas/';
        if (!str_starts_with($ref, $prefix)) {
            return null;
        }

        return substr($ref, \strlen($prefix));
    }

    private function arrayToXmlString(array $data, string $rootElement = 'request'): string
    {
        $xml = new \SimpleXMLElement(\sprintf('<%s/>', $rootElement));
        $this->appendXmlValues($xml, $data);

        return $xml->asXML() ?: '';
    }

    private function appendXmlValues(\SimpleXMLElement $element, mixed $value, ?string $name = null): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $childValue) {
                $childName = \is_string($key) ? $key : ($name ?? 'item');
                $child = $element->addChild($childName);
                $this->appendXmlValues($child, $childValue);
            }

            return;
        }

        if ($value === null) {
            $element[0] = '';

            return;
        }

        $element[0] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
    }

    private function extractSchema(mixed $mediaType): ?\ArrayObject
    {
        if ($mediaType instanceof MediaType) {
            return $mediaType->getSchema();
        }

        if ($mediaType instanceof \ArrayObject) {
            $schema = $mediaType['schema'] ?? null;

            return $schema instanceof \ArrayObject ? $schema : (\is_array($schema) ? new \ArrayObject($schema) : null);
        }

        if (\is_array($mediaType)) {
            $schema = $mediaType['schema'] ?? null;

            return $schema instanceof \ArrayObject ? $schema : (\is_array($schema) ? new \ArrayObject($schema) : null);
        }

        return null;
    }

    private function extractExample(mixed $mediaType): mixed
    {
        if ($mediaType instanceof MediaType) {
            return $mediaType->getExample();
        }

        if ($mediaType instanceof \ArrayObject) {
            return $mediaType['example'] ?? null;
        }

        if (\is_array($mediaType)) {
            return $mediaType['example'] ?? null;
        }

        return null;
    }

    private function normalizeSchemaType(mixed $type): ?string
    {
        if (\is_string($type)) {
            return $type;
        }

        if (\is_array($type)) {
            foreach ($type as $candidate) {
                if (\is_string($candidate) && $candidate !== 'null') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function buildXmlSchema(array $example, string $rootName): \ArrayObject
    {
        $schema = new \ArrayObject([
            'type' => 'object',
            'xml' => ['name' => $rootName],
            'properties' => new \ArrayObject(),
        ]);

        /** @var \ArrayObject $properties */
        $properties = $schema['properties'];

        foreach ($example as $name => $value) {
            $properties[$name] = $this->buildXmlPropertySchema($name, $value);
        }

        return $schema;
    }

    private function buildXmlPropertySchema(string $name, mixed $value): \ArrayObject
    {
        if (\is_array($value)) {
            if ($this->isList($value)) {
                $items = $value === [] ? 'string' : $value[0];

                return new \ArrayObject([
                    'type' => 'array',
                    'xml' => ['name' => $name, 'wrapped' => false],
                    'items' => $this->buildXmlPropertySchema('item', $items),
                ]);
            }

            return $this->buildXmlSchema($value, $name);
        }

        $type = match (true) {
            \is_int($value) => 'integer',
            \is_float($value) => 'number',
            \is_bool($value) => 'boolean',
            default => 'string',
        };

        return new \ArrayObject([
            'type' => $type,
            'xml' => ['name' => $name],
            'example' => $value,
        ]);
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, \count($value) - 1);
    }
}
