<?php

declare(strict_types=1);

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
$match = $router->match('/monitor-nfse');
if (($match['_route'] ?? '') !== 'app_nfse_panel') {
    fwrite(STDERR, "NFSe panel route should match /monitor-nfse.\n");
    exit(1);
}

/** @var \App\Service\Api\ApiPathMatcher $apiPathMatcher */
$apiPathMatcher = $kernel->getContainer()->get('test.service_container')->get(\App\Service\Api\ApiPathMatcher::class);
if ($apiPathMatcher->isManagedPath('/monitor-nfse')) {
    fwrite(STDERR, "NFSe panel route should not be protected as an API path.\n");
    exit(1);
}

if (!$apiPathMatcher->isManagedPath('/nfse/demais-provedores/envio/enviar-um-rps')) {
    fwrite(STDERR, "NFSe API routes should remain protected as API paths.\n");
    exit(1);
}

/** @var RequestStack $requestStack */
$requestStack = $kernel->getContainer()->get('request_stack');
$request = Request::create('/monitor-nfse');
$request->attributes->set('_route', 'app_nfse_panel');
$request->attributes->set('_auth_user', [
    'id' => 2,
    'username' => 'usuario_comum',
    'name' => 'Usuário Comum',
    'type' => 'common',
    'roles' => ['common'],
    'subscriber_ids' => [10],
    'subscriber_id' => 10,
    'auth_type' => 'test',
]);
$requestStack->push($request);

/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer()->get('test.service_container');
/** @var \Twig\Environment $twig */
$twig = $container->get('twig');
$content = $twig->render('admin/nfse_panel.html.twig', [
    'emitRpsUrl' => '/index.php/monitor-nfse/acoes/emitir-rps',
    'cancelUrl' => '/index.php/monitor-nfse/acoes/cancelar',
    'substituteUrl' => '/index.php/monitor-nfse/acoes/substituir',
    'nationalEventUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/enviar-evento',
    'consultDpsUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/consultar-dps',
    'consultNfseUrl' => '/index.php/monitor-nfse/acoes/padrao-nacional/consultar-nfse',
    'docsUrl' => '/index.php/docs?filter_tags[]=nfse',
]);

foreach (['NFSe', 'RPS', 'Cancelar', 'Substituir', 'Padrao Nacional', 'Retorno'] as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "NFSe panel should render {$needle}.\n");
        exit(1);
    }
}

if (!preg_match('#<script src="/catalog-assets/monitor/nfse-panel\.js\?v=[^"&]+"></script>#', $content)) {
    fwrite(STDERR, "NFSe panel JavaScript URL should carry a cache-busting version.\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
