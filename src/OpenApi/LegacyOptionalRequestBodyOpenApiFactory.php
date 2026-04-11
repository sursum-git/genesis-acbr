<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;

final class LegacyOptionalRequestBodyOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = new Paths();

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            $paths->addPath($path, $this->normalizePathItem($path, $pathItem));
        }

        return $openApi->withPaths($paths);
    }

    private function normalizePathItem(string $path, PathItem $pathItem): PathItem
    {
        if (!$this->isLegacyPath($path)) {
            return $pathItem;
        }

        return $pathItem
            ->withGet($this->normalizeOperation($pathItem->getGet()))
            ->withPost($this->normalizeOperation($pathItem->getPost()))
            ->withPut($this->normalizeOperation($pathItem->getPut()))
            ->withPatch($this->normalizeOperation($pathItem->getPatch()))
            ->withDelete($this->normalizeOperation($pathItem->getDelete()));
    }

    private function normalizeOperation(?Operation $operation): ?Operation
    {
        if ($operation === null || $operation->getRequestBody() === null) {
            return $operation;
        }

        return $operation->withRequestBody($operation->getRequestBody()->withRequired(false));
    }

    private function isLegacyPath(string $path): bool
    {
        return str_starts_with($path, '/nfe/') || str_starts_with($path, '/nfse/');
    }
}
