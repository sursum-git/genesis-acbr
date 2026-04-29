<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Nfe\NfeEmailConfiguracaoResource;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class NfeEmailConfiguracaoProvider implements ProviderInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NfeEmailConfiguracaoResource
    {
        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');

        if ($script === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $resultado = $this->executor->execute($script, 'carregarConfiguracoesEmail');
        $dados = $resultado['dados'] ?? null;

        if (!is_array($dados)) {
            throw new AcbrLegacyApiException('Resposta inválida ao carregar configuracoes de e-mail da NFe.');
        }

        return new NfeEmailConfiguracaoResource(
            emailNome: (string) ($dados['emailNome'] ?? ''),
            emailConta: (string) ($dados['emailConta'] ?? ''),
            emailServidor: (string) ($dados['emailServidor'] ?? ''),
            emailPorta: (string) ($dados['emailPorta'] ?? ''),
            emailSSL: (string) ($dados['emailSSL'] ?? '0'),
            emailTLS: (string) ($dados['emailTLS'] ?? '0'),
            emailUsuario: (string) ($dados['emailUsuario'] ?? ''),
            emailSenha: (string) ($dados['emailSenha'] ?? ''),
            mensagem: isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null,
        );
    }
}
