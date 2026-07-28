<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Controller\NfeOutputMonitorController;

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = (new ReflectionClass(NfeOutputMonitorController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(NfeOutputMonitorController::class, 'buildCorrectionEventXml');
$method->setAccessible(true);

$xml = $method->invoke($controller, [
    'chave_nfe' => '32260706013812000158550030001972521191972527',
    'emitente_documento' => '06013812000158',
    'ambiente' => '2',
], 'Corrige texto das informacoes adicionais da NF-e.', 1);

assertContainsText(
    'A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, de 15 de dezembro de 1970 e pode ser utilizada para regularizacao de erro ocorrido na emissao de documento fiscal',
    (string) $xml,
    'CC-e xCondUso should match the schema enumeration accepted by ACBr.'
);
assertContainsText('<tpEvento>110110</tpEvento>', (string) $xml, 'CC-e XML should use event type 110110.');

fwrite(STDOUT, "OK\n");
