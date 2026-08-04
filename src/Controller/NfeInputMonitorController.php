<?php

namespace App\Controller;

use App\Http\Exception\AcbrLegacyApiException;
use App\Repository\NfeInputFiscalEventRepository;
use App\Repository\NfeInputMonitorRepository;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class NfeInputMonitorController extends AbstractController
{
    public function __construct(
        private readonly NfeInputMonitorRepository $monitorRepository,
        private readonly NfeInputFiscalEventRepository $fiscalEventRepository,
        private readonly AcbrLegacyScriptExecutor $legacyScriptExecutor,
    ) {
    }

    #[Route('/monitor-entrada-nfe', name: 'app_nfe_input_monitor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/nfe_input_monitor.html.twig', [
            'dataUrl' => $this->generateUrl('app_nfe_input_monitor_data'),
            'filterOptionsUrl' => $this->generateUrl('app_nfe_input_monitor_filter_options'),
            'filterLookupUrl' => $this->generateUrl('app_nfe_input_monitor_filter_lookup'),
            'manifestationUrl' => $this->generateUrl('app_nfe_input_monitor_recipient_manifestation'),
            'detailUrlTemplate' => $this->generateUrl('app_nfe_input_monitor_detail', ['requestId' => '__REQUEST_ID__']),
            'technicalDetailUrlTemplate' => $this->generateUrl('app_nfe_input_monitor_technical_detail', ['requestId' => '__REQUEST_ID__']),
        ]);
    }

    #[Route('/monitor-entrada-nfe/manifestacao-destinatario', name: 'app_nfe_input_monitor_recipient_manifestation', methods: ['POST'])]
    public function recipientManifestation(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload inválido para Manifestação do Destinatário.'], Response::HTTP_BAD_REQUEST);
        }

        $requestId = trim((string) ($payload['nota_request_id'] ?? ''));
        $eventType = trim((string) ($payload['tipo_manifestacao'] ?? ''));
        $justification = trim((string) ($payload['justificativa'] ?? ''));
        if (!in_array($eventType, ['210200', '210210', '210220', '210240'], true)) {
            return $this->json(['message' => 'Tipo de manifestação inválido.'], Response::HTTP_BAD_REQUEST);
        }

        if ($eventType === '210240' && mb_strlen($justification) < 15) {
            return $this->json(['message' => 'Operação não realizada exige justificativa com no mínimo 15 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($justification) > 255) {
            return $this->json(['message' => 'A justificativa deve ter no máximo 255 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null) {
            return $this->json(['message' => 'Documento de entrada não encontrado para manifestação.'], Response::HTTP_NOT_FOUND);
        }

        $documentId = (int) ($detail['request_id'] ?? 0);
        $accessKey = preg_replace('/\D+/', '', (string) ($detail['chave_nfe'] ?? '')) ?? '';
        $recipientDocument = preg_replace('/\D+/', '', (string) ($detail['cliente_documento'] ?? '')) ?? '';
        $authorizedXml = trim((string) ($detail['xml_autorizado'] ?? ''));
        if ($documentId <= 0 || $accessKey === '' || $recipientDocument === '' || $authorizedXml === '') {
            return $this->json(['message' => 'A nota precisa ter chave, destinatário e XML completo para enviar manifestação.'], Response::HTTP_BAD_REQUEST);
        }

        $sequence = $this->nextManifestationSequence($detail, $eventType);
        $nfeXmlPath = $this->writeTemporaryXml('nfe-manifest-nfe-', $authorizedXml);
        $eventXmlPath = $this->writeTemporaryXml('nfe-manifest-evento-', $this->buildManifestationEventXml($detail, $eventType, $justification, $sequence));
        $actionPayload = [
            'AeArquivoXmlNFe' => $nfeXmlPath,
            'AeArquivoXmlEvento' => $eventXmlPath,
            'AidLote' => '1',
            'chave' => $accessKey,
            'documento_destinatario' => $recipientDocument,
            'tpEvento' => $eventType,
            'justificativa' => $justification,
            'nSeqEvento' => (string) $sequence,
        ];

        try {
            $response = $this->legacyScriptExecutor->execute('NFe/MT/ACBrNFeServicosMT.php', 'EnviarEvento', [
                'AeArquivoXmlNFe' => $nfeXmlPath,
                'AeArquivoXmlEvento' => $eventXmlPath,
                'AidLote' => '1',
            ]);
            $event = $this->fiscalEventRepository->recordManifestationResult($documentId, '', $actionPayload, $response);

            return $this->json(['resultado' => $response, 'event' => $event]);
        } catch (AcbrLegacyApiException $exception) {
            $response = ['mensagem' => $exception->getMessage()];
            $event = $this->fiscalEventRepository->recordManifestationResult($documentId, '', $actionPayload, $response);

            return $this->json(['message' => $exception->getMessage(), 'event' => $event], Response::HTTP_BAD_GATEWAY);
        } finally {
            @unlink($nfeXmlPath);
            @unlink($eventXmlPath);
        }
    }

    #[Route('/monitor-entrada-nfe/filtros/opcoes', name: 'app_nfe_input_monitor_filter_options', methods: ['GET'])]
    public function filterOptions(): JsonResponse
    {
        return $this->json($this->monitorRepository->filterOptions());
    }

    #[Route('/monitor-entrada-nfe/filtros/busca', name: 'app_nfe_input_monitor_filter_lookup', methods: ['GET'])]
    public function filterLookup(Request $request): JsonResponse
    {
        return $this->json([
            'items' => $this->monitorRepository->searchFilterOptions(
                $request->query->getString('type'),
                $request->query->getString('q')
            ),
        ]);
    }

    #[Route('/monitor-entrada-nfe/dados', name: 'app_nfe_input_monitor_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $filters = [
            'date_from' => $request->query->getString('date_from'),
            'date_to' => $request->query->getString('date_to'),
            'numero_nota' => $request->query->getString('numero_nota'),
            'cliente' => $request->query->getString('cliente'),
            'status' => $request->query->all('status'),
            'chave' => $request->query->getString('chave'),
            'assinante' => $request->query->getString('assinante'),
            'emissor' => $request->query->getString('emissor'),
            'ambiente' => $request->query->getString('ambiente'),
        ];

        return $this->json([
            'data' => $this->monitorRepository->search($filters),
        ]);
    }

    #[Route('/monitor-entrada-nfe/nota/{requestId}', name: 'app_nfe_input_monitor_detail', methods: ['GET'])]
    public function detail(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Documento de entrada nao encontrado.');
        }

        return $this->render('admin/nfe_input_monitor_detail.html.twig', [
            'detail' => $detail,
            'backUrl' => $this->generateUrl('app_nfe_input_monitor'),
            'technicalUrl' => $this->generateUrl('app_nfe_input_monitor_technical_detail', ['requestId' => $requestId]),
        ]);
    }

    #[Route('/monitor-entrada-nfe/nota/{requestId}/tecnico', name: 'app_nfe_input_monitor_technical_detail', methods: ['GET'])]
    public function technicalDetail(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Documento de entrada nao encontrado.');
        }

        return $this->render('admin/nfe_input_monitor_technical_detail.html.twig', [
            'detail' => $detail,
            'backUrl' => $this->generateUrl('app_nfe_input_monitor'),
            'detailUrl' => $this->generateUrl('app_nfe_input_monitor_detail', ['requestId' => $requestId]),
        ]);
    }

    #[Route('/monitor-entrada-nfe/xml/{requestId}', name: 'app_nfe_input_monitor_xml', methods: ['GET'])]
    public function xml(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null || ($detail['xml_autorizado'] ?? '') === '') {
            throw $this->createNotFoundException('XML completo nao disponivel para este documento.');
        }

        $filename = sprintf('nfe-entrada-%s.xml', $detail['chave_nfe'] ?: $requestId);
        $response = new Response((string) $detail['xml_autorizado']);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function nextManifestationSequence(array $detail, string $eventType): int
    {
        $events = is_array($detail['eventos_nfe'] ?? null) ? $detail['eventos_nfe'] : [];
        $count = 0;
        foreach ($events as $event) {
            if (is_array($event) && (string) ($event['tipo_evento'] ?? '') === $eventType) {
                $count++;
            }
        }

        return $count + 1;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function buildManifestationEventXml(array $detail, string $eventType, string $justification, int $sequence): string
    {
        $accessKey = preg_replace('/\D+/', '', (string) ($detail['chave_nfe'] ?? '')) ?? '';
        $recipientDocument = preg_replace('/\D+/', '', (string) ($detail['cliente_documento'] ?? '')) ?? '';
        $environment = in_array((string) ($detail['ambiente'] ?? ''), ['1', '2'], true) ? (string) $detail['ambiente'] : '1';
        $eventDate = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:sP');
        $sequenceText = (string) $sequence;
        $eventId = 'ID' . $eventType . $accessKey . str_pad($sequenceText, 2, '0', STR_PAD_LEFT);
        $description = match ($eventType) {
            '210200' => 'Confirmacao da Operacao',
            '210210' => 'Ciencia da Operacao',
            '210220' => 'Desconhecimento da Operacao',
            '210240' => 'Operacao nao Realizada',
            default => 'Ciencia da Operacao',
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">'
            . '<idLote>1</idLote>'
            . '<evento versao="1.00"><infEvento Id="' . $this->xmlEscape($eventId) . '">'
            . '<cOrgao>91</cOrgao>'
            . '<tpAmb>' . $this->xmlEscape($environment) . '</tpAmb>'
            . '<CNPJ>' . $this->xmlEscape($recipientDocument) . '</CNPJ>'
            . '<chNFe>' . $this->xmlEscape($accessKey) . '</chNFe>'
            . '<dhEvento>' . $this->xmlEscape($eventDate) . '</dhEvento>'
            . '<tpEvento>' . $this->xmlEscape($eventType) . '</tpEvento>'
            . '<nSeqEvento>' . $this->xmlEscape($sequenceText) . '</nSeqEvento>'
            . '<verEvento>1.00</verEvento>'
            . '<detEvento versao="1.00">'
            . '<descEvento>' . $this->xmlEscape($description) . '</descEvento>';

        if ($eventType === '210240') {
            $xml .= '<xJust>' . $this->xmlEscape($justification) . '</xJust>';
        }

        return $xml . '</detEvento></infEvento></evento></envEvento>';
    }

    private function writeTemporaryXml(string $prefix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para Manifestação do Destinatário.');
        }

        return $path;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
