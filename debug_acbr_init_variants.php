<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/ACBrComum/ACBrComum.php';

function initCode(string $dir, string $nomeLib, string $modo, string $prefixo, string $iniPath): array
{
    $dllPath = CarregaDll($dir, $nomeLib);
    $importsPath = CarregaImports($dir, $nomeLib, $modo);
    $ffi = FFI::cdef(file_get_contents($importsPath), $dllPath);
    $handle = FFI::new('uintptr_t');
    $initMethod = $prefixo . '_Inicializar';
    $retorno = $ffi->$initMethod(FFI::addr($handle), $iniPath, '');

    if ($retorno === 0) {
        $finalizar = $prefixo . '_Finalizar';
        $ffi->$finalizar($handle->cdata);
    }

    return [
        'iniPath' => $iniPath,
        'exists' => $iniPath !== '' ? file_exists($iniPath) : false,
        'retorno' => $retorno,
    ];
}

$emptyIni = tempnam(sys_get_temp_dir(), 'acbr-empty-');
file_put_contents($emptyIni, '');

$nfeDir = __DIR__ . '/NFe/MT';
$nfseDir = __DIR__ . '/NFSe/MT';

$nfeIni = CarregaIniPath($nfeDir, 'ACBrNFe');
$nfseIni = CarregaIniPath($nfseDir, 'ACBrNFSe');

echo json_encode([
    'nfe' => [
        'original' => initCode($nfeDir, 'ACBrNFe', 'MT', 'NFE', $nfeIni),
        'emptyPath' => initCode($nfeDir, 'ACBrNFe', 'MT', 'NFE', ''),
        'emptyFile' => initCode($nfeDir, 'ACBrNFe', 'MT', 'NFE', $emptyIni),
    ],
    'nfse' => [
        'original' => initCode($nfseDir, 'ACBrNFSe', 'MT', 'NFSE', $nfseIni),
        'emptyPath' => initCode($nfseDir, 'ACBrNFSe', 'MT', 'NFSE', ''),
        'emptyFile' => initCode($nfseDir, 'ACBrNFSe', 'MT', 'NFSE', $emptyIni),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
