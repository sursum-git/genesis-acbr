<?php

namespace App\Controller;

use App\Repository\ApiAuditRepository;
use App\Support\ApiRequestAttributes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiRequestStatusController
{
    public function __construct(private readonly ApiAuditRepository $auditRepository)
    {
    }

    #[Route('/requests/{requestId}', name: 'app_api_request_status', methods: ['GET'])]
    public function __invoke(string $requestId, Request $request): JsonResponse
    {
        $tokenHash = $request->attributes->get(ApiRequestAttributes::TOKEN_HASH);
        if (!is_string($tokenHash) || $tokenHash === '') {
            return new JsonResponse(['mensagem' => 'Token nao identificado na requisicao.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $status = $this->auditRepository->findRequestStatus($requestId, $tokenHash);
        if ($status === null) {
            return new JsonResponse(['mensagem' => 'Requisicao nao encontrada para este token.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($status);
    }
}
