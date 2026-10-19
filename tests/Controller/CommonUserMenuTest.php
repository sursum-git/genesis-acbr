<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

function assertCommonMenuContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertCommonMenuNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$kernel = new Kernel('test', true);
$kernel->boot();

$request = Request::create('/');
$request->attributes->set('_route', 'app_home');
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
/** @var RequestStack $requestStack */
$requestStack = $kernel->getContainer()->get('request_stack');
$requestStack->push($request);

/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer()->get('test.service_container');
/** @var \Twig\Environment $twig */
$twig = $container->get('twig');
$content = $twig->render('admin/base.html.twig');

assertCommonMenuContains('Usuário Comum', $content, 'Header should show the current common user.');
assertCommonMenuContains('/index.php/monitor-saida-nfe', $content, 'Common user sidebar should keep output monitor link.');
assertCommonMenuContains('/index.php/monitor-entrada-nfe', $content, 'Common user sidebar should keep input monitor link.');
assertCommonMenuContains('/index.php/monitor-nfse', $content, 'Common user sidebar should show NFSe panel link.');
assertCommonMenuNotContains('/index.php/usuarios', $content, 'Common user sidebar should not show user admin link.');
assertCommonMenuNotContains('/index.php/assinantes', $content, 'Common user sidebar should not show subscribers link.');
assertCommonMenuNotContains('/index.php/catalogo-programas', $content, 'Common user sidebar should not show program catalog operation link.');
assertCommonMenuNotContains('/index.php/consulta-requisicoes', $content, 'Common user sidebar should not show request lookup operation link.');
assertCommonMenuNotContains('/index.php/auditoria-requisicoes/visao-analitica', $content, 'Common user sidebar should not show audit operation link.');
assertCommonMenuNotContains('/index.php/demos', $content, 'Common user sidebar should not show super-admin demo link.');

fwrite(STDOUT, "OK\n");
