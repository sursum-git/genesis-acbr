<?php

require_once __DIR__ . '/ACBrCEPMT.php';
require_once dirname(__DIR__, 2) . '/ACBrComum/ACBrComum.php';

class ACBrCEPApiMT
{
    private string $nomeLib = 'ACBrCEP';
    private bool $inicializado = false;

    public function executar(string $metodo, array $dados): array
    {
        if (ValidaFFI() != 0) {
            throw new RuntimeException('FFI indisponivel.');
        }

        $dllPath = CarregaDll(__DIR__, $this->nomeLib);
        if ($dllPath == -10) {
            throw new RuntimeException('Falha ao carregar a DLL da ACBr.');
        }

        $importsPath = CarregaImports(__DIR__, $this->nomeLib, 'MT');
        if ($importsPath == -10) {
            throw new RuntimeException('Falha ao carregar os imports da ACBr.');
        }

        $iniPath = CarregaIniPath(__DIR__, $this->nomeLib);
        $ffi = CarregaContents($importsPath, $dllPath);
        $handle = FFI::new('uintptr_t');
        try {
            $this->inicializarBiblioteca($handle, $ffi, $iniPath);

            $responseData = $this->processarMetodo($metodo, $dados, $handle, $ffi);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            if ($this->inicializado && isset($ffi, $handle)) {
                $this->finalizarBiblioteca($handle, $ffi);
            }
        }

        return $responseData;
    }

    private function processarMetodo(string $metodo, array $dados, $handle, $ffi): array
    {
        if ($metodo === 'salvarConfiguracoes') {
            return $this->salvarConfiguracoes($dados, $handle, $ffi);
        }

        if ($metodo === 'carregarConfiguracoes') {
            return $this->carregarConfiguracoes($handle, $ffi);
        }

        if ($metodo === 'BuscarPorCEP') {
            return $this->buscarPorCep($dados, $handle, $ffi);
        }

        if ($metodo === 'BuscarPorLogradouro') {
            return $this->buscarPorLogradouro($dados, $handle, $ffi);
        }

        return ['mensagem' => 'Metodo nao suportado.'];
    }

    private function salvarConfiguracoes(array $dados, $handle, $ffi): array
    {
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'Usuario', $dados['usuario'] ?? '');
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'Senha', $dados['senha'] ?? '');
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'ChaveAcesso', $dados['chaveacesso'] ?? '');
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'WebService', $dados['webservice'] ?? '0');

        return ['mensagem' => 'Configurações salvas com sucesso.'];
    }

    private function carregarConfiguracoes($handle, $ffi): array
    {
        $usuario = $this->lerConfiguracao($handle, $ffi, 'CEP', 'Usuario');
        $senha = $this->lerConfiguracao($handle, $ffi, 'CEP', 'Senha');
        $chaveAcesso = $this->lerConfiguracao($handle, $ffi, 'CEP', 'ChaveAcesso');
        $webservice = $this->lerConfiguracao($handle, $ffi, 'CEP', 'WebService');

        return [
            'retorno' => '',
            'dados' => [
                'usuario' => $usuario,
                'senha' => $senha,
                'chaveacesso' => $chaveAcesso,
                'webservice' => $webservice !== '' ? $webservice : '0',
            ],
        ];
    }

    private function buscarPorCep(array $dados, $handle, $ffi): array
    {
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'WebService', $dados['webservice'] ?? '0');

        $iniContent = '';
        ob_start();
        $resultado = BuscarPorCEP($handle, $ffi, $dados['cepcons'] ?? '', $iniContent);
        $saida = trim(ob_get_clean() ?: '');
        if ($resultado != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, 'Falha ao consultar CEP.'));
        }

        return $this->montarRespostaConsulta($resultado, $iniContent);
    }

    private function buscarPorLogradouro(array $dados, $handle, $ffi): array
    {
        $this->gravarConfiguracao($handle, $ffi, 'CEP', 'WebService', $dados['webservice'] ?? '0');

        $iniContent = '';
        ob_start();
        $resultado = BuscarPorLogradouro(
            $handle,
            $ffi,
            $dados['cidadecons'] ?? '',
            $dados['tipocons'] ?? '',
            $dados['logradourocons'] ?? '',
            $dados['ufcons'] ?? '',
            $dados['bairrocons'] ?? '',
            $iniContent
        );
        $saida = trim(ob_get_clean() ?: '');

        if ($resultado != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, 'Falha ao consultar logradouro.'));
        }

        return $this->montarRespostaConsulta($resultado, $iniContent);
    }

    private function montarRespostaConsulta(int $resultado, string $iniContent): array
    {
        $parsedIni = parseIniToStr($iniContent);
        $secao = 'Endereco1';

        if (!isset($parsedIni[$secao])) {
            return [
                'retorno' => $resultado,
                'mensagem' => $iniContent,
                'dados' => '',
            ];
        }

        return [
            'retorno' => $resultado,
            'mensagem' => $iniContent,
            'dados' => [
                'bairro' => $parsedIni[$secao]['Bairro'] ?? '',
                'cep' => $parsedIni[$secao]['CEP'] ?? '',
                'complemento' => $parsedIni[$secao]['Complemento'] ?? '',
                'ibgemunicipio' => $parsedIni[$secao]['IBGE_Municipio'] ?? '',
                'ibgeuf' => $parsedIni[$secao]['IBGE_UF'] ?? '',
                'logradouro' => $parsedIni[$secao]['Logradouro'] ?? '',
                'municipio' => $parsedIni[$secao]['Municipio'] ?? '',
                'tipologradouro' => $parsedIni[$secao]['Tipo_Logradouro'] ?? '',
                'UF' => $parsedIni[$secao]['UF'] ?? '',
            ],
        ];
    }

    private function gravarConfiguracao($handle, $ffi, string $sessao, string $chave, string $valor): void
    {
        ob_start();
        $retorno = ConfigGravarValor($handle, $ffi, $sessao, $chave, $valor);
        $saida = trim(ob_get_clean() ?: '');

        if ($retorno != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, "Erro ao gravar configuracao {$sessao}.{$chave}."));
        }
    }

    private function lerConfiguracao($handle, $ffi, string $sessao, string $chave): string
    {
        $valor = '';
        ob_start();
        $retorno = ConfigLerValor($handle, $ffi, $sessao, $chave, $valor);
        $saida = trim(ob_get_clean() ?: '');

        if ($retorno != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, "Erro ao ler configuracao {$sessao}.{$chave}."));
        }

        return $valor;
    }

    private function extrairMensagem(string $saida, string $padrao): string
    {
        $json = json_decode($saida, true);

        if (is_array($json) && isset($json['mensagem']) && $json['mensagem'] !== '') {
            return $json['mensagem'];
        }

        return $padrao;
    }

    private function inicializarBiblioteca($handle, $ffi, string $iniPath): void
    {
        ob_start();
        $retorno = Inicializar($handle, $ffi, $iniPath);
        $saida = trim(ob_get_clean() ?: '');

        if ($retorno != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, 'Falha ao inicializar a biblioteca ACBr.'));
        }

        $this->inicializado = true;
    }

    private function finalizarBiblioteca($handle, $ffi): void
    {
        ob_start();
        $retorno = Finalizar($handle, $ffi);
        $saida = trim(ob_get_clean() ?: '');
        $this->inicializado = false;

        if ($retorno != 0) {
            throw new RuntimeException($this->extrairMensagem($saida, 'Falha ao finalizar a biblioteca ACBr.'));
        }
    }
}
