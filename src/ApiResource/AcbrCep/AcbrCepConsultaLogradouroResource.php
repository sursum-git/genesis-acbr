<?php

namespace App\ApiResource\AcbrCep;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\AcbrCep\AcbrCepConsultaLogradouroProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'AcbrCepConsultaLogradouro',
    operations: [
        new Post(
            uriTemplate: '/acbr-cep/consulta-logradouro',
            processor: AcbrCepConsultaLogradouroProcessor::class,
            openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['cep']]),
            denormalizationContext: ['groups' => ['acbr_cep_consulta_logradouro:write']],
            normalizationContext: ['groups' => ['acbr_cep_consulta_logradouro:read']],
        ),
    ]
)]
final class AcbrCepConsultaLogradouroResource
{
    public function __construct(
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $cidade = null,
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $tipo = '',
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $logradouro = '',
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $uf = '',
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $bairro = '',
        #[Groups(['acbr_cep_consulta_logradouro:write', 'acbr_cep_consulta_logradouro:read'])]
        public ?string $webservice = '0',
        #[Groups(['acbr_cep_consulta_logradouro:read'])]
        public ?int $retorno = null,
        #[Groups(['acbr_cep_consulta_logradouro:read'])]
        public ?string $mensagem = null,
        #[Groups(['acbr_cep_consulta_logradouro:read'])]
        public ?AcbrCepEnderecoResource $dados = null,
    ) {
    }
}
