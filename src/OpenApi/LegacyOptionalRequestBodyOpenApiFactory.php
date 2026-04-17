<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
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
            $paths->addPath($path, $this->normalizePathItem($path, $pathItem, $openApi));
        }

        return $openApi->withPaths($paths);
    }

    private function normalizePathItem(string $path, PathItem $pathItem, OpenApi $openApi): PathItem
    {
        if (!$this->supportsXmlPath($path) && !$this->isLegacyPath($path)) {
            return $pathItem;
        }

        return $pathItem
            ->withGet($this->normalizeOperation($path, $pathItem->getGet(), $openApi))
            ->withPost($this->normalizeOperation($path, $pathItem->getPost(), $openApi))
            ->withPut($this->normalizeOperation($path, $pathItem->getPut(), $openApi))
            ->withPatch($this->normalizeOperation($path, $pathItem->getPatch(), $openApi))
            ->withDelete($this->normalizeOperation($path, $pathItem->getDelete(), $openApi));
    }

    private function normalizeOperation(string $path, ?Operation $operation, OpenApi $openApi): ?Operation
    {
        if ($operation === null || $operation->getRequestBody() === null) {
            return $operation;
        }

        $requestBody = $operation->getRequestBody();

        if ($this->supportsXmlPath($path)) {
            $requestBody = $this->addXmlMediaType($requestBody, $openApi);
        }

        if ($this->isLegacyPath($path)) {
            $requestBody = $requestBody->withRequired(false);
        }

        return $operation->withRequestBody($requestBody);
    }

    private function isLegacyPath(string $path): bool
    {
        return str_starts_with($path, '/nfe/') || str_starts_with($path, '/nfse/');
    }

    private function supportsXmlPath(string $path): bool
    {
        return $this->isLegacyPath($path) || str_starts_with($path, '/acbr-cep/');
    }

    private function addXmlMediaType(RequestBody $requestBody, OpenApi $openApi): RequestBody
    {
        $content = $requestBody->getContent();
        if ($content === null) {
            return $requestBody;
        }

        $jsonMediaType = $content['application/ld+json'] ?? $content['application/json'] ?? null;
        if ($jsonMediaType === null) {
            return $requestBody;
        }

        $xmlExample = $this->buildXmlExample($jsonMediaType, $openApi);
        $xmlSourceExample = $this->extractExample($jsonMediaType);
        if ($xmlSourceExample === null) {
            $xmlSourceExample = $this->buildExampleFromSchema($this->extractSchema($jsonMediaType), $openApi);
        }

        $xmlSchema = \is_array($xmlSourceExample)
            ? $this->buildXmlSchema($xmlSourceExample, 'request')
            : $this->extractSchema($jsonMediaType);

        if (\is_string($xmlExample) && $xmlExample !== '' && $xmlSchema instanceof \ArrayObject) {
            $xmlSchema['example'] = $xmlExample;
        }

        $updatedContent = new \ArrayObject(iterator_to_array($content));
        $updatedContent['application/xml'] = new MediaType($xmlSchema, $xmlExample);

        return $requestBody->withContent($updatedContent);
    }

    private function buildXmlExample(mixed $mediaType, OpenApi $openApi): ?string
    {
        $example = $this->extractExample($mediaType);
        if ($example === null) {
            $example = $this->buildExampleFromSchema($this->extractSchema($mediaType), $openApi);
        }

        if (!\is_array($example) || $example === []) {
            return null;
        }

        return $this->arrayToXmlString($example);
    }

    private function buildExampleFromSchema(?\ArrayObject $schema, OpenApi $openApi): mixed
    {
        if ($schema === null) {
            return null;
        }

        $data = $schema->getArrayCopy();

        if (isset($data['example'])) {
            return $data['example'];
        }

        if (isset($data['$ref']) && \is_string($data['$ref'])) {
            $refName = $this->extractComponentName($data['$ref']);
            $schemas = $openApi->getComponents()->getSchemas();
            $referenced = $refName !== null && $schemas instanceof \ArrayObject ? ($schemas[$refName] ?? null) : null;

            if ($referenced instanceof \ArrayObject) {
                return $this->buildExampleFromSchema($referenced, $openApi);
            }
        }

        $type = $this->normalizeSchemaType($data['type'] ?? null);
        $properties = $data['properties'] ?? null;

        if ($type === 'object' && ($properties instanceof \ArrayObject || \is_array($properties))) {
            $example = [];

            foreach ($properties as $name => $propertySchema) {
                if ($propertySchema instanceof \ArrayObject || \is_array($propertySchema)) {
                    $example[$name] = $this->buildExampleFromSchema(
                        $propertySchema instanceof \ArrayObject ? $propertySchema : new \ArrayObject($propertySchema),
                        $openApi
                    );
                }
            }

            return $example;
        }

        $items = $data['items'] ?? null;
        if ($type === 'array' && ($items instanceof \ArrayObject || \is_array($items))) {
            return [$this->buildExampleFromSchema($items instanceof \ArrayObject ? $items : new \ArrayObject($items), $openApi)];
        }

        return match ($type) {
            'integer', 'number' => 0,
            'boolean' => false,
            default => 'string',
        };
    }

    private function extractComponentName(string $ref): ?string
    {
        $prefix = '#/components/schemas/';
        if (!str_starts_with($ref, $prefix)) {
            return null;
        }

        return substr($ref, \strlen($prefix));
    }

    private function arrayToXmlString(array $data, string $rootElement = 'request'): string
    {
        $xml = new \SimpleXMLElement(\sprintf('<%s/>', $rootElement));
        $this->appendXmlValues($xml, $data);

        return $xml->asXML() ?: '';
    }

    private function appendXmlValues(\SimpleXMLElement $element, mixed $value, ?string $name = null): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $childValue) {
                $childName = \is_string($key) ? $key : ($name ?? 'item');
                $child = $element->addChild($childName);
                $this->appendXmlValues($child, $childValue);
            }

            return;
        }

        if ($value === null) {
            $element[0] = '';

            return;
        }

        $element[0] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
    }

    private function extractSchema(mixed $mediaType): ?\ArrayObject
    {
        if ($mediaType instanceof MediaType) {
            return $mediaType->getSchema();
        }

        if ($mediaType instanceof \ArrayObject) {
            $schema = $mediaType['schema'] ?? null;

            return $schema instanceof \ArrayObject ? $schema : (\is_array($schema) ? new \ArrayObject($schema) : null);
        }

        if (\is_array($mediaType)) {
            $schema = $mediaType['schema'] ?? null;

            return $schema instanceof \ArrayObject ? $schema : (\is_array($schema) ? new \ArrayObject($schema) : null);
        }

        return null;
    }

    private function extractExample(mixed $mediaType): mixed
    {
        if ($mediaType instanceof MediaType) {
            return $mediaType->getExample();
        }

        if ($mediaType instanceof \ArrayObject) {
            return $mediaType['example'] ?? null;
        }

        if (\is_array($mediaType)) {
            return $mediaType['example'] ?? null;
        }

        return null;
    }

    private function normalizeSchemaType(mixed $type): ?string
    {
        if (\is_string($type)) {
            return $type;
        }

        if (\is_array($type)) {
            foreach ($type as $candidate) {
                if (\is_string($candidate) && $candidate !== 'null') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function buildXmlSchema(array $example, string $rootName): \ArrayObject
    {
        $schema = new \ArrayObject([
            'type' => 'object',
            'xml' => ['name' => $rootName],
            'properties' => new \ArrayObject(),
        ]);

        /** @var \ArrayObject $properties */
        $properties = $schema['properties'];

        foreach ($example as $name => $value) {
            $properties[$name] = $this->buildXmlPropertySchema($name, $value);
        }

        return $schema;
    }

    private function buildXmlPropertySchema(string $name, mixed $value): \ArrayObject
    {
        if (\is_array($value)) {
            if ($this->isList($value)) {
                $items = $value === [] ? 'string' : $value[0];

                return new \ArrayObject([
                    'type' => 'array',
                    'xml' => ['name' => $name, 'wrapped' => false],
                    'items' => $this->buildXmlPropertySchema('item', $items),
                ]);
            }

            return $this->buildXmlSchema($value, $name);
        }

        $type = match (true) {
            \is_int($value) => 'integer',
            \is_float($value) => 'number',
            \is_bool($value) => 'boolean',
            default => 'string',
        };

        return new \ArrayObject([
            'type' => $type,
            'xml' => ['name' => $name],
            'example' => $value,
        ]);
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, \count($value) - 1);
    }
}
