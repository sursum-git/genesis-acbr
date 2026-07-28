<?php

namespace App\Controller;

use App\Http\Exception\AcbrLegacyApiException;
use App\Repository\ApiAssinanteRepository;
use App\Repository\NfeOutputMonitorRepository;
use App\Repository\NfeOutputFiscalEventRepository;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class NfeOutputMonitorController extends AbstractController
{
    public function __construct(
        private readonly NfeOutputMonitorRepository $monitorRepository,
        private readonly NfeOutputFiscalEventRepository $fiscalEventRepository,
        private readonly AcbrLegacyScriptExecutor $legacyScriptExecutor,
        private readonly ApiAssinanteRepository $assinanteRepository,
    ) {
    }

    #[Route('/monitor-envios-nfe', name: 'app_nfe_output_monitor_legacy', methods: ['GET'])]
    public function legacyIndex(): Response
    {
        return $this->redirectToRoute('app_nfe_output_monitor', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/monitor-saida-nfe', name: 'app_nfe_output_monitor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/nfe_output_monitor.html.twig', [
            'dataUrl' => $this->generateUrl('app_nfe_output_monitor_data'),
            'filterOptionsUrl' => $this->generateUrl('app_nfe_output_monitor_filter_options'),
            'filterLookupUrl' => $this->generateUrl('app_nfe_output_monitor_filter_lookup'),
            'actionEventUrl' => $this->generateUrl('app_nfe_output_monitor_record_fiscal_event'),
            'correctionUrl' => $this->generateUrl('app_nfe_output_monitor_correction_letter'),
            'detailUrlTemplate' => $this->generateUrl('app_nfe_output_monitor_detail', ['requestId' => '__REQUEST_ID__']),
            'technicalDetailUrlTemplate' => $this->generateUrl('app_nfe_output_monitor_technical_detail', ['requestId' => '__REQUEST_ID__']),
        ]);
    }

    #[Route('/monitor-saida-nfe/eventos/registrar', name: 'app_nfe_output_monitor_record_fiscal_event', methods: ['POST'])]
    public function recordFiscalEvent(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload inválido para registrar evento fiscal.'], Response::HTTP_BAD_REQUEST);
        }

        $noteRequestId = trim((string) ($payload['nota_request_id'] ?? ''));
        $detail = $this->monitorRepository->findByRequestId($noteRequestId);
        if ($detail === null || (int) ($detail['note_id'] ?? 0) <= 0) {
            return $this->json(['message' => 'Nota não encontrada para registrar o evento fiscal.'], Response::HTTP_NOT_FOUND);
        }

        $event = $this->fiscalEventRepository->recordActionResult(
            (int) $detail['note_id'],
            (string) ($payload['action'] ?? ''),
            (string) ($payload['request_id'] ?? ''),
            is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
            is_array($payload['response'] ?? null) ? $payload['response'] : []
        );

        return $this->json(['event' => $event]);
    }

    #[Route('/monitor-saida-nfe/carta-correcao', name: 'app_nfe_output_monitor_correction_letter', methods: ['POST'])]
    public function correctionLetter(Request $request): JsonResponse
    {
        if ($this->validApiToken($request) === false) {
            return $this->json(['message' => 'Token invalido.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload inválido para Carta de Correção.'], Response::HTTP_BAD_REQUEST);
        }

        $noteRequestId = trim((string) ($payload['nota_request_id'] ?? ''));
        $correction = trim((string) ($payload['correcao'] ?? ''));
        if (mb_strlen($correction) < 15) {
            return $this->json(['message' => 'A correção deve ter no mínimo 15 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($correction) > 1000) {
            return $this->json(['message' => 'A correção deve ter no máximo 1000 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $detail = $this->monitorRepository->findByRequestId($noteRequestId);
        if ($detail === null || (int) ($detail['note_id'] ?? 0) <= 0) {
            return $this->json(['message' => 'Nota não encontrada para Carta de Correção.'], Response::HTTP_NOT_FOUND);
        }

        $accessKey = preg_replace('/\D+/', '', (string) ($detail['chave_nfe'] ?? '')) ?? '';
        $issuerDocument = preg_replace('/\D+/', '', (string) ($detail['emitente_documento'] ?? '')) ?? '';
        $authorizedXml = trim((string) ($detail['xml_autorizado'] ?? ''));
        if ($accessKey === '' || $issuerDocument === '' || $authorizedXml === '') {
            return $this->json(['message' => 'A nota precisa ter chave, emitente e XML autorizado para enviar Carta de Correção.'], Response::HTTP_BAD_REQUEST);
        }

        $sequence = $this->nextCorrectionSequence($detail);
        if ($sequence > 20) {
            return $this->json(['message' => 'A NF-e já atingiu o limite de 20 Cartas de Correção.'], Response::HTTP_BAD_REQUEST);
        }

        $nfeXmlPath = $this->writeTemporaryXml('nfe-cce-nfe-', $authorizedXml);
        $eventXmlPath = $this->writeTemporaryXml('nfe-cce-evento-', $this->buildCorrectionEventXml($detail, $correction, $sequence));
        $actionPayload = [
            'AeArquivoXmlNFe' => $nfeXmlPath,
            'AeArquivoXmlEvento' => $eventXmlPath,
            'AidLote' => '1',
            'AeChave' => $accessKey,
            'AeCNPJCPF' => $issuerDocument,
            'xCorrecao' => $correction,
            'nSeqEvento' => (string) $sequence,
        ];

        try {
            $response = $this->legacyScriptExecutor->execute('NFe/MT/ACBrNFeServicosMT.php', 'EnviarEvento', [
                'AeArquivoXmlNFe' => $nfeXmlPath,
                'AeArquivoXmlEvento' => $eventXmlPath,
                'AidLote' => '1',
            ]);
            $event = $this->fiscalEventRepository->recordActionResult((int) $detail['note_id'], 'carta_correcao', '', $actionPayload, $response);

            return $this->json(['resultado' => $response, 'event' => $event]);
        } catch (AcbrLegacyApiException $exception) {
            $response = ['mensagem' => $exception->getMessage()];
            $event = $this->fiscalEventRepository->recordActionResult((int) $detail['note_id'], 'carta_correcao', '', $actionPayload, $response);

            return $this->json(['message' => $exception->getMessage(), 'event' => $event], Response::HTTP_BAD_GATEWAY);
        } finally {
            @unlink($nfeXmlPath);
            @unlink($eventXmlPath);
        }
    }

    #[Route('/monitor-saida-nfe/filtros/opcoes', name: 'app_nfe_output_monitor_filter_options', methods: ['GET'])]
    public function filterOptions(): JsonResponse
    {
        return $this->json($this->monitorRepository->filterOptions());
    }

    #[Route('/monitor-saida-nfe/filtros/busca', name: 'app_nfe_output_monitor_filter_lookup', methods: ['GET'])]
    public function filterLookup(Request $request): JsonResponse
    {
        return $this->json([
            'items' => $this->monitorRepository->searchFilterOptions(
                $request->query->getString('type'),
                $request->query->getString('q')
            ),
        ]);
    }

    #[Route('/monitor-saida-nfe/dados', name: 'app_nfe_output_monitor_data', methods: ['GET'])]
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

    #[Route('/monitor-saida-nfe/nota/{requestId}', name: 'app_nfe_output_monitor_detail', methods: ['GET'])]
    public function detail(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Tentativa de envio nao encontrada.');
        }

        return $this->render('admin/nfe_output_monitor_detail.html.twig', [
            'detail' => $detail,
            'backUrl' => $this->generateUrl('app_nfe_output_monitor'),
            'technicalUrl' => $this->generateUrl('app_nfe_output_monitor_technical_detail', ['requestId' => $requestId]),
        ]);
    }

    #[Route('/monitor-saida-nfe/nota/{requestId}/tecnico', name: 'app_nfe_output_monitor_technical_detail', methods: ['GET'])]
    public function technicalDetail(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Tentativa de envio nao encontrada.');
        }

        return $this->render('admin/nfe_output_monitor_technical_detail.html.twig', [
            'detail' => $detail,
            'backUrl' => $this->generateUrl('app_nfe_output_monitor'),
            'detailUrl' => $this->generateUrl('app_nfe_output_monitor_detail', ['requestId' => $requestId]),
        ]);
    }

    #[Route('/monitor-saida-nfe/danfe/{requestId}', name: 'app_nfe_output_monitor_danfe', methods: ['GET'])]
    public function danfe(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null || !$this->hasDanfe($detail)) {
            throw $this->createNotFoundException('DANFE nao disponivel para esta tentativa.');
        }

        $path = (string) $detail['caminho_danfe'];
        if ($path !== '' && is_file($path)) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));

            return $response;
        }

        $pdfBinary = base64_decode((string) ($detail['danfe_base64'] ?? ''), true);
        if ($pdfBinary === false || $pdfBinary === '') {
            throw $this->createNotFoundException('Arquivo DANFE nao encontrado no disco.');
        }

        $response = new Response($pdfBinary);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('danfe-%s.pdf', $detail['chave_nfe'] ?: $requestId)
        ));

        return $response;
    }

    #[Route('/monitor-saida-nfe/xml/{requestId}', name: 'app_nfe_output_monitor_xml', methods: ['GET'])]
    public function xml(string $requestId): Response
    {
        $detail = $this->monitorRepository->findByRequestId($requestId);
        if ($detail === null || ($detail['xml_autorizado'] ?? '') === '') {
            throw $this->createNotFoundException('XML autorizado nao disponivel para esta tentativa.');
        }

        $filename = sprintf('nfe-%s.xml', $detail['chave_nfe'] ?: $requestId);
        $response = new Response((string) $detail['xml_autorizado']);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function hasDanfe(array $detail): bool
    {
        return ($detail['caminho_danfe'] ?? '') !== '' || ($detail['danfe_base64'] ?? '') !== '';
    }

    private function validApiToken(Request $request): bool
    {
        $token = trim((string) $request->headers->get('X-Api-Token', ''));
        if ($token === '') {
            $authorization = trim((string) $request->headers->get('Authorization', ''));
            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
                $token = trim((string) ($matches[1] ?? ''));
            }
        }

        return $token !== '' && $this->assinanteRepository->findByToken($token) !== null;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function nextCorrectionSequence(array $detail): int
    {
        $events = is_array($detail['eventos_nfe'] ?? null) ? $detail['eventos_nfe'] : [];
        $correctionCount = 0;
        foreach ($events as $event) {
            if (is_array($event) && (string) ($event['tipo_evento'] ?? '') === '110110') {
                $correctionCount++;
            }
        }

        return $correctionCount + 1;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function buildCorrectionEventXml(array $detail, string $correction, int $sequence): string
    {
        $accessKey = preg_replace('/\D+/', '', (string) ($detail['chave_nfe'] ?? '')) ?? '';
        $issuerDocument = preg_replace('/\D+/', '', (string) ($detail['emitente_documento'] ?? '')) ?? '';
        $environment = in_array((string) ($detail['ambiente'] ?? ''), ['1', '2'], true) ? (string) $detail['ambiente'] : '2';
        $stateCode = substr($accessKey, 0, 2) !== '' ? substr($accessKey, 0, 2) : '32';
        $eventDate = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:sP');
        $sequenceText = (string) $sequence;
        $eventId = 'ID110110' . $accessKey . str_pad($sequenceText, 2, '0', STR_PAD_LEFT);
        $condition = 'A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, de 15 de dezembro de 1970 e pode corrigir erros que nao estejam relacionados com as variaveis que determinam o valor do imposto, dados cadastrais que impliquem mudanca do remetente ou destinatario e data de emissao ou de saida.';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">'
            . '<idLote>1</idLote>'
            . '<evento versao="1.00"><infEvento Id="' . $this->xmlEscape($eventId) . '">'
            . '<cOrgao>' . $this->xmlEscape($stateCode) . '</cOrgao>'
            . '<tpAmb>' . $this->xmlEscape($environment) . '</tpAmb>'
            . '<CNPJ>' . $this->xmlEscape($issuerDocument) . '</CNPJ>'
            . '<chNFe>' . $this->xmlEscape($accessKey) . '</chNFe>'
            . '<dhEvento>' . $this->xmlEscape($eventDate) . '</dhEvento>'
            . '<tpEvento>110110</tpEvento>'
            . '<nSeqEvento>' . $this->xmlEscape($sequenceText) . '</nSeqEvento>'
            . '<verEvento>1.00</verEvento>'
            . '<detEvento versao="1.00">'
            . '<descEvento>Carta de Correcao</descEvento>'
            . '<xCorrecao>' . $this->xmlEscape($correction) . '</xCorrecao>'
            . '<xCondUso>' . $this->xmlEscape($condition) . '</xCondUso>'
            . '</detEvento>'
            . '</infEvento></evento>'
            . '</envEvento>';
    }

    private function writeTemporaryXml(string $prefix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new AcbrLegacyApiException('Nao foi possivel criar arquivo temporario para Carta de Correção.');
        }

        return $path;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
