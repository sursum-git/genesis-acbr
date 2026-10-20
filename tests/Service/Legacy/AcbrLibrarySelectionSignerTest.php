<?php

declare(strict_types=1);

use App\Service\Legacy\AcbrLibraryReleaseSelection;
use App\Service\Legacy\AcbrLibrarySelectionSigner;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$signer = new AcbrLibrarySelectionSigner('test-secret');
$selection = new AcbrLibraryReleaseSelection(7, 'nfe', 'homologacao', 'homologacao', '1.5.0.444', '/opt/acbr_libs/releases/nfe-1.5.0.444/x64/libacbrnfe64.so', str_repeat('a', 64));
$token = $signer->sign($selection, 60, new DateTimeImmutable('2026-10-19 12:00:00'));
$payload = $signer->verify($token, 'nfe', new DateTimeImmutable('2026-10-19 12:00:30'));

if (($payload['id'] ?? null) !== 7 || ($payload['profile'] ?? null) !== 'homologacao') {
    fwrite(STDERR, "A assinatura deve preservar a seleção aprovada.\n");
    exit(1);
}

try {
    $signer->verify($token, 'nfse', new DateTimeImmutable('2026-10-19 12:00:30'));
    fwrite(STDERR, "A assinatura não pode ser aceita por outro módulo.\n");
    exit(1);
} catch (RuntimeException) {
}

try {
    $signer->verify($token, 'nfe', new DateTimeImmutable('2026-10-19 12:01:01'));
    fwrite(STDERR, "A assinatura expirada deve ser rejeitada.\n");
    exit(1);
} catch (RuntimeException) {
}

fwrite(STDOUT, "OK\n");
