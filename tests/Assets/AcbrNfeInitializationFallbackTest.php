<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/NFe/MT/ACBrNFeMT.php';
final class InitializationProbe {
    public array $paths = [];
    public function __construct(private int $initCode = -3, private string $environment = '1') {}
    public function NFE_Inicializar(mixed $handle, string $path, string $crypt): int { $this->paths[] = $path; return $this->initCode; }
    public function NFE_ConfigLerValor(mixed $handle, string $section, string $key, mixed $buffer, mixed $size): int {
        $buffer[0] = $this->environment; $buffer[1] = "\0"; return 0;
    }
}
function expectInit(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
}
$p = tempnam(sys_get_temp_dir(), 'acbr-init-test-');
try {
    $probe = new InitializationProbe(-3); $handle = FFI::new('uintptr_t');
    ob_start(); $code = Inicializar($handle, $probe, $p); ob_end_clean();
    expectInit(-10, $code, 'invalid INI must fail; memory defaults must never hide an invalid profile');
    expectInit([$p], $probe->paths, 'must not fall back to an unconfigured memory instance');
    $probe = new InitializationProbe(0); $handle = FFI::new('uintptr_t');
    ob_start(); $code = Inicializar($handle, $probe, $p); ob_end_clean();
    expectInit(0, $code, 'valid INI should initialize normally');
    ob_start(); $code = ValidarAmbienteNFe($handle, $probe, 'homologacao'); ob_end_clean();
    expectInit(0, $code, 'native INI environment 1 means homologacao');
    $probe = new InitializationProbe(0, '0');
    ob_start(); $code = ValidarAmbienteNFe($handle, $probe, 'homologacao'); ob_end_clean();
    expectInit(-10, $code, 'native production environment must be rejected for a homologacao selection');
    ob_start(); $code = ValidarAmbienteNFe($handle, $probe, 'producao'); ob_end_clean();
    expectInit(0, $code, 'native INI environment 0 means producao');
} finally { unlink($p); }
echo "OK\n";
