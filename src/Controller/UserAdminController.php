<?php

namespace App\Controller;

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use App\Service\Auth\CurrentUserContext;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly CurrentUserContext $currentUser,
    ) {
    }

    #[Route('/usuarios', name: 'app_users', methods: ['GET'])]
    public function index(): Response
    {
        $loadError = null;
        $users = [];
        $userCompanies = [];

        try {
            $this->schemaManager->ensureSchema();
            $this->users->syncCompaniesFromMonitorIssuers();
            $users = $this->users->listUsers();
            $userCompanies = $this->users->companiesForUsers(array_map(static fn (array $user): int => (int) $user['id_t00005'], $users));
        } catch (Throwable $throwable) {
            $loadError = $throwable->getMessage();
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'userCompanies' => $userCompanies,
            'loadError' => $loadError,
            'types' => $this->allowedTypes(),
            'canCreateSuperAdmin' => $this->currentUser->isSuperAdmin(),
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

            $this->assertAllowedType($type);

            if ($password === '') {
                throw new InvalidArgumentException('Informe uma senha inicial.');
            }

            if ($this->users->findActiveByUsername($username) !== null) {
                throw new InvalidArgumentException('Já existe um usuário ativo com esse login.');
            }

            $userId = $this->users->createUser($username, password_hash($password, PASSWORD_DEFAULT), $type, true, $name);
            $companyRole = $this->companyRoleForType($type);
            if ($companyRole !== null) {
                $this->users->replaceUserCompanies($userId, $this->companyIdsFromRequest($request), $companyRole);
            }

            $this->addFlash('success', 'Usuário criado.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao criar usuário: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_users');
    }

    #[Route('/usuarios/atualizar', name: 'app_users_update', methods: ['POST'])]
    public function update(Request $request): RedirectResponse
    {
        try {
            $this->schemaManager->ensureSchema();
            $userId = (int) $request->request->get('usuario_id', 0);
            $user = $this->users->findById($userId);
            if ($user === null) {
                throw new InvalidArgumentException('Usuário não encontrado.');
            }

            $currentType = (string) ($user['c_tipo'] ?? 'common');
            if ($currentType === 'super_admin' && !$this->currentUser->isSuperAdmin()) {
                throw new InvalidArgumentException('Admin do assinante não pode editar administrador geral.');
            }

            $type = trim((string) $request->request->get('tipo', 'common'));
            $this->assertAllowedType($type);

            $name = trim((string) $request->request->get('nome', ''));
            $password = (string) $request->request->get('senha', '');
            $active = $request->request->get('ativo') === '1';
            $passwordHash = trim($password) !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            $this->users->updateUser($userId, $name, $type, $active, $passwordHash);
            $companyRole = $this->companyRoleForType($type);
            $this->users->replaceUserCompanies($userId, $companyRole === null ? [] : $this->companyIdsFromRequest($request), $companyRole ?? 'common');

            $this->addFlash('success', 'Usuário atualizado.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao atualizar usuário: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_users');
    }

    #[Route('/usuarios/empresas/busca', name: 'app_users_companies_search', methods: ['GET'])]
    public function searchCompanies(Request $request): JsonResponse
    {
        try {
            $this->schemaManager->ensureSchema();
            $this->users->syncCompaniesFromMonitorIssuers();

            return $this->json([
                'items' => $this->users->searchCompanies($request->query->getString('q')),
            ]);
        } catch (Throwable $throwable) {
            return $this->json(['items' => [], 'message' => $throwable->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/usuarios/empresas/salvar', name: 'app_users_companies_save', methods: ['POST'])]
    public function saveCompanies(Request $request): RedirectResponse
    {
        try {
            $this->schemaManager->ensureSchema();
            $userId = (int) $request->request->get('usuario_id', 0);
            $user = $this->users->findById($userId);
            if ($user === null) {
                throw new InvalidArgumentException('Usuário não encontrado.');
            }
            if (($user['c_tipo'] ?? '') === 'super_admin' && !$this->currentUser->isSuperAdmin()) {
                throw new InvalidArgumentException('Admin do assinante não pode alterar empresas de administrador geral.');
            }

            $companyRole = $this->companyRoleForType((string) ($user['c_tipo'] ?? 'common'));
            $this->users->replaceUserCompanies($userId, $companyRole === null ? [] : $this->companyIdsFromRequest($request), $companyRole ?? 'common');
            $this->addFlash('success', 'Empresas do usuário atualizadas.');
        } catch (Throwable $throwable) {
            $this->addFlash('error', 'Falha ao atualizar empresas do usuário: ' . $throwable->getMessage());
        }

        return $this->redirectToRoute('app_users');
    }

    /**
     * @return list<int>
     */
    private function companyIdsFromRequest(Request $request): array
    {
        $companyIds = $request->request->all('empresa_ids');
        if (!is_array($companyIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $companyIds), static fn (int $id): bool => $id > 0)));
    }

    private function companyRoleForType(string $type): ?string
    {
        return match ($type) {
            'company_admin' => 'company_admin',
            'common' => 'common',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function allowedTypes(): array
    {
        $types = [
            'common' => 'Comum',
            'company_admin' => 'Admin do assinante',
        ];

        if ($this->currentUser->isSuperAdmin()) {
            $types['super_admin'] = 'Admin geral';
        }

        return $types;
    }

    private function assertAllowedType(string $type): void
    {
        if (!array_key_exists($type, $this->allowedTypes())) {
            throw new InvalidArgumentException('Tipo de usuário não permitido para o usuário atual.');
        }
    }
}
