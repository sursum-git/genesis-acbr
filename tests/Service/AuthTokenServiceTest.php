<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Service\Auth\AuthTokenService;

function assertTokenTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assertTokenSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$service = new AuthTokenService('test-secret-key', 900);
$token = $service->issueAccessToken(42, ['common'], 7);
$claims = $service->verifyAccessToken($token);

assertTokenSame(42, (int) $claims['sub'], 'JWT should carry user id.');
assertTokenSame(7, (int) $claims['sid'], 'JWT should carry selected subscriber id.');
assertTokenSame(['common'], $claims['roles'], 'JWT should carry user roles.');
assertTokenTrue($service->verifyAccessToken($token . 'x') === null, 'tampered JWT should be rejected.');

$refresh = $service->issueRefreshToken();
assertTokenTrue(str_starts_with($refresh['plain'], 'rft_'), 'refresh token should use an opaque prefix.');
assertTokenSame(hash('sha256', $refresh['plain']), $refresh['hash'], 'refresh token should be stored hashed.');

fwrite(STDOUT, "OK\n");
