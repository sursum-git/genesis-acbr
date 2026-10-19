<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

function assertUserEditTemplateContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertUserEditTemplateNotContains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$kernel = new Kernel('test', true);
$kernel->boot();

$request = Request::create('/usuarios');
$request->attributes->set('_route', 'app_users');
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

$content = $twig->render('admin/users.html.twig', [
    'users' => [[
        'id_t00005' => 10,
        'c_usuario' => 'operador',
        'c_nome' => 'Operador',
        'c_tipo' => 'common',
        'log_ativo' => true,
    ]],
    'userCompanies' => [10 => []],
    'loadError' => null,
    'types' => [
        'common' => 'Comum',
        'company_admin' => 'Admin do assinante',
    ],
]);

assertUserEditTemplateContains('/index.php/usuarios/atualizar', $content, 'User page should expose the user update action.');
assertUserEditTemplateContains('Atualizar usuário', $content, 'User page should expose an edit submit button.');
assertUserEditTemplateContains('name="senha"', $content, 'User edit form should expose optional password update.');
assertUserEditTemplateContains('data-company-form="user-update-10"', $content, 'User edit company search should bind dynamic company inputs to the update form.');
assertUserEditTemplateNotContains('value="super_admin"', $content, 'Company admin user page should not expose super admin as a selectable type.');

fwrite(STDOUT, "OK\n");
