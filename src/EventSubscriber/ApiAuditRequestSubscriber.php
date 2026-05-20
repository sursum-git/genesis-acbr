<?php

namespace App\EventSubscriber;

use App\Service\Api\ApiAuditManager;
use App\Service\Api\ApiPathMatcher;
use App\Support\ApiRequestAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiAuditRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiPathMatcher $pathMatcher,
        private readonly ApiAuditManager $auditManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
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

        $this->auditManager->begin($request);
    }
}
