<?php

namespace App\Controller;

use App\Repository\ApiTestCatalogRepository;
use App\Service\TestCatalog\ApiTestRunner;
use App\Support\XlsxResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TestCatalogController extends AbstractController
{
    public function __construct(
        private readonly ApiTestCatalogRepository $repository,
        private readonly ApiTestRunner $runner,
    ) {
    }

    #[Route('/catalogo-testes', name: 'app_test_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedGroupCode = trim((string) $request->query->get('grupo', ''));
        $selectedTestCode = trim((string) $request->query->get('teste', ''));
        $selectedBatchId = (int) $request->query->get('lote', 0);
        $sort = trim((string) $request->query->get('ordenar', 'last_recorded_at'));
        $direction = trim((string) $request->query->get('direcao', 'desc'));
        $page = max(1, (int) $request->query->get('pagina', 1));
        $perPage = 24;

        $groups = $this->repository->findGroups();
        $totalTests = $this->repository->countTests($search, $selectedGroupCode);
        $totalPages = max(1, (int) ceil($totalTests / $perPage));
        $page = min($page, $totalPages);
        if ((string) $request->query->get('csv', '0') === '1') {
            return $this->buildCsvResponse(
                'catalogo_testes.csv',
                $this->repository->findTests($search, $selectedGroupCode, 10000, 0, $sort, $direction)
            );
        }
        if ((string) $request->query->get('xlsx', '0') === '1') {
            return $this->buildXlsxResponse(
                'catalogo_testes.xlsx',
                $this->repository->findTests($search, $selectedGroupCode, 10000, 0, $sort, $direction)
            );
        }
        $tests = $this->repository->findTests($search, $selectedGroupCode, $perPage, ($page - 1) * $perPage, $sort, $direction);

        $selectedTest = null;
        $selectedTestRuns = [];
        if ($selectedTestCode !== '') {
            $selectedTest = $this->repository->findTestByCode($selectedTestCode);
        }

        if ($selectedTest === null && $tests !== []) {
            $selectedTest = $this->repository->findTestByCode((string) $tests[0]['code']);
        }

        if ($selectedTest !== null) {
            $selectedTestRuns = $this->repository->findRunsByTestId((int) $selectedTest['id']);
        }

        $selectedGroup = null;
        if ($selectedGroupCode !== '') {
            $selectedGroup = $this->repository->findGroupByCode($selectedGroupCode);
        } elseif ($selectedTest !== null) {
            $selectedGroup = $this->repository->findGroupByCode((string) $selectedTest['group_code']);
        }

        $selectedBatch = $selectedBatchId > 0
            ? $this->repository->findBatchById($selectedBatchId)
            : $this->repository->findLatestBatch();

        $batchRuns = [];
        if ($selectedBatch !== null) {
            $batchRuns = $this->repository->findRunsByBatchId((int) $selectedBatch['id']);
        }

        [$previousTestCode, $nextTestCode] = $this->resolveNeighborCodes(
            $tests,
            $selectedTest !== null ? (string) $selectedTest['code'] : null
        );

        return $this->render('catalog/test_catalog.html.twig', [
            'search' => $search,
            'groups' => $groups,
            'tests' => $tests,
            'selectedGroupCode' => $selectedGroupCode,
            'selectedGroup' => $selectedGroup,
            'selectedTest' => $selectedTest,
            'selectedTestRuns' => $selectedTestRuns,
            'selectedBatch' => $selectedBatch,
            'batchRuns' => $batchRuns,
            'recentBatches' => $this->repository->findRecentBatches(),
            'summary' => $this->repository->getSummary(),
            'baseUrl' => $this->resolveBaseUrl($request),
            'page' => $page,
            'perPage' => $perPage,
            'totalTests' => $totalTests,
            'totalPages' => $totalPages,
            'previousTestCode' => $previousTestCode,
            'nextTestCode' => $nextTestCode,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/catalogo-testes/executar/teste/{code}', name: 'app_test_catalog_run_test', methods: ['POST'])]
    public function runTest(string $code, Request $request): RedirectResponse
    {
        $test = $this->repository->findTestByCode($code);
        if ($test === null) {
            throw $this->createNotFoundException('Teste nao encontrado.');
        }

        $batchId = $this->runner->runTests([$test], 'individual', (string) $test['name'], $this->resolveBaseUrl($request));
        $this->addFlash('success', sprintf('Teste "%s" executado.', $test['name']));

        return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request, [
            'teste' => $code,
            'grupo' => (string) $test['group_code'],
            'lote' => $batchId,
        ]));
    }

    #[Route('/catalogo-testes/executar/grupo/{code}', name: 'app_test_catalog_run_group', methods: ['POST'])]
    public function runGroup(string $code, Request $request): RedirectResponse
    {
        $group = $this->repository->findGroupByCode($code);
        if ($group === null) {
            throw $this->createNotFoundException('Grupo de testes nao encontrado.');
        }

        $tests = $this->repository->findAutomatedTestsByGroupCode($code);
        if ($tests === []) {
            $this->addFlash('error', sprintf('O grupo "%s" ainda nao possui cenarios gravados para rerun.', $group['name']));

            return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request, [
                'grupo' => $code,
            ]));
        }

        $batchId = $this->runner->runTests($tests, 'grupo', (string) $group['name'], $this->resolveBaseUrl($request));
        $this->addFlash('success', sprintf('Grupo "%s" reexecutado com %d cenario(s).', $group['name'], count($tests)));

        return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request, [
            'grupo' => $code,
            'teste' => (string) $tests[0]['code'],
            'lote' => $batchId,
        ]));
    }

    #[Route('/catalogo-testes/executar/geral', name: 'app_test_catalog_run_all', methods: ['POST'])]
    public function runAll(Request $request): RedirectResponse
    {
        $tests = $this->repository->findAllAutomatedTests();
        if ($tests === []) {
            $this->addFlash('error', 'Ainda nao existem cenarios gravados para execucao geral.');

            return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request));
        }

        $batchId = $this->runner->runTests($tests, 'geral', 'Execucao geral', $this->resolveBaseUrl($request));
        $this->addFlash('success', sprintf('Execucao geral concluida com %d cenario(s).', count($tests)));

        return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request, [
            'teste' => (string) $tests[0]['code'],
            'grupo' => (string) $tests[0]['group_code'],
            'lote' => $batchId,
        ]));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildRedirectParams(Request $request, array $overrides = []): array
    {
        $params = [
            'q' => trim((string) $request->request->get('q', '')),
            'grupo' => trim((string) $request->request->get('grupo', '')),
            'teste' => trim((string) $request->request->get('teste', '')),
            'pagina' => trim((string) $request->request->get('pagina', '')),
        ];

        foreach ($overrides as $key => $value) {
            $params[$key] = $value;
        }

        return array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    private function resolveBaseUrl(Request $request): string
    {
        $configuredBaseUrl = trim((string) ($_ENV['API_TEST_BASE_URL'] ?? $_SERVER['API_TEST_BASE_URL'] ?? ''));
        if ($configuredBaseUrl !== '') {
            return rtrim($configuredBaseUrl, '/');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/index.php';
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
            fputcsv($handle, ['codigo', 'nome', 'grupo', 'metodo', 'path', 'request_count', 'last_status_code', 'last_duration_ms', 'last_recorded_at', 'descricao']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['code'] ?? '',
                    $row['name'] ?? '',
                    $row['group_name'] ?? '',
                    $row['method'] ?? '',
                    $row['path'] ?? '',
                    $row['request_count'] ?? '',
                    $row['last_status_code'] ?? '',
                    $row['last_duration_ms'] ?? '',
                    $row['last_recorded_at'] ?? '',
                    $row['description'] ?? '',
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
                $row['code'] ?? '',
                $row['name'] ?? '',
                $row['group_name'] ?? '',
                $row['method'] ?? '',
                $row['path'] ?? '',
                $row['request_count'] ?? '',
                $row['last_status_code'] ?? '',
                $row['last_duration_ms'] ?? '',
                $row['last_recorded_at'] ?? '',
                $row['description'] ?? '',
            ];
        }

        return XlsxResponseFactory::create(
            $filename,
            ['codigo', 'nome', 'grupo', 'metodo', 'path', 'request_count', 'last_status_code', 'last_duration_ms', 'last_recorded_at', 'descricao'],
            $sheetRows
        );
    }

    /**
     * @param list<array<string, mixed>> $tests
     * @return array{0:?string,1:?string}
     */
    private function resolveNeighborCodes(array $tests, ?string $selectedCode): array
    {
        if ($selectedCode === null || $selectedCode === '') {
            return [null, null];
        }

        $codes = array_values(array_map(static fn (array $test): string => (string) $test['code'], $tests));
        $index = array_search($selectedCode, $codes, true);
        if ($index === false) {
            return [null, null];
        }

        return [
            $codes[$index - 1] ?? null,
            $codes[$index + 1] ?? null,
        ];
    }
}
