<?php

namespace App\EventSubscriber;

use App\Http\Exception\AcbrLegacyApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AcbrLegacyApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof AcbrLegacyApiException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['mensagem' => $throwable->getMessage()],
            JsonResponse::HTTP_BAD_REQUEST
        ));
    }
}
