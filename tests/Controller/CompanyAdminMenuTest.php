<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

function assertCompanyAdminMenuContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertCompanyAdminMenuNotContains(string $needle, string $haystack, string $message): void
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
    'id' => 3,
    'username' => 'admin_assinante',
    'name' => 'Admin Assinante',
    'type' => 'company_admin',
    'roles' => ['company_admin'],
    'subscriber_ids' => [10],
    'company_documents' => ['06013812000158'],
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

assertCompanyAdminMenuContains('Admin Assinante', $content, 'Header should show the current company admin.');
assertCompanyAdminMenuContains('/index.php/usuarios', $content, 'Company admin sidebar should show user admin link.');
assertCompanyAdminMenuContains('/index.php/monitor-saida-nfe', $content, 'Company admin sidebar should keep monitor links.');
assertCompanyAdminMenuContains('/index.php/monitor-nfse', $content, 'Company admin sidebar should show NFSe panel link.');
assertCompanyAdminMenuNotContains('/index.php/assinantes', $content, 'Company admin sidebar should not show subscribers link.');
assertCompanyAdminMenuNotContains('/index.php/catalogo-programas', $content, 'Company admin sidebar should not show operation admin links.');

fwrite(STDOUT, "OK\n");
