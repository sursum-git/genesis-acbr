<?php

namespace App\Controller;

use App\Repository\NfeMonitorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NfeMonitorController extends AbstractController
{
    public function __construct(private readonly NfeMonitorRepository $monitorRepository)
    {
    }

    #[Route('/monitor-envios-nfe', name: 'app_nfe_monitor', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/nfe_monitor.html.twig', [
            'dataUrl' => $this->generateUrl('app_nfe_monitor_data'),
            'detailUrlTemplate' => $this->generateUrl('app_nfe_monitor_detail', ['requestId' => '__REQUEST_ID__']),
            'outputUrlTemplate' => $this->generateUrl('app_nfe_monitor_output', ['requestId' => '__REQUEST_ID__']),
        ]);
    }

    #[Route('/monitor-envios-nfe/dados', name: 'app_nfe_monitor_data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        return $this->json([
            'data' => $this->monitorRepository->listLatest(100),
        ]);
    }

    #[Route('/monitor-envios-nfe/detalhe/{requestId}', name: 'app_nfe_monitor_detail', methods: ['GET'])]
    public function detail(string $requestId): JsonResponse
    {
        $detail = $this->monitorRepository->findDetailByRequestId($requestId);
        if ($detail === null) {
            return $this->json(['mensagem' => 'Registro nao encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($detail);
    }

    #[Route('/monitor-envios-nfe/saida/{requestId}', name: 'app_nfe_monitor_output', methods: ['GET'])]
    public function output(string $requestId): Response
    {
        $detail = $this->monitorRepository->findDetailByRequestId($requestId);
        if ($detail === null) {
            throw $this->createNotFoundException('Registro do monitor nao encontrado.');
        }

        return $this->render('admin/nfe_monitor_output.html.twig', [
            'detail' => $detail,
        ]);
    }
}
