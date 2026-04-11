<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/ACBrComum/ACBrComum.php';

function debugModulo(string $dir, string $nomeLib, string $modo): array
{
    $dllPath = CarregaDll($dir, $nomeLib);
    $importsPath = CarregaImports($dir, $nomeLib, $modo);
    $iniPath = CarregaIniPath($dir, $nomeLib);

    $ffiLoad = 'not-tested';

    if ($importsPath !== -10) {
        try {
            FFI::cdef(file_get_contents($importsPath), $dllPath);
            $ffiLoad = 'ok';
        } catch (Throwable $e) {
            $ffiLoad = $e->getMessage();
        }
    }

    return [
        'dir' => $dir,
        'dllPath' => $dllPath,
        'dllExists' => is_string($dllPath) && $dllPath !== '' ? file_exists($dllPath) : null,
        'importsPath' => $importsPath,
        'importsExists' => is_string($importsPath) ? file_exists($importsPath) : false,
        'iniPath' => $iniPath,
        'iniExists' => file_exists($iniPath),
        'ffiLoad' => $ffiLoad,
    ];
}

echo json_encode([
    'phpVersion' => PHP_VERSION,
    'phpBinary' => PHP_BINARY,
    'os' => PHP_OS,
    'arch' => php_uname('m'),
    'path' => getenv('PATH'),
    'cep' => debugModulo(__DIR__ . '/ConsultaCEP/MT', 'ACBrCEP', 'MT'),
    'nfe' => debugModulo(__DIR__ . '/NFe/MT', 'ACBrNFe', 'MT'),
    'nfse' => debugModulo(__DIR__ . '/NFSe/MT', 'ACBrNFSe', 'MT'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
