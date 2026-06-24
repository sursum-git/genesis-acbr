<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Service\Api\ApiFunctionalSuccessMatcher;

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

$matcher = new ApiFunctionalSuccessMatcher();

$matched = $matcher->match(
    200,
    [
        'mensagem' => "[Envio]\nCStat=150\nXMotivo=Autorizado o uso da NF-e\n[NFe1]\nchDFe=32260406013812000158550030001955901308939122\n",
    ],
    '200,201',
    '150'
);

assertSameValue('150', $matched['c_stat_receita'] ?? null, 'match should return cStat when HTTP and Receita codes match.');

$statusMiss = $matcher->match(
    500,
    ['mensagem' => "[Envio]\nCStat=150\n"],
    '200,201',
    '150'
);

assertNullValue($statusMiss, 'match should fail when HTTP code is not allowed.');

$receitaMiss = $matcher->match(
    200,
    ['mensagem' => "[Envio]\nCStat=100\n"],
    '200,201',
    '150'
);

assertNullValue($receitaMiss, 'match should fail when Receita code is not allowed.');

fwrite(STDOUT, "OK\n");
