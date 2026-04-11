<?php

namespace App\ApiResource\AcbrCep;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\AcbrCep\AcbrCepConfiguracaoProcessor;
use App\State\AcbrCep\AcbrCepConfiguracaoProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AcbrCepConfiguracao',
    operations: [
        new Get(
            uriTemplate: '/acbr-cep/configuracoes',
            provider: AcbrCepConfiguracaoProvider::class,
            openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['cep']]),
            normalizationContext: ['groups' => ['acbr_cep_config:read']],
        ),
        new Post(
            uriTemplate: '/acbr-cep/configuracoes',
            processor: AcbrCepConfiguracaoProcessor::class,
            openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['cep']]),
            denormalizationContext: ['groups' => ['acbr_cep_config:write']],
            normalizationContext: ['groups' => ['acbr_cep_config:read']],
        ),
    ]
)]
final class AcbrCepConfiguracaoResource
{
    public function __construct(
        #[Groups(['acbr_cep_config:read', 'acbr_cep_config:write'])]
        public ?string $usuario = '',
        #[Groups(['acbr_cep_config:read', 'acbr_cep_config:write'])]
        public ?string $senha = '',
        #[Groups(['acbr_cep_config:read', 'acbr_cep_config:write'])]
        public ?string $chaveAcesso = '',
        #[Groups(['acbr_cep_config:read', 'acbr_cep_config:write'])]
        public ?string $webservice = '0',
        #[Groups(['acbr_cep_config:read'])]
        public ?string $mensagem = null,
    ) {
    }
}
