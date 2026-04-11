<?php

namespace App\ApiResource\AcbrCep;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\AcbrCep\AcbrCepConsultaCepProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AcbrCepConsultaCep',
    operations: [
        new Post(
            uriTemplate: '/acbr-cep/consulta-cep',
            processor: AcbrCepConsultaCepProcessor::class,
            openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['cep']]),
            denormalizationContext: ['groups' => ['acbr_cep_consulta_cep:write']],
            normalizationContext: ['groups' => ['acbr_cep_consulta_cep:read']],
        ),
    ]
)]
final class AcbrCepConsultaCepResource
{
    public function __construct(
        #[Groups(['acbr_cep_consulta_cep:write', 'acbr_cep_consulta_cep:read'])]
        public ?string $cep = null,
        #[Groups(['acbr_cep_consulta_cep:write', 'acbr_cep_consulta_cep:read'])]
        public ?string $webservice = '0',
        #[Groups(['acbr_cep_consulta_cep:read'])]
        public ?int $retorno = null,
        #[Groups(['acbr_cep_consulta_cep:read'])]
        public ?string $mensagem = null,
        #[Groups(['acbr_cep_consulta_cep:read'])]
        public ?AcbrCepEnderecoResource $dados = null,
    ) {
    }
}
