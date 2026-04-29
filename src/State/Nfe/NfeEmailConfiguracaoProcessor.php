<?php

namespace App\State\Nfe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Nfe\NfeEmailConfiguracaoResource;
use App\Http\Exception\AcbrLegacyApiException;
use App\Service\Legacy\AcbrLegacyScriptExecutor;

final class NfeEmailConfiguracaoProcessor implements ProcessorInterface
{
    public function __construct(private readonly AcbrLegacyScriptExecutor $executor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NfeEmailConfiguracaoResource
    {
        if (!$data instanceof NfeEmailConfiguracaoResource) {
            throw new AcbrLegacyApiException('Payload inválido para configuracao de e-mail da NFe.');
        }

        $extraProperties = $operation->getExtraProperties();
        $script = (string) ($extraProperties['acbr_script'] ?? '');

        if ($script === '') {
            throw new AcbrLegacyApiException('Operação API Platform sem metadados do legado ACBr.');
        }

        $resultado = $this->executor->execute($script, 'salvarConfiguracoesEmail', [
            'emailNome' => (string) ($data->emailNome ?? ''),
            'emailConta' => (string) ($data->emailConta ?? ''),
            'emailServidor' => (string) ($data->emailServidor ?? ''),
            'emailPorta' => (string) ($data->emailPorta ?? ''),
            'emailSSL' => (string) ($data->emailSSL ?? '0'),
            'emailTLS' => (string) ($data->emailTLS ?? '0'),
            'emailUsuario' => (string) ($data->emailUsuario ?? ''),
            'emailSenha' => (string) ($data->emailSenha ?? ''),
        ]);

        return new NfeEmailConfiguracaoResource(
            emailNome: (string) ($data->emailNome ?? ''),
            emailConta: (string) ($data->emailConta ?? ''),
            emailServidor: (string) ($data->emailServidor ?? ''),
            emailPorta: (string) ($data->emailPorta ?? ''),
            emailSSL: (string) ($data->emailSSL ?? '0'),
            emailTLS: (string) ($data->emailTLS ?? '0'),
            emailUsuario: (string) ($data->emailUsuario ?? ''),
            emailSenha: (string) ($data->emailSenha ?? ''),
            mensagem: isset($resultado['mensagem']) ? (string) $resultado['mensagem'] : null,
        );
    }
}
