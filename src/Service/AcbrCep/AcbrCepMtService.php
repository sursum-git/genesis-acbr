<?php

namespace App\Service\AcbrCep;

use App\Dto\AcbrCep\AcbrCepConfiguracao;
use App\Dto\AcbrCep\AcbrCepConsultaCepInput;
use App\Dto\AcbrCep\AcbrCepConsultaLogradouroInput;
use App\Dto\AcbrCep\AcbrCepConsultaResultado;
use App\Http\Exception\AcbrCepException;
use Throwable;

require_once dirname(__DIR__, 3) . '/ConsultaCEP/MT/ACBrCEPApiMT.php';

final class AcbrCepMtService
{
    private \ACBrCEPApiMT $legacyService;

    public function __construct(?\ACBrCEPApiMT $legacyService = null)
    {
        $this->legacyService = $legacyService ?? new \ACBrCEPApiMT();
    }

    public function salvarConfiguracoes(AcbrCepConfiguracao $configuracao): string
    {
        try {
            $response = $this->legacyService->executar('salvarConfiguracoes', $configuracao->toLegacyPayload());
        } catch (Throwable $e) {
            throw new AcbrCepException($e->getMessage(), 0, $e);
        }

        return (string) ($response['mensagem'] ?? '');
    }

    public function carregarConfiguracoes(): AcbrCepConfiguracao
    {
        try {
            $response = $this->legacyService->executar('carregarConfiguracoes', []);
        } catch (Throwable $e) {
            throw new AcbrCepException($e->getMessage(), 0, $e);
        }

        $dados = $response['dados'] ?? null;

        if (!is_array($dados)) {
            throw new AcbrCepException('Resposta invalida ao carregar configuracoes do ACBrCEP.');
        }

        return AcbrCepConfiguracao::fromLegacyPayload($dados);
    }

    public function buscarPorCep(AcbrCepConsultaCepInput $input): AcbrCepConsultaResultado
    {
        try {
            $response = $this->legacyService->executar('BuscarPorCEP', $input->toLegacyPayload());
        } catch (Throwable $e) {
            throw new AcbrCepException($e->getMessage(), 0, $e);
        }

        return AcbrCepConsultaResultado::fromLegacyPayload($response);
    }

    public function buscarPorLogradouro(AcbrCepConsultaLogradouroInput $input): AcbrCepConsultaResultado
    {
        try {
            $response = $this->legacyService->executar('BuscarPorLogradouro', $input->toLegacyPayload());
        } catch (Throwable $e) {
            throw new AcbrCepException($e->getMessage(), 0, $e);
        }

        return AcbrCepConsultaResultado::fromLegacyPayload($response);
    }
}
