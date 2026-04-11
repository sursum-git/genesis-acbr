<?php

namespace App\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProgramCatalogController extends AbstractController
{
    #[Route('/catalogo-programas', name: 'app_program_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $databasePath = $this->getParameter('kernel.project_dir') . '/var/db/program_catalog.sqlite';
        $search = trim((string) $request->query->get('q', ''));
        $selectedCode = trim((string) $request->query->get('programa', ''));

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($search === '') {
            $programsStatement = $pdo->query(
                'SELECT code, name, path, physical_path, category, status, description, detailed_explanation, started_at, ended_at, updated_at
                 FROM programs
                 ORDER BY name'
            );
            $programs = $programsStatement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $programsStatement = $pdo->prepare(
                'SELECT code, name, path, physical_path, category, status, description, detailed_explanation, started_at, ended_at, updated_at
                 FROM programs
                 WHERE name LIKE :term
                    OR code LIKE :term
                    OR path LIKE :term
                    OR description LIKE :term
                    OR detailed_explanation LIKE :term
                 ORDER BY name'
            );
            $programsStatement->execute(['term' => '%' . $search . '%']);
            $programs = $programsStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        $selectedProgram = null;
        if ($selectedCode !== '') {
            $programStatement = $pdo->prepare(
                'SELECT id, code, name, path, physical_path, category, status, description, detailed_explanation, started_at, ended_at, created_at, updated_at
                 FROM programs
                 WHERE code = :code'
            );
            $programStatement->execute(['code' => $selectedCode]);
            $selectedProgram = $programStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($selectedProgram === null && $programs !== []) {
            $selectedProgram = $programs[0];
            $programStatement = $pdo->prepare(
                'SELECT id, code, name, path, physical_path, category, status, description, detailed_explanation, started_at, ended_at, created_at, updated_at
                 FROM programs
                 WHERE code = :code'
            );
            $programStatement->execute(['code' => $selectedProgram['code']]);
            $selectedProgram = $programStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $history = [];
        if ($selectedProgram !== null && isset($selectedProgram['id'])) {
            $historyStatement = $pdo->prepare(
                'SELECT event_type, event_summary, physical_path_snapshot, description_snapshot, detailed_explanation_snapshot, status_snapshot, started_at_snapshot, ended_at_snapshot, event_at
                 FROM program_history
                 WHERE program_id = :program_id
                 ORDER BY event_at DESC, id DESC'
            );
            $historyStatement->execute(['program_id' => $selectedProgram['id']]);
            $history = $historyStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->render('catalog/program_catalog.html.twig', [
            'search' => $search,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'history' => $history,
        ]);
    }
}
