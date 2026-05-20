<?php

namespace App\Controller;

use App\Repository\ApiAuditDashboardRepository;
use App\Support\ApiRequestStatus;
use App\Support\XlsxResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAuditDashboardController extends AbstractController
{
    public function __construct(private readonly ApiAuditDashboardRepository $repository)
    {
    }

    #[Route('/auditoria-requisicoes', name: 'app_api_audit_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'assinante' => trim((string) $request->query->get('assinante', '')),
            'metodo' => trim((string) $request->query->get('metodo', '')),
            'modo' => trim((string) $request->query->get('modo', '')),
            'programa' => trim((string) $request->query->get('programa', '')),
            'status_processamento' => trim((string) $request->query->get('status_processamento', '')),
            'status_http' => trim((string) $request->query->get('status_http', '')),
            'caminho' => trim((string) $request->query->get('caminho', '')),
            'data_ini' => trim((string) $request->query->get('data_ini', '')),
            'data_fim' => trim((string) $request->query->get('data_fim', '')),
        ];

        $limit = max(1, min((int) $request->query->get('limite', 80), 300));
        $sort = trim((string) $request->query->get('ordenar', 'dt_hr_recebimento'));
        $direction = trim((string) $request->query->get('direcao', 'desc'));
        $page = max(1, (int) $request->query->get('pagina', 1));
        $summary = $this->repository->getSummary($filters);
        $advancedMetrics = $this->normalizeAdvancedMetrics($this->repository->getAdvancedMetrics($filters));
        $comparison = $this->buildComparison($filters);
        $alerts = $this->buildAlerts($summary, $advancedMetrics, $comparison);
        $totalRequests = (int) $summary['total'];
        $totalPages = max(1, (int) ceil($totalRequests / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;
        if ((string) $request->query->get('csv', '0') === '1') {
            return $this->buildCsvResponse(
                'auditoria_requisicoes.csv',
                $this->repository->findRequests($filters, 10000, 0, $sort, $direction)
            );
        }
        if ((string) $request->query->get('xlsx', '0') === '1') {
            return $this->buildXlsxResponse(
                'auditoria_requisicoes.xlsx',
                $this->repository->findRequests($filters, 10000, 0, $sort, $direction)
            );
        }
        $requests = $this->repository->findRequests($filters, $limit, $offset, $sort, $direction);
        $selectedRequestId = trim((string) $request->query->get('requisicao', ''));
        $selectedRequest = $this->repository->findSelectedRequest($filters, $selectedRequestId !== '' ? $selectedRequestId : null);

        $attempts = [];
        $events = [];
        if ($selectedRequest !== null) {
            $attempts = $this->repository->findAttempts((int) $selectedRequest['id_t99001']);
            $events = $this->repository->findEvents((int) $selectedRequest['id_t99001']);
            $selectedRequest = $this->normalizeRequest($selectedRequest);
        }

        [$previousRequestId, $nextRequestId] = $this->resolveNeighborIds(
            $requests,
            $selectedRequest !== null ? (string) $selectedRequest['u_c_request_id'] : null
        );

        return $this->render('catalog/api_audit_dashboard.html.twig', [
            'filters' => $filters,
            'limit' => $limit,
            'summary' => $summary,
            'advancedMetrics' => $advancedMetrics,
            'comparison' => $comparison,
            'alerts' => $alerts,
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'attempts' => array_map([$this, 'normalizeRequest'], $attempts),
            'events' => $events,
            'assinantes' => $this->repository->findAssinanteOptions(),
            'methods' => $this->repository->findMethodOptions(),
            'modes' => $this->repository->findModeOptions(),
            'programs' => $this->repository->findProgramOptions(),
            'statusOptions' => $this->getStatusOptions(),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRequests' => $totalRequests,
            'previousRequestId' => $previousRequestId,
            'nextRequestId' => $nextRequestId,
            'sort' => $sort,
            'direction' => $direction,
            'presets' => $this->getPresets(),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function normalizeRequest(array $row): array
    {
        foreach (['t_headers_requisicao', 't_headers_resposta', 't_assinante_json'] as $field) {
            if (isset($row[$field]) && is_string($row[$field]) && $row[$field] !== '') {
                $decoded = json_decode($row[$field], true);
                if (is_array($decoded)) {
                    if (str_starts_with($field, 't_headers_')) {
                        $decoded = $this->maskHeaders($decoded);
                    }
                    $row[$field . '_pretty'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        foreach (['t_headers_requisicao', 't_headers_resposta'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = preg_replace('/(X-Api-Token:\s*)(.+)/i', '$1[mascarado]', $row[$field]) ?? $row[$field];
                $row[$field] = preg_replace('/(Authorization:\s*Bearer\s+)(.+)/i', '$1[mascarado]', $row[$field]) ?? $row[$field];
            }
        }

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function getStatusOptions(): array
    {
        return [
            ApiRequestStatus::RECEBIDA => 'Recebida',
            ApiRequestStatus::ENFILEIRADA => 'Enfileirada',
            ApiRequestStatus::PROCESSANDO => 'Processando',
            ApiRequestStatus::CONCLUIDA => 'Concluida',
            ApiRequestStatus::FALHA => 'Falha',
            ApiRequestStatus::NAO_AUTORIZADA => 'Nao autorizada',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPresets(): array
    {
        return [
            ['label' => 'Falhas recentes', 'query' => ['status_processamento' => (string) ApiRequestStatus::FALHA]],
            ['label' => 'Fila async', 'query' => ['modo' => 'async', 'status_processamento' => (string) ApiRequestStatus::ENFILEIRADA]],
            ['label' => 'Não autorizadas', 'query' => ['status_processamento' => (string) ApiRequestStatus::NAO_AUTORIZADA]],
            ['label' => 'NFe', 'query' => ['programa' => 'nfe']],
            ['label' => 'Infra auditoria', 'query' => ['programa' => 'src_api_auditoria']],
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function normalizeAdvancedMetrics(array $metrics): array
    {
        $metrics['requisicoes_por_dia'] = $this->applyBarPercent($metrics['requisicoes_por_dia'] ?? [], 'total');
        $metrics['erros_por_dia'] = $this->applyBarPercent($metrics['erros_por_dia'] ?? [], 'total');
        $metrics['tempo_medio_por_endpoint'] = $this->applyBarPercent($metrics['tempo_medio_por_endpoint'] ?? [], 'tempo_medio_ms');
        $metrics['top_endpoints'] = $this->applyBarPercent($metrics['top_endpoints'] ?? [], 'total');
        $metrics['top_assinantes'] = $this->applyBarPercent($metrics['top_assinantes'] ?? [], 'total');
        $metrics['status_http'] = $this->applyBarPercent($metrics['status_http'] ?? [], 'total');
        $metrics['requisicoes_por_dia_svg'] = $this->buildVerticalSvgChart($metrics['requisicoes_por_dia'] ?? [], 'dia', 'total');
        $metrics['erros_por_dia_svg'] = $this->buildVerticalSvgChart($metrics['erros_por_dia'] ?? [], 'dia', 'total');
        $metrics['tempo_medio_por_endpoint_svg'] = $this->buildHorizontalSvgChart($metrics['tempo_medio_por_endpoint'] ?? [], 'c_caminho', 'tempo_medio_ms', ' ms');

        return $metrics;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildComparison(array $filters): array
    {
        [$currentFilters, $previousFilters, $label, $previousLabel] = $this->deriveComparisonFilters($filters);
        $currentSummary = $this->repository->getSummary($currentFilters);
        $currentMetrics = $this->normalizeAdvancedMetrics($this->repository->getAdvancedMetrics($currentFilters));
        $previousSummary = $this->repository->getSummary($previousFilters);
        $previousMetrics = $this->normalizeAdvancedMetrics($this->repository->getAdvancedMetrics($previousFilters));

        return [
            'label_atual' => $label,
            'label_anterior' => $previousLabel,
            'atual' => [
                'total' => (int) ($currentSummary['total'] ?? 0),
                'falhas' => (int) ($currentSummary['falhas'] ?? 0),
                'tempo_medio_ms' => (int) ($currentMetrics['tempo_medio_ms'] ?? 0),
                'taxa_erro_perc' => (int) ($currentMetrics['taxa_erro_perc'] ?? 0),
            ],
            'anterior' => [
                'total' => (int) ($previousSummary['total'] ?? 0),
                'falhas' => (int) ($previousSummary['falhas'] ?? 0),
                'tempo_medio_ms' => (int) ($previousMetrics['tempo_medio_ms'] ?? 0),
                'taxa_erro_perc' => (int) ($previousMetrics['taxa_erro_perc'] ?? 0),
            ],
            'deltas' => [
                'total' => $this->buildDelta((int) ($currentSummary['total'] ?? 0), (int) ($previousSummary['total'] ?? 0), false),
                'falhas' => $this->buildDelta((int) ($currentSummary['falhas'] ?? 0), (int) ($previousSummary['falhas'] ?? 0), false),
                'tempo_medio_ms' => $this->buildDelta((int) ($currentMetrics['tempo_medio_ms'] ?? 0), (int) ($previousMetrics['tempo_medio_ms'] ?? 0), false),
                'taxa_erro_perc' => $this->buildDelta((int) ($currentMetrics['taxa_erro_perc'] ?? 0), (int) ($previousMetrics['taxa_erro_perc'] ?? 0), true),
            ],
        ];
    }

    /**
     * @param array<string, int> $summary
     * @param array<string, mixed> $advancedMetrics
     * @param array<string, mixed> $comparison
     * @return list<array<string, string>>
     */
    private function buildAlerts(array $summary, array $advancedMetrics, array $comparison): array
    {
        $alerts = [];
        $currentErrorRate = (int) ($advancedMetrics['taxa_erro_perc'] ?? 0);
        $errorRateDelta = (int) ($comparison['deltas']['taxa_erro_perc']['diff'] ?? 0);
        $currentLatency = (int) ($advancedMetrics['tempo_medio_ms'] ?? 0);
        $latencyDeltaPerc = (int) ($comparison['deltas']['tempo_medio_ms']['percent'] ?? 0);
        $queued = (int) ($summary['enfileiradas'] ?? 0);
        $processing = (int) ($summary['processando'] ?? 0);

        if ($currentErrorRate >= 20 || $errorRateDelta >= 10) {
            $alerts[] = [
                'nivel' => 'danger',
                'titulo' => 'Taxa de erro acima do normal',
                'mensagem' => sprintf('A taxa de erro atual está em %d%% e variou %d pontos contra o período anterior.', $currentErrorRate, $errorRateDelta),
            ];
        }

        if ($currentLatency >= 3000 || $latencyDeltaPerc >= 35) {
            $alerts[] = [
                'nivel' => 'warning',
                'titulo' => 'Latência média em alta',
                'mensagem' => sprintf('O tempo médio atual está em %d ms e variou %d%% contra o período anterior.', $currentLatency, $latencyDeltaPerc),
            ];
        }

        if ($queued > 0 || $processing > 0) {
            $alerts[] = [
                'nivel' => 'warning',
                'titulo' => 'Fila ativa',
                'mensagem' => sprintf('Existem %d requisições enfileiradas e %d em processamento nos filtros atuais.', $queued, $processing),
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'nivel' => 'success',
                'titulo' => 'Sem alertas críticos',
                'mensagem' => 'Não houve pico relevante de erro, latência ou fila no recorte atual.',
            ];
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:array<string, mixed>,1:array<string, mixed>,2:string,3:string}
     */
    private function deriveComparisonFilters(array $filters): array
    {
        $today = new \DateTimeImmutable('today');
        $start = null;
        $end = null;

        $dataIni = trim((string) ($filters['data_ini'] ?? ''));
        if ($dataIni !== '') {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d', $dataIni) ?: null;
        }

        $dataFim = trim((string) ($filters['data_fim'] ?? ''));
        if ($dataFim !== '') {
            $end = \DateTimeImmutable::createFromFormat('Y-m-d', $dataFim) ?: null;
        }

        if ($start === null && $end === null) {
            $end = $today;
            $start = $end->modify('-6 days');
        } elseif ($start !== null && $end === null) {
            $end = $today < $start ? $start : $today;
        } elseif ($start === null && $end !== null) {
            $start = $end->modify('-6 days');
        }

        if ($start === null || $end === null) {
            $start = $today->modify('-6 days');
            $end = $today;
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $days = max(1, (int) $start->diff($end)->days + 1);
        $previousEnd = $start->modify('-1 day');
        $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');

        $currentFilters = $filters;
        $currentFilters['data_ini'] = $start->format('Y-m-d');
        $currentFilters['data_fim'] = $end->format('Y-m-d');

        $previousFilters = $filters;
        $previousFilters['data_ini'] = $previousStart->format('Y-m-d');
        $previousFilters['data_fim'] = $previousEnd->format('Y-m-d');

        return [
            $currentFilters,
            $previousFilters,
            sprintf('%s a %s', $start->format('d/m/Y'), $end->format('d/m/Y')),
            sprintf('%s a %s', $previousStart->format('d/m/Y'), $previousEnd->format('d/m/Y')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDelta(int $current, int $previous, bool $points): array
    {
        $diff = $current - $previous;
        $percent = $previous === 0 ? ($current > 0 ? 100 : 0) : (int) round(($diff / $previous) * 100);

        return [
            'diff' => $diff,
            'percent' => $percent,
            'formatted' => sprintf('%s%d%s', $diff >= 0 ? '+' : '', $diff, $points ? ' pts' : ''),
            'trend' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildVerticalSvgChart(array $rows, string $labelField, string $valueField): array
    {
        $chartWidth = 320.0;
        $chartHeight = 180.0;
        $leftPadding = 18.0;
        $rightPadding = 18.0;
        $topPadding = 26.0;
        $bottomPadding = 34.0;
        $plotHeight = $chartHeight - $topPadding - $bottomPadding;

        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) ($row[$valueField] ?? 0));
        }

        $bars = [];
        $count = max(1, count($rows));
        $slot = ($chartWidth - $leftPadding - $rightPadding) / $count;
        $barWidth = max(12.0, $slot - 8.0);

        foreach ($rows as $index => $row) {
            $value = (int) ($row[$valueField] ?? 0);
            $height = $max > 0 ? ($value / $max) * $plotHeight : 0.0;
            $x = $leftPadding + ($index * $slot) + (($slot - $barWidth) / 2);
            $y = $chartHeight - $bottomPadding - $height;
            $bars[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'width' => round($barWidth, 2),
                'height' => round($height, 2),
                'label' => (string) ($row[$labelField] ?? ''),
                'value' => $value,
                'label_x' => round($x + ($barWidth / 2), 2),
                'label_y' => round($chartHeight - 12.0, 2),
                'value_y' => round(max(14.0, $y - 6.0), 2),
            ];
        }

        return [
            'bars' => $bars,
            'max' => $max,
            'width' => (int) $chartWidth,
            'height' => (int) $chartHeight,
            'axis_y' => (int) ($chartHeight - $bottomPadding),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildHorizontalSvgChart(array $rows, string $labelField, string $valueField, string $suffix = ''): array
    {
        $chartWidth = 420.0;
        $labelWidth = 132.0;
        $valueWidth = 74.0;
        $leftPadding = 8.0;
        $rightPadding = 12.0;
        $topPadding = 10.0;
        $rowHeight = 28.0;
        $barHeight = 14.0;
        $plotWidth = $chartWidth - $labelWidth - $valueWidth - $leftPadding - $rightPadding;

        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) ($row[$valueField] ?? 0));
        }

        $bars = [];
        foreach ($rows as $index => $row) {
            $value = (int) ($row[$valueField] ?? 0);
            $y = $topPadding + ($index * $rowHeight);
            $barWidth = $max > 0 ? round(($value / $max) * $plotWidth, 2) : 0.0;
            $bars[] = [
                'x' => $labelWidth,
                'y' => $y,
                'width' => $barWidth,
                'label' => $this->shortenLabel((string) ($row[$labelField] ?? ''), 28),
                'full_label' => (string) ($row[$labelField] ?? ''),
                'value' => $value . $suffix,
                'label_x' => $leftPadding,
                'label_y' => $y + 10.5,
                'value_x' => $labelWidth + $barWidth + 8.0,
                'value_y' => $y + 10.5,
                'height' => $barHeight,
            ];
        }

        return [
            'bars' => $bars,
            'max' => $max,
            'width' => (int) $chartWidth,
            'height' => (int) max(86.0, $topPadding + (count($rows) * $rowHeight)),
        ];
    }

    private function shortenLabel(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function applyBarPercent(array $rows, string $metricField): array
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int) ($row[$metricField] ?? 0));
        }

        foreach ($rows as &$row) {
            $value = (int) ($row[$metricField] ?? 0);
            $row['bar_percent'] = $max > 0 ? max(6, (int) round(($value / $max) * 100)) : 0;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function buildCsvResponse(string $filename, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['request_id', 'metodo', 'caminho', 'assinante', 'programa', 'versao_programa', 'status_processamento', 'status_http', 'modo', 'recebimento', 'fim_processamento']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['u_c_request_id'] ?? '',
                    $row['c_metodo'] ?? '',
                    $row['c_caminho'] ?? '',
                    trim((string) (($row['c_assinante_identificador'] ?? '') . ' ' . ($row['c_assinante_nome'] ?? ''))),
                    $row['c_cod_programa'] ?? '',
                    $row['c_versao_programa'] ?? '',
                    $row['si_status_processamento'] ?? '',
                    $row['si_status_http'] ?? '',
                    $row['c_modo_execucao'] ?? '',
                    $row['dt_hr_recebimento'] ?? '',
                    $row['dt_hr_fim_processamento'] ?? '',
                ]);
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function buildXlsxResponse(string $filename, array $rows): Response
    {
        $sheetRows = [];
        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['u_c_request_id'] ?? '',
                $row['c_metodo'] ?? '',
                $row['c_caminho'] ?? '',
                trim((string) (($row['c_assinante_identificador'] ?? '') . ' ' . ($row['c_assinante_nome'] ?? ''))),
                $row['c_cod_programa'] ?? '',
                $row['c_versao_programa'] ?? '',
                $row['si_status_processamento'] ?? '',
                $row['si_status_http'] ?? '',
                $row['c_modo_execucao'] ?? '',
                $row['dt_hr_recebimento'] ?? '',
                $row['dt_hr_fim_processamento'] ?? '',
            ];
        }

        return XlsxResponseFactory::create(
            $filename,
            ['request_id', 'metodo', 'caminho', 'assinante', 'programa', 'versao_programa', 'status_processamento', 'status_http', 'modo', 'recebimento', 'fim_processamento'],
            $sheetRows
        );
    }

    /**
     * @param list<array<string, mixed>> $requests
     * @return array{0:?string,1:?string}
     */
    private function resolveNeighborIds(array $requests, ?string $selectedId): array
    {
        if ($selectedId === null || $selectedId === '') {
            return [null, null];
        }

        $ids = array_values(array_map(static fn (array $row): string => (string) $row['u_c_request_id'], $requests));
        $index = array_search($selectedId, $ids, true);
        if ($index === false) {
            return [null, null];
        }

        return [
            $ids[$index - 1] ?? null,
            $ids[$index + 1] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private function maskHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            $normalized = strtolower((string) $name);
            if (in_array($normalized, ['authorization', 'x-api-token'], true)) {
                $headers[$name] = $normalized === 'authorization' ? 'Bearer [mascarado]' : '[mascarado]';
            }
        }

        return $headers;
    }
}
