<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/NFe/MT/ACBrNFeMT.php';
final class InitializationProbe {
    public array $paths = [];
    public array $readPaths = [];
    public int $finalizations = 0;
    public function __construct(private int $readCode = 0) {}
    public function NFE_Inicializar(mixed $handle, string $path, string $crypt): int {
        $this->paths[] = $path;
        return $path === '[Memory]' ? 0 : -3;
    }
    public function NFE_ConfigLer(mixed $handle, string $path): int { $this->readPaths[] = $path; return $this->readCode; }
    public function NFE_Finalizar(mixed $handle): int { ++$this->finalizations; return 0; }
}
function expectInit(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
}
$p = tempnam(sys_get_temp_dir(), 'acbr-init-test-');
try {
    $probe = new InitializationProbe(); $handle = FFI::new('uintptr_t');
    ob_start(); $code = Inicializar($handle, $probe, $p); $output = ob_get_clean();
    expectInit(0, $code, 'should initialize in memory and then read the requested INI');
    expectInit([$p, '[Memory]'], $probe->paths, 'fallback must not silently use a different profile');
    expectInit([$p], $probe->readPaths, 'fallback must read exactly the requested profile');
    expectInit('', $output, 'successful fallback should not emit an error');
    $probe = new InitializationProbe(-6); $handle = FFI::new('uintptr_t');
    ob_start(); $code = Inicializar($handle, $probe, $p); ob_end_clean();
    expectInit(-10, $code, 'failed config read must fail closed');
    expectInit(1, $probe->finalizations, 'failed fallback must release the native handle');
    $probe = new InitializationProbe(); $handle = FFI::new('uintptr_t');
    ob_start(); $code = Inicializar($handle, $probe, $p . '.missing'); ob_end_clean();
    expectInit(-10, $code, 'missing profile must not initialize with defaults');
    expectInit([], $probe->readPaths, 'missing profile must not be loaded');
} finally { unlink($p); }
echo "OK\n";
