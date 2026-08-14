<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel('test', true);
$request = Request::create('/login');
$response = $kernel->handle($request);
$content = (string) $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Login page should render directly.\n");
    exit(1);
}

if (str_contains($content, 'app-sidebar') || str_contains($content, 'sidebar-menu')) {
    fwrite(STDERR, "Login page should not render the administrative sidebar.\n");
    exit(1);
}

if (!str_contains($content, 'name="usuario"') || !str_contains($content, 'name="senha"')) {
    fwrite(STDERR, "Login page should render username and password fields.\n");
    exit(1);
}

if (!str_contains($content, 'window.top !== window.self')) {
    fwrite(STDERR, "Login page should force itself out of embedded frames.\n");
    exit(1);
}

$kernel->terminate($request, $response);
fwrite(STDOUT, "OK\n");
