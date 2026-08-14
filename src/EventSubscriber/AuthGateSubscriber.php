<?php

namespace App\EventSubscriber;

use App\Service\Api\ApiPathMatcher;
use App\Service\Auth\CurrentUserContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthGateSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_PATHS = [
        '/login',
        '/logout',
        '/api/auth/token',
        '/api/auth/refresh',
    ];

    private const SUPER_ADMIN_PREFIXES = [
        '/demos',
        '/usuarios',
        '/assinantes',
        '/configuracao-execucao',
        '/capacidade-workers',
        '/webhooks',
        '/monitor-workers',
        '/catalogo-testes',
    ];

    public function __construct(
        private readonly CurrentUserContext $currentUser,
        private readonly ApiPathMatcher $apiPathMatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo() !== '' ? $request->getPathInfo() : '/';

        if ($this->isPublicPath($path) || $this->apiPathMatcher->isManagedPath($path)) {
            return;
        }

        $jwtUser = $this->authenticateJwtFromHeader($event);
        if ($jwtUser !== null) {
            $request->attributes->set('_auth_user', $jwtUser);
        }

        if (!$this->currentUser->isAuthenticated()) {
            $event->setResponse($this->wantsJson($request->headers->get('Accept', '')) ? new JsonResponse(['message' => 'Login obrigatorio.'], Response::HTTP_UNAUTHORIZED) : new RedirectResponse('/index.php/login'));

            return;
        }

        if ($this->requiresSuperAdmin($path) && !$this->currentUser->isSuperAdmin()) {
            $event->setResponse($this->wantsJson($request->headers->get('Accept', '')) ? new JsonResponse(['message' => 'Acesso restrito ao administrador geral.'], Response::HTTP_FORBIDDEN) : new Response('Acesso restrito ao administrador geral.', Response::HTTP_FORBIDDEN));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authenticateJwtFromHeader(RequestEvent $event): ?array
    {
        $request = $event->getRequest();
        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if (!preg_match('/^Bearer\s+([^.]+\.[^.]+\.[^.]+)$/', $authorization, $matches)) {
            return null;
        }

        $subscriberIdHeader = trim((string) $request->headers->get('X-Subscriber-Id', ''));
        $subscriberId = $subscriberIdHeader !== '' ? (int) $subscriberIdHeader : null;

        return $this->currentUser->authenticateJwt((string) $matches[1], $subscriberId);
    }

    private function isPublicPath(string $path): bool
    {
        foreach (self::PUBLIC_PATHS as $publicPath) {
            if ($path === $publicPath) {
                return true;
            }
        }

        return false;
    }

    private function requiresSuperAdmin(string $path): bool
    {
        foreach (self::SUPER_ADMIN_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function wantsJson(string $accept): bool
    {
        return str_contains(strtolower($accept), 'json');
    }
}
