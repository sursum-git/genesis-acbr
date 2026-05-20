<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/ACBrComum/ACBrComum.php';

function ultimoRetornoGenerico($ffi, string $prefixo, $handle): string
{
    $buffer = FFI::new('char[9048]');
    $size = FFI::new('long');
    $size->cdata = 9048;

    $method = $prefixo . '_UltimoRetorno';
    $ffi->$method($handle->cdata, $buffer, FFI::addr($size));

    return FFI::string($buffer);
}

function debugInit(string $dir, string $nomeLib, string $modo, string $prefixo): array
{
    $dllPath = CarregaDll($dir, $nomeLib);
    $importsPath = CarregaImports($dir, $nomeLib, $modo);
    $iniPath = CarregaIniPath($dir, $nomeLib);
    $ffi = FFI::cdef(file_get_contents($importsPath), $dllPath);
    $handle = FFI::new('uintptr_t');

    $initMethod = $prefixo . '_Inicializar';
    $retorno = $ffi->$initMethod(FFI::addr($handle), $iniPath, '');
    $ultimoRetorno = '';

    if ($retorno !== 0) {
        $ultimoRetorno = ultimoRetornoGenerico($ffi, $prefixo, $handle);
    } else {
        $finalizar = $prefixo . '_Finalizar';
        $ffi->$finalizar($handle->cdata);
    }

    return [
        'dllPath' => $dllPath,
        'iniPath' => $iniPath,
        'iniExists' => file_exists($iniPath),
        'retorno' => $retorno,
        'ultimoRetorno' => $ultimoRetorno,
    ];
}

echo json_encode([
    'nfe' => debugInit(__DIR__ . '/NFe/MT', 'ACBrNFe', 'MT', 'NFE'),
    'nfse' => debugInit(__DIR__ . '/NFSe/MT', 'ACBrNFSe', 'MT', 'NFSE'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
