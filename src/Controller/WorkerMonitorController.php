<?php

namespace App\Controller;

use App\Repository\WorkerCapacityRepository;
use App\Repository\WorkerMonitorRepository;
use App\Service\Api\WorkerRuntimeInspector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class WorkerMonitorController extends AbstractController
{
    public function __construct(
        private readonly WorkerMonitorRepository $monitorRepository,
        private readonly WorkerCapacityRepository $capacityRepository,
        private readonly WorkerRuntimeInspector $runtimeInspector,
    ) {
    }

    #[Route('/monitor-workers', name: 'app_worker_monitor', methods: ['GET'])]
    public function index(): Response
    {
        $loadError = null;
        $queueMetrics = [];
        $queueHighlights = [];
        $webhookMetrics = [];
        $failedDeliveries = [];
        $currentCapacity = null;
        $workerExecutionSummary = [
            'api' => [],
            'webhook' => [],
        ];

        try {
            $queueMetrics = $this->monitorRepository->getQueueMetrics();
            $queueHighlights = $this->monitorRepository->findQueueHighlights();
            $webhookMetrics = $this->monitorRepository->getWebhookMetrics();
            $failedDeliveries = $this->monitorRepository->findFailedDeliveries();
            $workerExecutionSummary = $this->monitorRepository->getWorkerExecutionSummary();
            $currentCapacity = $this->capacityRepository->findCurrent();
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        $runtime = $this->runtimeInspector->inspect();
        $workerAvailability = $this->buildWorkerAvailability($currentCapacity, $runtime, $queueMetrics, $workerExecutionSummary);

        return $this->render('admin/worker_monitor.html.twig', [
            'loadError' => $loadError,
            'queueMetrics' => $queueMetrics,
            'queueHighlights' => $queueHighlights,
            'webhookMetrics' => $webhookMetrics,
            'failedDeliveries' => $failedDeliveries,
            'currentCapacity' => $currentCapacity,
            'runtime' => $runtime,
            'workerExecutionSummary' => $workerExecutionSummary,
            'workerAvailability' => $workerAvailability,
        ]);
    }

    /**
     * @param array<string, mixed>|null $currentCapacity
     * @param array<string, mixed> $runtime
     * @param array<string, mixed> $queueMetrics
     * @param array<string, array<string, mixed>> $workerExecutionSummary
     *
     * @return array<string, mixed>
     */
    private function buildWorkerAvailability(?array $currentCapacity, array $runtime, array $queueMetrics, array $workerExecutionSummary): array
    {
        $configured = max(0, (int) ($currentCapacity['qtd_workers'] ?? 0));
        $processingRequests = max(0, (int) ($queueMetrics['processing'] ?? 0));
        $apiRuntimeCount = max(0, (int) ($runtime['api_request_worker']['count'] ?? 0));
        $effectiveCapacity = min($configured, $apiRuntimeCount);
        $availableSlots = max(0, $effectiveCapacity - $processingRequests);
        $missingProcesses = max(0, $configured - $apiRuntimeCount);
        $apiSummary = $workerExecutionSummary['api'] ?? [];

        if ($configured <= 0) {
            $status = 'sem-capacidade';
            $statusLabel = 'Sem capacidade configurada';
            $statusClass = 'secondary';
        } elseif ($apiRuntimeCount <= 0) {
            $status = 'indisponivel';
            $statusLabel = 'Indisponível';
            $statusClass = 'danger';
        } elseif ($availableSlots <= 0) {
            $status = 'ocupado';
            $statusLabel = 'Todos ocupados';
            $statusClass = 'danger';
        } elseif ($missingProcesses > 0) {
            $status = 'parcial';
            $statusLabel = 'Parcial';
            $statusClass = 'info';
        } else {
            $status = 'disponivel';
            $statusLabel = 'Disponível';
            $statusClass = 'success';
        }

        return [
            'configured' => $configured,
            'effective_capacity' => $effectiveCapacity,
            'runtime_count' => $apiRuntimeCount,
            'processing' => $processingRequests,
            'available_slots' => $availableSlots,
            'missing_processes' => $missingProcesses,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'last_activity_at' => $apiSummary['last_activity_at'] ?? null,
            'last_finished_at' => $apiSummary['last_finished_at'] ?? null,
            'last_started_at' => $apiSummary['last_started_at'] ?? null,
            'last_worker_id' => $apiSummary['last_worker_id'] ?? null,
            'last_worker_pid' => $apiSummary['last_worker_pid'] ?? null,
            'last_http_status' => $apiSummary['last_http_status'] ?? null,
        ];
    }

    #[Route('/monitor-workers/reenfileirar-webhook/{id}', name: 'app_worker_monitor_requeue_webhook', methods: ['POST'])]
    public function requeueWebhook(int $id, Request $request): RedirectResponse
    {
        try {
            $this->monitorRepository->requeueFailedDelivery($id);
            $this->addFlash('success', sprintf('Entrega de webhook %d reenfileirada.', $id));
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao reenfileirar webhook: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_worker_monitor', $request->query->all());
    }
}
