<?php

namespace App\Controller;

use App\Repository\Auth\AuthUserRepository;
use App\Repository\Auth\RefreshTokenRepository;
use App\Service\Auth\AuthTokenService;
use App\Service\Auth\CurrentUserContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthUserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly AuthTokenService $tokenService,
        private readonly CurrentUserContext $currentUser,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->render('auth/login.html.twig', ['error' => null, 'username' => '']);
        }

        $username = trim((string) $request->request->get('usuario', ''));
        $password = (string) $request->request->get('senha', '');
        $user = $this->users->findActiveByUsername($username);
        if ($user === null || !password_verify($password, (string) ($user['c_senha_hash'] ?? ''))) {
            return $this->render('auth/login.html.twig', ['error' => 'Usuario ou senha invalidos.', 'username' => $username], new Response('', Response::HTTP_UNAUTHORIZED));
        }

        $subscriberIds = $this->users->subscriberIdsForUser((int) $user['id_t00005']);
        $this->currentUser->loginSession((int) $user['id_t00005'], count($subscriberIds) === 1 ? $subscriberIds[0] : null);

        return new RedirectResponse('/index.php/');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): RedirectResponse
    {
        $this->currentUser->logoutSession();

        return new RedirectResponse('/index.php/login');
    }

    #[Route('/api/auth/token', name: 'app_api_auth_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $username = trim((string) ($payload['usuario'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['senha'] ?? $payload['password'] ?? '');
        $subscriberId = isset($payload['subscriber_id']) ? (int) $payload['subscriber_id'] : null;
        $user = $this->users->findActiveByUsername($username);
        if ($user === null || !password_verify($password, (string) ($user['c_senha_hash'] ?? ''))) {
            return $this->json(['message' => 'Usuario ou senha invalidos.'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $user['id_t00005'];
        $subscriberIds = $this->users->subscriberIdsForUser($userId);
        if ($subscriberId !== null && !in_array($subscriberId, $subscriberIds, true)) {
            return $this->json(['message' => 'Assinante nao permitido para este usuario.'], Response::HTTP_FORBIDDEN);
        }
        if ($subscriberId === null && count($subscriberIds) === 1) {
            $subscriberId = $subscriberIds[0];
        }

        $roles = $this->users->rolesForUser($userId);
        $refresh = $this->tokenService->issueRefreshToken();
        $this->refreshTokens->store($userId, $refresh['hash'], $refresh['family'], $refresh['expires_at']);

        return $this->json([
            'access_token' => $this->tokenService->issueAccessToken($userId, $roles, $subscriberId),
            'expires_in' => 900,
            'refresh_token' => $refresh['plain'],
            'token_type' => 'Bearer',
            'subscriber_ids' => $subscriberIds,
        ]);
    }

    #[Route('/api/auth/refresh', name: 'app_api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $plain = is_array($payload) ? trim((string) ($payload['refresh_token'] ?? '')) : '';
        $row = $plain !== '' ? $this->refreshTokens->consume(hash('sha256', $plain)) : null;
        if ($row === null) {
            return $this->json(['message' => 'Refresh token invalido.'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $row['t00005_id'];
        $roles = $this->users->rolesForUser($userId);
        $refresh = $this->tokenService->issueRefreshToken();
        $this->refreshTokens->store($userId, $refresh['hash'], $refresh['family'], $refresh['expires_at']);

        return $this->json([
            'access_token' => $this->tokenService->issueAccessToken($userId, $roles),
            'expires_in' => 900,
            'refresh_token' => $refresh['plain'],
            'token_type' => 'Bearer',
        ]);
    }
}
