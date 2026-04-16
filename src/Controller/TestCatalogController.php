<?php

namespace App\Controller;

use App\Repository\ApiTestCatalogRepository;
use App\Service\TestCatalog\ApiTestRunner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $groups = $this->repository->findGroups();
        $tests = $this->repository->findTests($search, $selectedGroupCode);

        $selectedTest = null;
        if ($selectedTestCode !== '') {
            $selectedTest = $this->repository->findTestByCode($selectedTestCode);
        }

        if ($selectedTest === null && $tests !== []) {
            $selectedTest = $this->repository->findTestByCode((string) $tests[0]['code']);
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

        return $this->render('catalog/test_catalog.html.twig', [
            'search' => $search,
            'groups' => $groups,
            'tests' => $tests,
            'selectedGroupCode' => $selectedGroupCode,
            'selectedGroup' => $selectedGroup,
            'selectedTest' => $selectedTest,
            'selectedBatch' => $selectedBatch,
            'batchRuns' => $batchRuns,
            'recentBatches' => $this->repository->findRecentBatches(),
            'summary' => $this->repository->getSummary(),
            'baseUrl' => $this->resolveBaseUrl($request),
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
            $this->addFlash('error', sprintf('O grupo "%s" nao possui testes automatizados ativos.', $group['name']));

            return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request, [
                'grupo' => $code,
            ]));
        }

        $batchId = $this->runner->runTests($tests, 'grupo', (string) $group['name'], $this->resolveBaseUrl($request));
        $this->addFlash('success', sprintf('Grupo "%s" executado com %d teste(s).', $group['name'], count($tests)));

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
            $this->addFlash('error', 'Nao existem testes automatizados ativos para execucao geral.');

            return $this->redirectToRoute('app_test_catalog', $this->buildRedirectParams($request));
        }

        $batchId = $this->runner->runTests($tests, 'geral', 'Execucao geral', $this->resolveBaseUrl($request));
        $this->addFlash('success', sprintf('Execucao geral concluida com %d teste(s).', count($tests)));

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
}
