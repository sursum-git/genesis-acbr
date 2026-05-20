<?php

namespace App\EventSubscriber;

use App\Repository\ApiAssinanteRepository;
use App\Service\Api\ApiAuditManager;
use App\Service\Api\ApiPathMatcher;
use App\Service\Api\ApiTokenHasher;
use App\Support\ApiRequestAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiTokenAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiPathMatcher $pathMatcher,
        private readonly ApiAssinanteRepository $assinanteRepository,
        private readonly ApiTokenHasher $tokenHasher,
        private readonly ApiAuditManager $auditManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ((bool) $request->attributes->get(ApiRequestAttributes::INTERNAL_WORKER, false)) {
            return;
        }

        if (!$this->pathMatcher->isManagedPath($request->getPathInfo())) {
            return;
        }

        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if ($authorization === '') {
            $authorization = trim((string) $request->server->get('HTTP_AUTHORIZATION', ''));
        }
        if ($authorization === '') {
            $authorization = trim((string) $request->server->get('REDIRECT_HTTP_AUTHORIZATION', ''));
        }
        if ($authorization === '') {
            $apiToken = trim((string) $request->headers->get('X-Api-Token', ''));
            if ($apiToken === '') {
                $apiToken = trim((string) $request->server->get('HTTP_X_API_TOKEN', ''));
            }

            if ($apiToken !== '') {
                $authorization = 'Bearer ' . $apiToken;
            }
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $this->auditManager->markUnauthorized($request);
            $event->setResponse(new JsonResponse(['mensagem' => 'Bearer token ou X-Api-Token obrigatorio.'], JsonResponse::HTTP_UNAUTHORIZED));

            return;
        }

        $token = trim((string) ($matches[1] ?? ''));
        if ($token === '') {
            $this->auditManager->markUnauthorized($request);
            $event->setResponse(new JsonResponse(['mensagem' => 'Bearer token ou X-Api-Token obrigatorio.'], JsonResponse::HTTP_UNAUTHORIZED));

            return;
        }

        $assinante = $this->assinanteRepository->findByToken($token);
        if ($assinante === null) {
            $this->auditManager->markUnauthorized($request);
            $event->setResponse(new JsonResponse(['mensagem' => 'Token invalido.'], JsonResponse::HTTP_UNAUTHORIZED));

            return;
        }

        $request->attributes->set(ApiRequestAttributes::TOKEN_HASH, $this->tokenHasher->hash($token));
        $request->attributes->set(ApiRequestAttributes::ASSINANTE, $assinante);
        $this->auditManager->attachAuthentication($request, $assinante);
    }
}
