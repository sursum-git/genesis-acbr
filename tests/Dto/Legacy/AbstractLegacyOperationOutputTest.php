<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Dto\Legacy\AbstractLegacyOperationOutput;

final class LegacyOperationOutputStub extends AbstractLegacyOperationOutput
{
}

function assertNullValue(mixed $value, string $message): void
{
    if ($value !== null) {
        fwrite(STDERR, $message . ' Expected null, got: ' . var_export($value, true) . PHP_EOL);
        exit(1);
    }
}

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

$mensagem = "Linha 1\nLinha 2";
$duplicado = new LegacyOperationOutputStub(
    resultado: ['mensagem' => $mensagem],
    mensagem: $mensagem,
);

assertNullValue(
    $duplicado->mensagem,
    'testOmitMensagemWhenResultadoAlreadyContainsSameMensagem failed.'
);

$distinto = new LegacyOperationOutputStub(
    resultado: ['mensagem' => 'Mensagem interna'],
    mensagem: 'Mensagem publica',
);

assertSameValue(
    'Mensagem publica',
    $distinto->mensagem,
    'testKeepMensagemWhenResultadoDoesNotContainSameMensagem failed.'
);

fwrite(STDOUT, "OK\n");
