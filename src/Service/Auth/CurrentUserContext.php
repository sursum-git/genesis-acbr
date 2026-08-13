<?php

namespace App\Service\Auth;

use App\Repository\Auth\AuthUserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class CurrentUserContext
{
    public const SESSION_KEY = 'auth_user';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AuthUserRepository $userRepository,
        private readonly AuthTokenService $tokenService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $attributeUser = $request->attributes->get('_auth_user');
        if (is_array($attributeUser)) {
            return $attributeUser;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $sessionUser = $session?->get(self::SESSION_KEY);
        if (is_array($sessionUser) && isset($sessionUser['id'])) {
            $user = $this->buildUserContext((int) $sessionUser['id'], isset($sessionUser['subscriber_id']) ? (int) $sessionUser['subscriber_id'] : null);
            if ($user !== null) {
                $request->attributes->set('_auth_user', $user);

                return $user;
            }
        }

        return null;
    }

    public function loginSession(int $userId, ?int $subscriberId = null): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set(self::SESSION_KEY, ['id' => $userId, 'subscriber_id' => $subscriberId]);
        $request->attributes->set('_auth_user', $this->buildUserContext($userId, $subscriberId));
    }

    public function logoutSession(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->hasSession()) {
            $request->getSession()->remove(self::SESSION_KEY);
        }
    }

    public function authenticateJwt(string $token, ?int $requestedSubscriberId = null): ?array
    {
        $claims = $this->tokenService->verifyAccessToken($token);
        if ($claims === null) {
            return null;
        }

        $subscriberId = $requestedSubscriberId;
        if ($subscriberId === null && isset($claims['sid'])) {
            $subscriberId = (int) $claims['sid'];
        }

        return $this->buildUserContext((int) ($claims['sub'] ?? 0), $subscriberId, 'jwt');
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    public function isSuperAdmin(): bool
    {
        $user = $this->user();

        return is_array($user) && in_array('super_admin', $user['roles'] ?? [], true);
    }

    /**
     * @return list<int>
     */
    public function subscriberIds(): array
    {
        $user = $this->user();
        if (!is_array($user)) {
            return [];
        }

        return array_values(array_map('intval', is_array($user['subscriber_ids'] ?? null) ? $user['subscriber_ids'] : []));
    }

    public function activeSubscriberId(): ?int
    {
        $user = $this->user();
        if (!is_array($user) || !isset($user['subscriber_id'])) {
            return null;
        }

        return (int) $user['subscriber_id'];
    }

    public function subscriberToken(?int $subscriberId = null): ?string
    {
        $user = $this->user();
        if (!is_array($user)) {
            return null;
        }

        $targetSubscriberId = $subscriberId ?? $this->activeSubscriberId();
        if ($targetSubscriberId === null) {
            $ids = $this->subscriberIds();
            $targetSubscriberId = count($ids) === 1 ? $ids[0] : null;
        }

        return $targetSubscriberId === null ? null : $this->userRepository->subscriberTokenForUser((int) $user['id'], $targetSubscriberId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildUserContext(int $userId, ?int $subscriberId = null, string $authType = 'session'): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = $this->userRepository->findActiveById($userId);
        if ($row === null) {
            return null;
        }

        $subscriberIds = $this->userRepository->subscriberIdsForUser($userId);
        if ($subscriberId !== null && !in_array($subscriberId, $subscriberIds, true)) {
            return null;
        }

        return [
            'id' => $userId,
            'username' => (string) ($row['c_usuario'] ?? ''),
            'name' => (string) ($row['c_nome'] ?? ''),
            'type' => (string) ($row['c_tipo'] ?? 'common'),
            'roles' => $this->userRepository->rolesForUser($userId),
            'subscriber_ids' => $subscriberIds,
            'subscriber_id' => $subscriberId,
            'auth_type' => $authType,
        ];
    }
}
