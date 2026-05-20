<?php

namespace App\EventSubscriber;

use App\Service\TestCatalog\ApiTestRequestRecorder;
use Throwable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiTestCatalogSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ApiTestRequestRecorder $recorder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->shouldIgnore($request->getPathInfo())) {
            return;
        }

        try {
            $this->recorder->recordFromApiPlatform($request, $event->getResponse());
        } catch (Throwable $throwable) {
            @file_put_contents(
                dirname(__DIR__, 2) . '/var/log/test_catalog_capture.log',
                sprintf(
                    "[%s] %s %s | %s\n",
                    date('c'),
                    $request->getMethod(),
                    $request->getRequestUri(),
                    $throwable->getMessage()
                ),
                FILE_APPEND
            );
        }
    }

    private function shouldIgnore(string $pathInfo): bool
    {
        if ($pathInfo === '' || $pathInfo === '/') {
            return true;
        }

        foreach (['/catalogo-testes', '/catalogo-programas', '/docs', '/_profiler', '/_wdt'] as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
