<?php

namespace App\Controller;

use App\Repository\ProgramCatalogRepository;
use App\Support\XlsxResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProgramCatalogController extends AbstractController
{
    public function __construct(private readonly ProgramCatalogRepository $repository)
    {
    }

    #[Route('/catalogo-programas', name: 'app_program_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedCode = trim((string) $request->query->get('programa', ''));
        $sort = trim((string) $request->query->get('ordenar', 'name'));
        $direction = trim((string) $request->query->get('direcao', 'asc'));
        $page = max(1, (int) $request->query->get('pagina', 1));
        $perPage = 24;
        $totalPrograms = $this->repository->countPrograms($search);
        $totalPages = max(1, (int) ceil($totalPrograms / $perPage));
        $page = min($page, $totalPages);

        if ((string) $request->query->get('csv', '0') === '1') {
            return $this->buildCsvResponse(
                'catalogo_programas.csv',
                $this->repository->findPrograms($search, 10000, 0, $sort, $direction)
            );
        }
        if ((string) $request->query->get('xlsx', '0') === '1') {
            return $this->buildXlsxResponse(
                'catalogo_programas.xlsx',
                $this->repository->findPrograms($search, 10000, 0, $sort, $direction)
            );
        }

        $programs = $this->repository->findPrograms($search, $perPage, ($page - 1) * $perPage, $sort, $direction);

        $selectedProgram = null;
        if ($selectedCode !== '') {
            $selectedProgram = $this->repository->findProgramByCode($selectedCode);
        }

        if ($selectedProgram === null && $programs !== []) {
            $selectedProgram = $this->repository->findProgramByCode((string) $programs[0]['code']);
        }

        $history = [];
        if ($selectedProgram !== null && isset($selectedProgram['id'])) {
            $history = $this->repository->findHistoryByProgramId((int) $selectedProgram['id']);
        }

        [$previousProgramCode, $nextProgramCode] = $this->resolveNeighborCodes(
            $programs,
            $selectedProgram !== null ? (string) $selectedProgram['code'] : null
        );

        return $this->render('catalog/program_catalog.html.twig', [
            'search' => $search,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'history' => $history,
            'page' => $page,
            'perPage' => $perPage,
            'totalPrograms' => $totalPrograms,
            'totalPages' => $totalPages,
            'previousProgramCode' => $previousProgramCode,
            'nextProgramCode' => $nextProgramCode,
            'sort' => $sort,
            'direction' => $direction,
        ]);
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

            fputcsv($handle, ['codigo', 'nome', 'categoria', 'status', 'versao', 'fonte_versao', 'revisao', 'ultima_atualizacao', 'caminho', 'caminho_fisico', 'descricao']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['code'] ?? '',
                    $row['name'] ?? '',
                    $row['category'] ?? '',
                    $row['status'] ?? '',
                    $row['version'] ?? '',
                    $row['version_source'] ?? '',
                    $row['reference_commit'] ?? '',
                    $row['last_updated_at'] ?? '',
                    $row['path'] ?? '',
                    $row['physical_path'] ?? '',
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
                $row['category'] ?? '',
                $row['status'] ?? '',
                $row['version'] ?? '',
                $row['version_source'] ?? '',
                $row['reference_commit'] ?? '',
                $row['last_updated_at'] ?? '',
                $row['path'] ?? '',
                $row['physical_path'] ?? '',
                $row['description'] ?? '',
            ];
        }

        return XlsxResponseFactory::create(
            $filename,
            ['codigo', 'nome', 'categoria', 'status', 'versao', 'fonte_versao', 'revisao', 'ultima_atualizacao', 'caminho', 'caminho_fisico', 'descricao'],
            $sheetRows
        );
    }

    /**
     * @param list<array<string, mixed>> $programs
     * @return array{0:?string,1:?string}
     */
    private function resolveNeighborCodes(array $programs, ?string $selectedCode): array
    {
        if ($selectedCode === null || $selectedCode === '') {
            return [null, null];
        }

        $codes = array_values(array_map(static fn (array $program): string => (string) $program['code'], $programs));
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
