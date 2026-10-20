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
        $releases = $this->releases->list();
        $changesByRelease = [];
        foreach ($releases as $release) {
            $changesByRelease[(int) $release['id_t99037']] = $this->releases->listChanges((int) $release['id_t99037']);
        }

        return $this->render('admin/acbr_library_releases.html.twig', compact('releases', 'changesByRelease'));
    }

    #[Route('/bibliotecas-acbr/salvar', name: 'app_acbr_library_releases_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        try {
            $releaseId = $this->releases->save($request->request->all());
            foreach (preg_split('/\R+/', (string) $request->request->get('t_diferencas', '')) ?: [] as $difference) {
                if (trim($difference) !== '') {
                    $this->releases->saveChange($releaseId, ['c_categoria' => 'changelog', 't_descricao' => $difference, 'c_origem' => 'Cadastro administrativo']);
                }
            }
            $this->addFlash('success', 'Release ACBr cadastrada.');
        } catch (Throwable $exception) {
            $this->addFlash('error', 'Falha ao cadastrar release ACBr: ' . $exception->getMessage());
        }
        return $this->redirectToRoute('app_acbr_library_releases');
    }
}
