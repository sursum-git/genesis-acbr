<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Service\Nfe\NfeAuthorizedArtifactLocator;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL
        );
        exit(1);
    }
}

function assertNullValue(mixed $actual, string $message): void
{
    if ($actual !== null) {
        fwrite(STDERR, $message . ' Expected null, got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$baseDir = sys_get_temp_dir() . '/codex-nfe-artifacts-' . bin2hex(random_bytes(4));
@mkdir($baseDir . '/12345678000199/NFe/202606/NFe', 0777, true);
$xmlPath = $baseDir . '/12345678000199/NFe/202606/NFe/32260406013812000158550030001955901308939122-nfe.xml';
file_put_contents($xmlPath, '<xml />');

$locator = new NfeAuthorizedArtifactLocator($baseDir);

$key = $locator->extractAccessKeyFromResult([
    'mensagem' => "[Envio]\nCStat=150\n[NFe1]\nchDFe=32260406013812000158550030001955901308939122\n",
]);

assertSameValue('32260406013812000158550030001955901308939122', $key, 'locator should extract the access key from the retorno principal.');
assertSameValue($xmlPath, $locator->locateAuthorizedXmlPath($key), 'locator should find the authorized XML path by chave.');

$pdfPath = $locator->extractPdfPath("Arquivo salvo em {$baseDir}/danfe/teste.pdf");
assertSameValue("{$baseDir}/danfe/teste.pdf", $pdfPath, 'locator should extract the PDF path from legacy output.');

assertNullValue($locator->extractPdfPath('sem caminho de arquivo'), 'locator should return null when no PDF path is present.');

@unlink($xmlPath);
@rmdir(dirname($xmlPath));
@rmdir(dirname(dirname($xmlPath)));
@rmdir(dirname(dirname(dirname($xmlPath))));
@rmdir($baseDir);

fwrite(STDOUT, "OK\n");
