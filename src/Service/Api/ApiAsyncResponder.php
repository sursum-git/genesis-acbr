<?php

namespace App\Service\Api;

use ApiPlatform\Metadata\Operation;
use App\Support\ApiRequestAttributes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiAsyncResponder
{
    public function __construct(
        private readonly ApiExecutionModeResolver $executionModeResolver,
        private readonly ApiAuditManager $auditManager,
    ) {
    }

    public function shouldQueue(Operation $operation, Request $request): bool
    {
        $mode = $this->executionModeResolver->resolve($operation, $request);
        $request->attributes->set(ApiRequestAttributes::OPERATION_NAME, $operation->getName());
        $this->auditManager->updateOperationContext($request, $operation->getName(), $mode);

        return $mode === 'async';
    }

    public function accept(Request $request, Operation $operation, array $echo): array
    {
        $request->attributes->set(ApiRequestAttributes::DESIRED_STATUS_CODE, Response::HTTP_ACCEPTED);

        $payload = $this->auditManager->buildAcceptedPayload($request, $echo);
        $request->attributes->set(ApiRequestAttributes::ASYNC_ACCEPTED_PAYLOAD, $payload);
        $this->auditManager->markAsyncAccepted($request, (string) ($operation->getName() ?? ''));

        return $payload;
    }
}
