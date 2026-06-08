<?php

namespace App\Service\Api;

use ApiPlatform\Metadata\Operation;
use App\Repository\ApiExecutionModeRepository;
use App\Repository\WebhookRepository;
use App\Support\ApiRequestAttributes;
use Symfony\Component\HttpFoundation\Request;

final class ApiExecutionModeResolver
{
    public function __construct(
        private readonly ApiExecutionModeRepository $repository,
        private readonly WebhookRepository $webhookRepository,
        private readonly ApiProgramVersionResolver $programVersionResolver,
        private readonly string $defaultMode = 'sync',
    ) {
    }

    public function resolve(Operation $operation, Request $request): string
    {
        if ((bool) $request->attributes->get(ApiRequestAttributes::INTERNAL_WORKER, false)) {
            return 'sync';
        }

        if ((bool) $request->attributes->get(ApiRequestAttributes::BYPASS_QUEUE, false)) {
            return 'sync';
        }

        $extraProperties = $operation->getExtraProperties();
        $operationName = (string) ($operation->getName() ?? '');
        $path = $request->getPathInfo();
        $subscriber = $request->attributes->get(ApiRequestAttributes::ASSINANTE);
        $subscriberIdentifier = is_array($subscriber) ? trim((string) ($subscriber['c_identificador'] ?? '')) : '';
        if ($subscriberIdentifier !== '') {
            $programVersion = $this->programVersionResolver->resolveByPath($path);
            $bindingMode = $this->webhookRepository->findExecutionModeForSubscriber(
                $subscriberIdentifier,
                $path,
                is_array($programVersion) ? (string) ($programVersion['code'] ?? '') : null
            );
            if ($bindingMode !== null) {
                return $bindingMode;
            }
        }

        $dbMode = $this->repository->findModeForOperation($operationName, $path);

        if ($dbMode !== null) {
            return $dbMode;
        }

        $operationMode = strtolower(trim((string) ($extraProperties['execution_mode'] ?? 'inherit')));
        if (in_array($operationMode, ['sync', 'async'], true)) {
            return $operationMode;
        }

        return in_array($this->defaultMode, ['sync', 'async'], true) ? $this->defaultMode : 'sync';
    }
}
