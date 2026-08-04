<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

function assertSameExecutorIniValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertThrowsExecutorIniValue(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (AcbrLegacyApiException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$requestStack = new RequestStack();
$requestStack->push(Request::create('/nfe/teste', 'POST', ['ACBrIniProfile' => 'perfil-post']));
$executor = new AcbrLegacyScriptExecutor($requestStack);
$method = new ReflectionMethod(AcbrLegacyScriptExecutor::class, 'resolveIniProfile');
$method->setAccessible(true);

assertSameExecutorIniValue('perfil-explicito', $method->invoke($executor, 'perfil-explicito'), 'Explicit class profile should have priority.');
assertSameExecutorIniValue('perfil-post', $method->invoke($executor, null), 'Request payload profile should be accepted when explicit profile is absent.');

$requestStack = new RequestStack();
$requestStack->push(Request::create('/nfe/teste?ACBrIniProfile=perfil-query', 'GET', [], [], [], ['HTTP_X_ACBR_INI_PROFILE' => 'perfil-header']));
$executor = new AcbrLegacyScriptExecutor($requestStack);
$method = new ReflectionMethod(AcbrLegacyScriptExecutor::class, 'resolveIniProfile');
$method->setAccessible(true);

assertSameExecutorIniValue('perfil-header', $method->invoke($executor, null), 'Header profile should have priority over query profile.');
assertThrowsExecutorIniValue(fn () => $method->invoke($executor, '../ACBrNFe.INI'), 'Executor should reject unsafe profile names.');

fwrite(STDOUT, "OK\n");
