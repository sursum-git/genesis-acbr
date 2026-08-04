<?php
/* {******************************************************************************}
// { Projeto: Componentes ACBr                                                    }
// {  Biblioteca multiplataforma de componentes Delphi para interação com equipa- }
// { mentos de Automação Comercial utilizados no Brasil                           }
// {                                                                              }
// { Direitos Autorais Reservados (c) 2022 Daniel Simoes de Almeida               }
// {                                                                              }
// { Colaboradores nesse arquivo: Renato Rubinho                                  }
// {                                                                              }
// {  Você pode obter a última versão desse arquivo na pagina do  Projeto ACBr    }
// { Componentes localizado em      http://www.sourceforge.net/projects/acbr      }
// {                                                                              }
// {  Esta biblioteca é software livre; você pode redistribuí-la e/ou modificá-la }
// { sob os termos da Licença Pública Geral Menor do GNU conforme publicada pela  }
// { Free Software Foundation; tanto a versão 2.1 da Licença, ou (a seu critério) }
// { qualquer versão posterior.                                                   }
// {                                                                              }
// {  Esta biblioteca é distribuída na expectativa de que seja útil, porém, SEM   }
// { NENHUMA GARANTIA; nem mesmo a garantia implícita de COMERCIABILIDADE OU      }
// { ADEQUAÇÃO A UMA FINALIDADE ESPECÍFICA. Consulte a Licença Pública Geral Menor}
// { do GNU para mais detalhes. (Arquivo LICENÇA.TXT ou LICENSE.TXT)              }
// {                                                                              }
// {  Você deve ter recebido uma cópia da Licença Pública Geral Menor do GNU junto}
// { com esta biblioteca; se não, escreva para a Free Software Foundation, Inc.,  }
// { no endereço 59 Temple Street, Suite 330, Boston, MA 02111-1307 USA.          }
// { Você também pode obter uma copia da licença em:                              }
// { http://www.opensource.org/licenses/lgpl-license.php                          }
// {                                                                              }
// { Daniel Simões de Almeida - daniel@projetoacbr.com.br - www.projetoacbr.com.br}
// {       Rua Coronel Aureliano de Camargo, 963 - Tatuí - SP - 18270-170         }
// {******************************************************************************}
*/
header('Content-Type: application/json; charset=UTF-8');

const ACBR_SHARED_LIB_BASE_PATH = '/opt/acbr_libs';

function ValidaFFI()
{
    if (!extension_loaded('ffi')) {
        echo json_encode(["mensagem" => "A extensão FFI não está habilitada."]);
        return -10;
    }

    return 0;
}

function CarregaDll($dir, $nomeLib)
{
    if (strpos(PHP_OS, 'WIN') === false) {
        $prefixo = strtolower("lib" . $nomeLib);
        $extensao = ".so";
    } else {
        $prefixo = strtolower("lib" . $nomeLib);
        $prefixo = $nomeLib;
        $extensao = ".dll";
    }

    if (strpos(php_uname('m'), '64') === false)
        $arquitetura = "86";
    else
        $arquitetura = "64";

    $biblioteca = $prefixo . $arquitetura . $extensao;

    $dllPaths = [];
    $dllPath = $dir . DIRECTORY_SEPARATOR;

    if (strpos(PHP_OS, 'WIN') === false) {
        $dllPaths[] = rtrim(ACBR_SHARED_LIB_BASE_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . "x"
            . $arquitetura
            . DIRECTORY_SEPARATOR;
    }

    $dllPaths[] = $dllPath;
    $dllPaths[] = $dir . DIRECTORY_SEPARATOR . "ACBrLib" . DIRECTORY_SEPARATOR . "x" . $arquitetura . DIRECTORY_SEPARATOR;

    foreach ($dllPaths as $candidatePath) {
        if (file_exists($candidatePath . $biblioteca)) {
            $dllPath = $candidatePath;
            break;
        }
    }

    if (!file_exists($dllPath . $biblioteca)) {
        if (strpos(PHP_OS, 'WIN') === false)
            $dllPath = "";
        else{
            echo json_encode(["mensagem" => "Biblioteca (.dll/.so) não encontrada no caminho especificado: " . $dllPath . $biblioteca]);
            return -10;
        }
    }

    if (!empty($dir)) {
        $pathAtual = getenv('PATH');
        if (strpos($pathAtual, $dllPath) === false) {
            putenv("PATH=$dllPath;" . $pathAtual);
        }    
    }
    
    return $dllPath . $biblioteca;
}

function CarregaImports($dir, $nomeLib, $modo)
{
    $importsPath = $dir . DIRECTORY_SEPARATOR . $nomeLib . $modo . '.h';

    if (!file_exists($importsPath)) {
        echo json_encode(["mensagem" => "Imports não encontrados no caminho especificado: $importsPath"]);
        return -10;
    }

    return $importsPath;
}

function CarregaIniPath($dir, $nomeLib)
{
    return $dir . DIRECTORY_SEPARATOR . $nomeLib . ".INI";
}

function AcbrIniProfilesDir($dir)
{
    return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'configs';
}

function AcbrIniActiveProfileFile($dir)
{
    return AcbrIniProfilesDir($dir) . DIRECTORY_SEPARATOR . 'active-profile.json';
}

function AcbrIniNormalizeProfile($profile)
{
    if ($profile === null) {
        return null;
    }

    $profile = trim((string) $profile);
    if ($profile === '') {
        return null;
    }

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $profile)) {
        throw new InvalidArgumentException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
    }

    if (strpos($profile, '..') !== false || strpos($profile, '/') !== false || strpos($profile, '\\') !== false) {
        throw new InvalidArgumentException('Perfil INI invalido. Caminhos nao sao permitidos.');
    }

    return $profile;
}

function AcbrIniProfilePath($dir, $nomeLib, $profile)
{
    return AcbrIniProfilesDir($dir) . DIRECTORY_SEPARATOR . $profile . DIRECTORY_SEPARATOR . $nomeLib . '.INI';
}

function AcbrIniReadActiveProfile($dir, $activeKey = 'active')
{
    $activeFile = AcbrIniActiveProfileFile($dir);
    if (!is_file($activeFile)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($activeFile), true);
    if (!is_array($data)) {
        return null;
    }

    $profile = $data[$activeKey] ?? $data['active'] ?? null;
    return is_string($profile) ? AcbrIniNormalizeProfile($profile) : null;
}

function ResolveAcbrIniPath($dir, $nomeLib, $requestedProfile = null, $activeKey = 'active')
{
    $profile = AcbrIniNormalizeProfile($requestedProfile);

    if ($profile === null) {
        $profile = AcbrIniReadActiveProfile($dir, $activeKey);
    }

    if ($profile === null) {
        return CarregaIniPath($dir, $nomeLib);
    }

    $path = AcbrIniProfilePath($dir, $nomeLib, $profile);
    if (!is_file($path)) {
        throw new RuntimeException("Perfil INI nao encontrado: {$profile}");
    }

    return $path;
}

function ListaAcbrIniProfiles($dir, $nomeLib, $activeKey = 'active')
{
    $profilesDir = AcbrIniProfilesDir($dir);
    if (!is_dir($profilesDir)) {
        return [];
    }

    $activeProfile = AcbrIniReadActiveProfile($dir, $activeKey);
    $profiles = [];

    foreach ((scandir($profilesDir) ?: []) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        try {
            $profile = AcbrIniNormalizeProfile($entry);
        } catch (Throwable) {
            continue;
        }

        if ($profile !== $entry) {
            continue;
        }

        $path = AcbrIniProfilePath($dir, $nomeLib, $profile);
        if (!is_file($path)) {
            continue;
        }

        $profiles[] = [
            'id' => $profile,
            'active' => $activeProfile === $profile,
            'path' => $path,
        ];
    }

    usort($profiles, static function ($a, $b) {
        return $a['id'] <=> $b['id'];
    });

    return $profiles;
}

function CriaAcbrIniProfile($dir, $nomeLib, $profile, $sourceProfile = null, $activeKey = 'active')
{
    $profile = AcbrIniNormalizeProfile($profile);
    if ($profile === null) {
        throw new InvalidArgumentException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
    }

    $targetPath = AcbrIniProfilePath($dir, $nomeLib, $profile);
    if (file_exists($targetPath)) {
        throw new RuntimeException("Perfil INI ja existe: {$profile}");
    }

    $sourceProfile = AcbrIniNormalizeProfile($sourceProfile);
    $sourcePath = $sourceProfile === null ? CarregaIniPath($dir, $nomeLib) : ResolveAcbrIniPath($dir, $nomeLib, $sourceProfile, $activeKey);
    if (!is_file($sourcePath)) {
        throw new RuntimeException("Arquivo INI padrao nao encontrado: {$sourcePath}");
    }

    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException("Nao foi possivel criar a pasta do perfil INI: {$targetDir}");
    }

    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException("Nao foi possivel criar o perfil INI: {$profile}");
    }

    return $targetPath;
}

function SelecionaAcbrIniProfile($dir, $nomeLib, $profile, $activeKey = 'active')
{
    $profile = AcbrIniNormalizeProfile($profile);
    if ($profile === null) {
        throw new InvalidArgumentException('Perfil INI invalido. Use apenas letras, numeros, ponto, hifen ou underline.');
    }

    ResolveAcbrIniPath($dir, $nomeLib, $profile, $activeKey);

    $activeFile = AcbrIniActiveProfileFile($dir);
    $activeDir = dirname($activeFile);
    if (!is_dir($activeDir) && !mkdir($activeDir, 0777, true) && !is_dir($activeDir)) {
        throw new RuntimeException("Nao foi possivel criar a pasta de perfis INI: {$activeDir}");
    }

    $data = is_file($activeFile) ? json_decode((string) file_get_contents($activeFile), true) : [];
    if (!is_array($data)) {
        $data = [];
    }

    $data[$activeKey] = $profile;
    $tmpFile = $activeFile . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmpFile, $json . PHP_EOL, LOCK_EX) === false || !rename($tmpFile, $activeFile)) {
        @unlink($tmpFile);
        throw new RuntimeException('Nao foi possivel gravar o perfil INI ativo.');
    }
}

function CarregaContents($importsPath, $dllPath)
{
    $modoGrafico = verificaAmbienteGrafico();

    if ($modoGrafico === 2){
        putenv("DISPLAY=:99");
    }

    try {
        $ffi = FFI::cdef(
            file_get_contents($importsPath),
            $dllPath
        );
    } catch (Throwable $e) {
        if (strpos(PHP_OS, 'WIN') === false){
            $erro = ", verifique se o arquivo foi salvo no caminho padrão da sua distribuição";
        }
        else {
            $erro = "";
        }

        $erro = "Erro ao carregar a biblioteca$erro: " . $e->getMessage();
        die($erro);
    }   

    return $ffi;
}

function VerificaXmlOuIni($conteudo)
{
    if (!is_string($conteudo)) {
        return -1;
    }

    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
    $conteudo = ltrim($conteudo);
    $conteudo = preg_replace('/^(?:;[^\r\n]*[\r\n]+)+/', '', $conteudo) ?? $conteudo;
    $conteudo = ltrim($conteudo);

    // 0=Ini
    if (preg_match('/^\[[^\]]+\]/', $conteudo)) {
        return 0;
    }

    // 1=Xml
    if (preg_match('/^<\?xml|<\w+>/', $conteudo)) {
        return 1;
    }

    // -1=Nenhum
    return -1;
}

function formataDataAMD($dateString){
    if (preg_match('/^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $dateString)) {
        $dateString = str_replace('-', '/', $dateString);
        
        $date = DateTime::createFromFormat('d/m/Y', $dateString);
        return $date->format('Y/m/d');
    } else {
        return str_replace('-', '/', $dateString);
    }
}

function strDateToDoubleDate($dateString) {
    $baseDate = new DateTime('1899/12/30');
    $inputDate = new DateTime(formataDataAMD($dateString));

    // Diferença de dias entre a base e a data fornecida
    $daysDiff = $inputDate->diff($baseDate)->days;

    // Retorna o valor em formato double
    return $daysDiff;
}

function strDateTimeToDoubleDateTime($dateString) {
    $baseDate = new DateTime('1899/12/30');
    $inputDate = new DateTime(formataDataAMD($dateString));

    // Diferença de dias entre a base e a data fornecida
    $daysDiff = $inputDate->diff($baseDate)->days;

    // Fração de um dia (horas, minutos, segundos)
    $fraction = ($inputDate->format('H') / 24) +
        ($inputDate->format('i') / 1440) +
        ($inputDate->format('s') / 86400);

    // Retorna o valor em formato double
    return $daysDiff + $fraction;
}

function parseIniToStr($ini)
{
    $lines = explode( PHP_EOL, $ini );
    $config = [];
    $section = null;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === ';') {
            continue;
        }

        if ($line[0] === '[' && $line[-1] === ']') {
            $section = substr($line, 1, -1);
            $config[$section] = [];
        } else {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($section) {
                $config[$section][$key] = $value;
            } else {
                $config[$key] = $value;
            }
        }
    }

    return $config;
}

function verificaAmbienteGrafico()
{
    $verificaXVFB = shell_exec('pgrep Xvfb 2>&1') !== null;
    $displayXVFB = strpos(getenv('DISPLAY'), ':99') !== false;

    if ($verificaXVFB || $displayXVFB) {
        // Emulador XVFB    
        return 2;
    } else {
        $verificaX11 = shell_exec('pgrep Xorg') !== null && trim(shell_exec('pgrep Xorg')) !== '';
        $displayX11 = getenv('DISPLAY') !== false && trim(getenv('DISPLAY')) !== '';
        $pacoteX11 = shell_exec('dpkg -l | grep xserver-xorg') !== null && trim(shell_exec('dpkg -l | grep xserver-xorg')) !== '';

        if ($verificaX11 || $displayX11 || $pacoteX11) {
            // Ambiente grafico X11
            return 1;
        } else
            // Sem ambiente grafico
            return 0;
    }
}
