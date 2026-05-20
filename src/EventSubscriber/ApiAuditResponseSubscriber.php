<?php

namespace App\EventSubscriber;

use App\Service\Api\ApiAuditManager;
use App\Service\Api\ApiPathMatcher;
use App\Support\ApiRequestAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiAuditResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiPathMatcher $pathMatcher,
        private readonly ApiAuditManager $auditManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -64],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
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

        $desiredStatus = $request->attributes->get(ApiRequestAttributes::DESIRED_STATUS_CODE);
        if (is_int($desiredStatus) && $desiredStatus > 0) {
            $event->getResponse()->setStatusCode($desiredStatus);
        }

        $this->auditManager->finalize($request, $event->getResponse());
    }
}
