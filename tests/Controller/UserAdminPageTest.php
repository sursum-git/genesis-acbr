<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

function assertUserAdminContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$kernel = new Kernel('test', true);
$kernel->boot();

$request = Request::create('/usuarios');
$request->attributes->set('_auth_user', [
    'id' => 1,
    'username' => 'admin_tela',
    'name' => 'Admin Tela',
    'type' => 'super_admin',
    'roles' => ['super_admin'],
    'subscriber_ids' => [],
    'subscriber_id' => null,
    'auth_type' => 'test',
]);

$response = $kernel->handle($request);
$content = (string) $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "User admin page should render for super admin. Status: " . $response->getStatusCode() . PHP_EOL);
    exit(1);
}

assertUserAdminContains('Usuários', $content, 'User admin page should show users title.');
assertUserAdminContains('Novo usuário', $content, 'User admin page should expose new user action.');
assertUserAdminContains('Empresas', $content, 'User admin page should expose company assignment controls.');
assertUserAdminContains('data-company-search', $content, 'User admin page should use searchable company controls.');
assertUserAdminContains('/index.php/usuarios/empresas/busca', $content, 'User admin page should expose the company search endpoint.');
assertUserAdminContains('/index.php/usuarios/empresas/salvar', $content, 'User list should expose company binding save action.');
assertUserAdminContains('/catalog-assets/admin/user-company-search.js', $content, 'User admin page should load company search asset.');
assertUserAdminContains('Admin Tela', $content, 'Admin header should show current user name.');
assertUserAdminContains('/index.php/logout', $content, 'Admin header should expose logout link.');
assertUserAdminContains('nav-item ms-4 ps-3 border-start', $content, 'Admin header should visually separate logout from API docs link.');
assertUserAdminContains('/index.php/usuarios', $content, 'Sidebar should link to user admin page.');

if (str_contains($content, 'name="empresa_ids[]" multiple')) {
    fwrite(STDERR, "User admin page should not render full company multi-selects.\n");
    exit(1);
}

$searchAsset = file_get_contents(dirname(__DIR__, 2) . '/catalog-assets/admin/user-company-search.js');
assertUserAdminContains('name: \'empresa_ids[]\'', (string) $searchAsset, 'Company search asset should submit selected company ids.');

$kernel->terminate($request, $response);
fwrite(STDOUT, "OK\n");
