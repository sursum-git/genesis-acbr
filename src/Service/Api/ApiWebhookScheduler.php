<?php

namespace App\Service\Api;

use App\Repository\ApiAuditRepository;
use App\Repository\WebhookRepository;
use App\Support\ApiRequestStatus;

final class ApiWebhookScheduler
{
    public function __construct(
        private readonly ApiAuditRepository $auditRepository,
        private readonly WebhookRepository $webhookRepository,
    ) {
    }

    public function scheduleForRequestId(string $requestId): void
    {
        $requestRow = $this->auditRepository->findRequestByPublicId($requestId);
        if ($requestRow === null) {
            return;
        }

        $event = $this->resolveEventName((int) ($requestRow['si_status_processamento'] ?? -1));
        if ($event === null) {
            return;
        }

        $bindings = $this->webhookRepository->findEligibleBindingsForRequest($requestRow, $event);
        if ($bindings === []) {
            return;
        }

        $payloadJson = json_encode($this->buildPayload($requestRow), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return;
        }

        foreach ($bindings as $binding) {
            $this->webhookRepository->createDelivery(
                (int) $binding['id_t00004'],
                (int) $requestRow['id_t99001'],
                $payloadJson
            );

            $this->auditRepository->createEvent(
                (int) $requestRow['id_t99001'],
                'webhook.scheduled',
                sprintf(
                    'Webhook "%s" agendado para %s (%s).',
                    (string) ($binding['c_webhook_nome'] ?? ''),
                    (string) ($binding['c_url'] ?? ''),
                    $event
                )
            );
        }
    }

    private function resolveEventName(int $status): ?string
    {
        return match ($status) {
            ApiRequestStatus::CONCLUIDA => 'request.completed',
            ApiRequestStatus::FALHA, ApiRequestStatus::NAO_AUTORIZADA => 'request.failed',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $requestRow
     * @return array<string, mixed>
     */
    private function buildPayload(array $requestRow): array
    {
        $subscriber = [];
        $subscriberJson = (string) ($requestRow['t_assinante_json'] ?? '');
        if ($subscriberJson !== '') {
            $decoded = json_decode($subscriberJson, true);
            if (is_array($decoded)) {
                $subscriber = $decoded;
            }
        }

        return [
            'request_id' => (string) ($requestRow['u_c_request_id'] ?? ''),
            'subscriber' => $subscriber,
            'program' => (string) ($requestRow['c_cod_programa'] ?? ''),
            'path' => (string) ($requestRow['c_caminho'] ?? ''),
            'operation' => (string) ($requestRow['c_nome_operacao'] ?? ''),
            'execution_mode' => (string) ($requestRow['c_modo_execucao'] ?? ''),
            'status_processamento' => (int) ($requestRow['si_status_processamento'] ?? 0),
            'status_http' => isset($requestRow['si_status_http']) ? (int) $requestRow['si_status_http'] : null,
            'received_at' => (string) ($requestRow['dt_hr_recebimento'] ?? ''),
            'finished_at' => (string) ($requestRow['dt_hr_fim_processamento'] ?? ''),
            'duration_ms' => isset($requestRow['i_tempo_processamento_ms']) ? (int) $requestRow['i_tempo_processamento_ms'] : null,
            'response_excerpt' => $this->truncate((string) ($requestRow['t_corpo_resposta'] ?? ''), 2000),
        ];
    }

    private function truncate(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit) . "\n...[truncado]";
    }
}
