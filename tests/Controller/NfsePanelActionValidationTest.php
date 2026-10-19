<?php

declare(strict_types=1);

use App\Controller\NfsePanelController;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

/** @var \Symfony\Component\Routing\RouterInterface $router */
$router = $kernel->getContainer()->get('router');
foreach ([
    '/monitor-nfse/acoes/cancelar' => 'app_nfse_panel_cancel_action',
    '/monitor-nfse/acoes/substituir' => 'app_nfse_panel_substitute_action',
] as $path => $routeName) {
    $router->getContext()->setMethod('POST');
    $match = $router->match($path);
    if (($match['_route'] ?? '') !== $routeName) {
        fwrite(STDERR, "NFSe action route {$path} should match {$routeName}.\n");
        exit(1);
    }
}

/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer()->get('test.service_container');
/** @var RequestStack $requestStack */
$requestStack = $kernel->getContainer()->get('request_stack');
/** @var NfsePanelController $controller */
$controller = $container->get(NfsePanelController::class);

$cancelRequest = Request::create('/monitor-nfse/acoes/cancelar', 'POST', [], [], [], [], '{}');
$cancelRequest->headers->set('Content-Type', 'application/json');
$requestStack->push($cancelRequest);
$cancelResponse = $controller->cancelAction($cancelRequest);

if ($cancelResponse->getStatusCode() !== 400 || !str_contains((string) $cancelResponse->getContent(), 'cancelamento')) {
    fwrite(STDERR, "NFSe cancel action should reject empty payload with 400.\n");
    exit(1);
}

$substituteRequest = Request::create('/monitor-nfse/acoes/substituir', 'POST', [], [], [], [], '{}');
$substituteRequest->headers->set('Content-Type', 'application/json');
$requestStack->push($substituteRequest);
$substituteResponse = $controller->substituteAction($substituteRequest);

if ($substituteResponse->getStatusCode() !== 400 || !str_contains((string) $substituteResponse->getContent(), 'substituicao')) {
    fwrite(STDERR, "NFSe substitute action should reject empty payload with 400.\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
