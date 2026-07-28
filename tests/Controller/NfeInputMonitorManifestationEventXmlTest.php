<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Controller\NfeInputMonitorController;

function assertContainsManifestationText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = (new ReflectionClass(NfeInputMonitorController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(NfeInputMonitorController::class, 'buildManifestationEventXml');
$method->setAccessible(true);

$xml = $method->invoke($controller, [
    'chave_nfe' => '32260712345678000199550030006420801000000012',
    'cliente_documento' => '06013812000158',
    'ambiente' => '1',
], '210240', 'Mercadoria recusada no recebimento.', 1);

assertContainsManifestationText('<cOrgao>91</cOrgao>', (string) $xml, 'Recipient manifestation should use Ambiente Nacional cOrgao 91.');
assertContainsManifestationText('<CNPJ>06013812000158</CNPJ>', (string) $xml, 'Recipient manifestation should use recipient document.');
assertContainsManifestationText('<tpEvento>210240</tpEvento>', (string) $xml, 'Recipient manifestation should use selected event type.');
assertContainsManifestationText('<descEvento>Operacao nao Realizada</descEvento>', (string) $xml, 'Recipient manifestation should use ACBr event description.');
assertContainsManifestationText('<xJust>Mercadoria recusada no recebimento.</xJust>', (string) $xml, 'Operation not completed should include justification.');

$scienceXml = $method->invoke($controller, [
    'chave_nfe' => '32260712345678000199550030006420801000000012',
    'cliente_documento' => '06013812000158',
    'ambiente' => '1',
], '210210', '', 1);

if (str_contains((string) $scienceXml, '<xJust>')) {
    fwrite(STDERR, 'Science manifestation should not include xJust.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "OK\n");
