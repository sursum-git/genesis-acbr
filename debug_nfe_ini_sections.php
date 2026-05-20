<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/ACBrComum/ACBrComum.php';

function splitIniSections(string $contents): array
{
    preg_match_all('/^\[[^\]]+\][\s\S]*?(?=^\[[^\]]+\]|\z)/m', $contents, $matches);
    return $matches[0] ?? [];
}

function initWithIni(string $iniContent): int
{
    $dir = __DIR__ . '/NFe/MT';
    $dllPath = CarregaDll($dir, 'ACBrNFe');
    $importsPath = CarregaImports($dir, 'ACBrNFe', 'MT');
    $ffi = FFI::cdef(file_get_contents($importsPath), $dllPath);
    $handle = FFI::new('uintptr_t');
    $temp = tempnam(sys_get_temp_dir(), 'nfe-ini-');
    file_put_contents($temp, $iniContent);
    $retorno = $ffi->NFE_Inicializar(FFI::addr($handle), $temp, '');

    if ($retorno === 0) {
        $ffi->NFE_Finalizar($handle->cdata);
    }

    @unlink($temp);
    return $retorno;
}

$contents = file_get_contents(__DIR__ . '/NFe/MT/ACBrNFe.INI');
$sections = splitIniSections($contents);
$results = [];
$aggregate = '';

foreach ($sections as $section) {
    $aggregate .= $section . PHP_EOL;
    preg_match('/^\[([^\]]+)\]/m', $section, $match);
    $name = $match[1] ?? 'unknown';

    $results[] = [
        'section' => $name,
        'single' => initWithIni($section),
        'cumulative' => initWithIni($aggregate),
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
