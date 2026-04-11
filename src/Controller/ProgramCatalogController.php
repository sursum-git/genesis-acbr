<?php

namespace App\Controller;

use App\Repository\ProgramCatalogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $programs = $this->repository->findPrograms($search);

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

        return $this->render('catalog/program_catalog.html.twig', [
            'search' => $search,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'history' => $history,
        ]);
    }
}
