<?php

namespace App\Command;

use App\Repository\ApiAuditRepository;
use App\Repository\WebhookRepository;
use App\Service\Api\WebhookDeliveryClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:webhook-delivery-worker', description: 'Processa entregas pendentes de webhook.')]
final class WebhookDeliveryWorkerCommand extends Command
{
    public function __construct(
        private readonly WebhookRepository $webhookRepository,
        private readonly WebhookDeliveryClient $client,
        private readonly ApiAuditRepository $auditRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de entregas a processar.', '20')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Intervalo entre ciclos no modo continuo, em segundos.', '2')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Executa apenas um ciclo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $sleepSeconds = max(1, (int) $input->getOption('sleep'));
        $runOnce = (bool) $input->getOption('once');

        do {
            $processed = 0;

            while ($processed < $limit) {
                $delivery = $this->webhookRepository->claimNextPendingDelivery();
                if ($delivery === null) {
                    break;
                }

                $processed++;
                $result = $this->client->send($delivery);
                $deliveryId = (int) $delivery['id_t99006'];
                $requestInternalId = (int) $delivery['t99001_id'];
                $attemptNumber = (int) ($delivery['si_num_tentativa'] ?? 1);
                $statusCode = $result['status_code'];
                $responseHeaders = $result['response_headers'];
                $responseBody = $result['response_body'];
                $error = $result['error'];

                if ($error === null && $statusCode !== null && $this->isSuccessfulDelivery($delivery, $statusCode, $responseBody)) {
                    $this->webhookRepository->markDeliverySuccess($deliveryId, $statusCode, $responseHeaders, $responseBody);
                    $this->auditRepository->createEvent(
                        $requestInternalId,
                        'webhook.delivered',
                        sprintf('Webhook "%s" entregue com HTTP %d.', (string) ($delivery['c_webhook_nome'] ?? ''), $statusCode)
                    );

                    continue;
                }

                $this->webhookRepository->markDeliveryFailureWithPolicy(
                    $deliveryId,
                    $attemptNumber,
                    max(1, (int) ($delivery['si_max_tentativas'] ?? 3)),
                    max(1, (int) ($delivery['si_intervalo_tentativas_segundos'] ?? 300)),
                    $statusCode,
                    $responseHeaders,
                    $responseBody,
                    $error ?? $responseBody
                );

                $this->auditRepository->createEvent(
                    $requestInternalId,
                    'webhook.failed',
                    sprintf(
                        'Webhook "%s" falhou na tentativa %d. %s',
                        (string) ($delivery['c_webhook_nome'] ?? ''),
                        $attemptNumber,
                        trim((string) ($error ?? $responseBody ?? 'Sem detalhe'))
                    )
                );
            }

            if ($runOnce) {
                break;
            }

            sleep($sleepSeconds);
        } while (true);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function isSuccessfulDelivery(array $delivery, int $statusCode, ?string $responseBody): bool
    {
        if (!$this->matchesStatusCode((string) ($delivery['c_success_status_codes'] ?? '200,201,202,204'), $statusCode)) {
            return false;
        }

        $mode = strtolower(trim((string) ($delivery['c_success_mode'] ?? 'status_only')));
        if ($mode !== 'status_and_payload') {
            return true;
        }

        return $this->matchesPayloadRules((string) ($delivery['t_success_payload_rules_json'] ?? ''), $responseBody);
    }

    private function matchesStatusCode(string $rule, int $statusCode): bool
    {
        $parts = preg_split('/[\s,;]+/', strtolower($rule)) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^[1-5]xx$/', $part) && intdiv($statusCode, 100) === (int) $part[0]) {
                return true;
            }

            if ((string) $statusCode === $part) {
                return true;
            }
        }

        return false;
    }

    private function matchesPayloadRules(string $rulesJson, ?string $responseBody): bool
    {
        if (trim($rulesJson) === '') {
            return true;
        }

        $payload = json_decode((string) $responseBody, true);
        if (!is_array($payload)) {
            return false;
        }

        $rules = json_decode($rulesJson, true);
        if (!is_array($rules)) {
            return false;
        }

        $rules = array_is_list($rules) ? $rules : [$rules];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                return false;
            }

            $actual = $this->readPayloadPath($payload, (string) ($rule['path'] ?? $rule['tag'] ?? ''));
            if (!$this->matchesPayloadRule($actual, strtolower((string) ($rule['operator'] ?? 'equals')), $rule['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readPayloadPath(array $payload, string $path): mixed
    {
        $current = $payload;
        foreach (explode('.', trim($path)) as $segment) {
            if ($segment === '') {
                continue;
            }

            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        return $current;
    }

    private function matchesPayloadRule(mixed $actual, string $operator, mixed $expected): bool
    {
        $actualString = is_scalar($actual) || $actual === null
            ? (string) $actual
            : (json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return match ($operator) {
            'contains' => str_contains($actualString, (string) $expected),
            'in' => in_array($actualString, $this->normalizeList($expected), true),
            default => $actualString === (string) $expected,
        };
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): string => (string) $item, $value);
        }

        $parts = preg_split('/[\s,;]+/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $item): bool => $item !== ''));
    }
}
