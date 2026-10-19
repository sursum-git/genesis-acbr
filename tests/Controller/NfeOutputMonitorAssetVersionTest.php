<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel('test', true);
$request = Request::create('/monitor-saida-nfe');
$response = $kernel->handle($request);
$content = (string) $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Monitor page should render successfully.\n");
    exit(1);
}

if (!preg_match('#<script src="/catalog-assets/monitor/nfe-output-monitor\.js\?v=[^"&]+"></script>#', $content)) {
    fwrite(STDERR, "NFe output monitor JavaScript URL should carry a cache-busting version.\n");
    exit(1);
}

$kernel->terminate($request, $response);
fwrite(STDOUT, "OK\n");
