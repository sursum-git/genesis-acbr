<?php

namespace App\Controller;

use App\Repository\NfeOutputMonitorRepository;
use App\Repository\NfeOutputFiscalEventRepository;
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
}
