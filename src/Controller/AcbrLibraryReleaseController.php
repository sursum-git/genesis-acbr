<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AcbrLibraryReleaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class AcbrLibraryReleaseController extends AbstractController
{
    public function __construct(private readonly AcbrLibraryReleaseRepository $releases)
    {
    }

    #[Route('/bibliotecas-acbr', name: 'app_acbr_library_releases', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/acbr_library_releases.html.twig', ['releases' => $this->releases->list()]);
    }

    #[Route('/bibliotecas-acbr/salvar', name: 'app_acbr_library_releases_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        try {
            $this->releases->save($request->request->all());
            $this->addFlash('success', 'Release ACBr cadastrada.');
        } catch (Throwable $exception) {
            $this->addFlash('error', 'Falha ao cadastrar release ACBr: ' . $exception->getMessage());
        }
        return $this->redirectToRoute('app_acbr_library_releases');
    }
}
