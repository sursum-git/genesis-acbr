<?php

namespace App\Service\Api;

final class WorkerRuntimeInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        return [
            'api_request_worker' => $this->inspectCommand('app:api-request-worker'),
            'webhook_delivery_worker' => $this->inspectCommand('app:webhook-delivery-worker'),
        ];
    }

    /**
     * @return array{count:int,available:bool,processes:list<array<string, mixed>>,error:?string}
     */
    private function inspectCommand(string $commandName): array
    {
        if (!function_exists('shell_exec')) {
            return [
                'count' => 0,
                'available' => false,
                'processes' => [],
                'error' => 'shell_exec indisponível neste runtime.',
            ];
        }

        $output = @shell_exec("ps -eo pid,etimes,cmd 2>/dev/null");
        if (!is_string($output) || trim($output) === '') {
            return [
                'count' => 0,
                'available' => false,
                'processes' => [],
                'error' => 'Não foi possível ler a lista de processos do sistema.',
            ];
        }

        $processes = [];
        $lines = preg_split('/\r?\n/', trim($output)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, 'PID ELAPSED')) {
                continue;
            }

            if (!str_contains($line, $commandName)) {
                continue;
            }

            if (!preg_match('/^(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $elapsedSeconds = (int) $matches[2];
            $processes[] = [
                'pid' => (int) $matches[1],
                'elapsed_seconds' => $elapsedSeconds,
                'elapsed_human' => $this->formatDuration($elapsedSeconds),
                'command' => $matches[3],
            ];
        }

        return [
            'count' => count($processes),
            'available' => true,
            'processes' => $processes,
            'error' => null,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02dh %02dm %02ds', $hours, $minutes, $secs);
        }

        return sprintf('%02dm %02ds', $minutes, $secs);
    }
}
