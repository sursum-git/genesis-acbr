<?php

namespace App\Controller;

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly AuthSchemaManager $schemaManager,
        private readonly AuthUserRepository $users,
    ) {
    }

    #[Route('/usuarios', name: 'app_users', methods: ['GET'])]
    public function index(): Response
    {
        $loadError = null;
        $users = [];

        try {
            $this->schemaManager->ensureSchema();
            $users = $this->users->listUsers();
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'loadError' => $loadError,
            'types' => [
                'common' => 'Comum',
                'company_admin' => 'Admin do assinante',
                'super_admin' => 'Admin geral',
            ],
        ]);
    }

    #[Route('/usuarios/salvar', name: 'app_users_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        try {
            $this->schemaManager->ensureSchema();
            $username = trim((string) $request->request->get('usuario', ''));
            $name = trim((string) $request->request->get('nome', ''));
            $password = (string) $request->request->get('senha', '');
            $type = trim((string) $request->request->get('tipo', 'common'));

            if ($password === '') {
                throw new InvalidArgumentException('Informe uma senha inicial.');
            }

            if ($this->users->findActiveByUsername($username) !== null) {
                throw new InvalidArgumentException('Já existe um usuário ativo com esse login.');
            }

            $this->users->createUser($username, password_hash($password, PASSWORD_DEFAULT), $type, true, $name);
            $this->addFlash('success', 'Usuário criado.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao criar usuário: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_users');
    }
}
