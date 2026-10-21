<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\LegacyOptionalRequestBodyOpenApiFactory;
use App\Service\Api\InternalApiRequestRunner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$factory = new class implements OpenApiFactoryInterface {
    public function __invoke(array $context = []): OpenApi {
        $paths = new Paths();
        $paths->addPath('/nfe/consultas/status-servico', new PathItem(get: new Operation()));
        $paths->addPath('/nfe/envio/enviar-sincrono-xml', new PathItem(post: new Operation()));
        $paths->addPath('/nfse/test', new PathItem(get: new Operation()));
        return new OpenApi(new Info('test', '1'), [], $paths);
    }
};
$api = (new LegacyOptionalRequestBodyOpenApiFactory($factory))();
foreach (['/nfe/consultas/status-servico' => 'getGet', '/nfe/envio/enviar-sincrono-xml' => 'getPost'] as $path => $method) {
    $parameters = $api->getPaths()->getPath($path)->$method()->getParameters();
    $headers = array_values(array_filter($parameters, fn ($p) => $p->getName() === 'X-ACBr-Ambiente'));
    if (count($headers) !== 1 || $headers[0]->getIn() !== 'header' || $headers[0]->getSchema()['enum'] !== [1, 2]) {
        throw new RuntimeException('OpenAPI must describe the numeric NFe environment header.');
    }
}
$kernel = new class implements HttpKernelInterface {
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response {
        if ($request->headers->get('X-ACBr-Ambiente') !== '2') {
            throw new RuntimeException('Async worker lost the selected environment.');
        }
        return new Response('OK');
    }
};
(new InternalApiRequestRunner($kernel))->run([
    'c_caminho' => '/nfe/test', 'c_metodo' => 'POST',
    't_headers_requisicao' => json_encode(['x-acbr-ambiente' => '2']),
]);
echo "OK\n";
