<?php

namespace App\Controller;

use App\Service\Auth\CurrentUserContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NfsePanelController extends AbstractController
{
    private const EMIT_ENDPOINTS = [
        'emitir-nota' => '/index.php/nfse/demais-provedores/envio/emitir-nota',
        'enviar-um-rps' => '/index.php/nfse/demais-provedores/envio/enviar-um-rps',
        'enviar-lote-rps-sincrono' => '/index.php/nfse/demais-provedores/envio/enviar-lote-rps-sincrono',
        'enviar-lote-rps-assincrono' => '/index.php/nfse/demais-provedores/envio/enviar-lote-rps-assincrono',
    ];

    public function __construct(private readonly CurrentUserContext $currentUser)
    {
    }

    #[Route('/monitor-nfse', name: 'app_nfse_panel', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/nfse_panel.html.twig', [
            'emitRpsUrl' => '/index.php/monitor-nfse/acoes/emitir-rps',
            'cancelUrl' => '/index.php/monitor-nfse/acoes/cancelar',
            'substituteUrl' => '/index.php/monitor-nfse/acoes/substituir',
            'nationalEventUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/enviar-evento',
            'consultDpsUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/consultar-dps',
            'consultNfseUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/consultar-nfse',
            'docsUrl' => '/index.php/docs?filter_tags[]=nfse',
        ]);
    }

    #[Route('/monitor-nfse/acoes/emitir-rps', name: 'app_nfse_panel_emit_rps_action', methods: ['POST'])]
    public function emitRpsAction(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $content = trim((string) ($payload['conteudo'] ?? ''));
        $mode = trim((string) ($payload['modo'] ?? ''));
        if ($payload === null || $content === '' || !isset(self::EMIT_ENDPOINTS[$mode])) {
            return $this->json(['message' => 'Payload inválido para emissão de RPS.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->forwardRaw($request, 'POST', self::EMIT_ENDPOINTS[$mode], $content, [
            'ALote' => trim((string) ($payload['ALote'] ?? '1')) ?: '1',
        ]);
    }

    #[Route('/monitor-nfse/acoes/cancelar', name: 'app_nfse_panel_cancel_action', methods: ['POST'])]
    public function cancelAction(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $content = trim((string) ($payload['conteudo'] ?? ''));
        if ($payload === null || $content === '') {
            return $this->json(['message' => 'Payload inválido para cancelamento de NFSe.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->forwardRaw($request, 'POST', '/index.php/nfse/demais-provedores/cancelamento/cancelar', $content);
    }

    #[Route('/monitor-nfse/acoes/substituir', name: 'app_nfse_panel_substitute_action', methods: ['POST'])]
    public function substituteAction(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        if ($payload === null) {
            return $this->json(['message' => 'Payload inválido para substituicao de NFSe.'], Response::HTTP_BAD_REQUEST);
        }

        $requiredFields = [
            'conteudo',
            'ANumeroNFSe',
            'ASerieNFSe',
            'ACodigoCancelamento',
            'AMotivoCancelamento',
            'ANumeroLote',
            'ACodigoVerificacao',
        ];
        foreach ($requiredFields as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                return $this->json(['message' => 'Payload inválido para substituicao de NFSe.'], Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->forwardJson($request, 'POST', '/index.php/nfse/demais-provedores/envio/substituir-nfse', [
            'payload' => [
                'AeArquivoXmlOuIni' => (string) $payload['conteudo'],
                'ANumeroNFSe' => (string) $payload['ANumeroNFSe'],
                'ASerieNFSe' => (string) $payload['ASerieNFSe'],
                'ACodigoCancelamento' => (string) $payload['ACodigoCancelamento'],
                'AMotivoCancelamento' => (string) $payload['AMotivoCancelamento'],
                'ANumeroLote' => (string) $payload['ANumeroLote'],
                'ACodigoVerificacao' => (string) $payload['ACodigoVerificacao'],
            ],
        ]);
    }

    #[Route('/monitor-nfse/acoes/padrao-nacional/enviar-evento', name: 'app_nfse_panel_national_event_action', methods: ['POST'])]
    public function nationalEventAction(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $content = trim((string) ($payload['conteudo'] ?? ''));
        if ($payload === null || $content === '') {
            return $this->json(['message' => 'Payload inválido para evento do Padrão Nacional.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->forwardRaw($request, 'POST', '/index.php/nfse/padrao-nacional/enviar-evento', $content);
    }

    #[Route('/monitor-nfse/acoes/padrao-nacional/consultar-dps', name: 'app_nfse_panel_consult_dps_action', methods: ['GET'])]
    public function consultDpsAction(Request $request): JsonResponse
    {
        $key = trim($request->query->getString('AChaveDPS'));
        if ($key === '') {
            return $this->json(['message' => 'Informe a chave DPS.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->forwardJson($request, 'GET', '/index.php/nfse/padrao-nacional/consultar-dps-por-chave', [
            'AChaveDPS' => $key,
        ]);
    }

    #[Route('/monitor-nfse/acoes/padrao-nacional/consultar-nfse', name: 'app_nfse_panel_consult_nfse_action', methods: ['GET'])]
    public function consultNfseAction(Request $request): JsonResponse
    {
        $key = trim($request->query->getString('AChaveNFSe'));
        if ($key === '') {
            return $this->json(['message' => 'Informe a chave NFSe.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->forwardJson($request, 'GET', '/index.php/nfse/padrao-nacional/consultar-nfse-por-chave', [
            'AChaveNFSe' => $key,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function forwardJson(Request $request, string $method, string $path, array $payload): JsonResponse
    {
        return $this->forwardInternalApi($request, $method, $path, $payload, 'application/ld+json');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function forwardRaw(Request $request, string $method, string $path, string $body, array $query = []): JsonResponse
    {
        return $this->forwardInternalApi($request, $method, $path, $query, 'text/plain', $body);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function forwardInternalApi(Request $request, string $method, string $path, array $payload, string $contentType, ?string $body = null): JsonResponse
    {
        $token = $this->apiToken();
        if ($token === null) {
            return $this->json(['message' => 'Selecione um assinante ativo para executar a ação.'], Response::HTTP_FORBIDDEN);
        }

        $method = strtoupper($method);
        $url = $request->getSchemeAndHttpHost() . $path;
        if ($method === 'GET' || ($body !== null && $payload !== [])) {
            $url .= '?' . http_build_query($payload);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return $this->json(['message' => 'Falha ao iniciar chamada interna da API.'], Response::HTTP_BAD_GATEWAY);
        }

        $headers = [
            'Accept: application/json',
            'X-Api-Token: ' . $token,
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: ' . $contentType;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($curl, $options);
        $responseBody = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            return $this->json(['message' => $error !== '' ? $error : 'Falha ao chamar a API interna.'], Response::HTTP_BAD_GATEWAY);
        }

        $decoded = json_decode((string) $responseBody, true);
        $status = $httpCode >= 100 ? $httpCode : Response::HTTP_BAD_GATEWAY;

        return $this->json(is_array($decoded) ? $decoded : ['mensagem' => trim((string) $responseBody)], $status);
    }

    private function apiToken(): ?string
    {
        $subscriberId = $this->currentUser->activeSubscriberId();
        if ($subscriberId === null) {
            $subscriberIds = $this->currentUser->subscriberIds();
            $subscriberId = count($subscriberIds) === 1 ? $subscriberIds[0] : null;
        }

        return $this->currentUser->subscriberToken($subscriberId);
    }
}
