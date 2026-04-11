<?php

namespace App\ApiResource\Nfe;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\ApiResource\Legacy\AbstractAcbrLegacyOperationResource;
use App\State\Legacy\AcbrLegacyOperationProvider;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFeConsultas',
    operations: [
        new Get(uriTemplate: '/nfe/consultas/status-servico', provider: AcbrLegacyOperationProvider::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'statusServico'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']]),
        new Post(
            uriTemplate: '/nfe/consultas/consultar-com-chave',
            processor: AcbrLegacyOperationProcessor::class,
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    description: 'Informe a chave da NFe ou o caminho/conteudo do XML a ser consultado.',
                    content: new \ArrayObject([
                        'application/json' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['payload'],
                                'properties' => [
                                    'payload' => [
                                        'type' => 'object',
                                        'required' => ['eChaveOuNFe'],
                                        'properties' => [
                                            'eChaveOuNFe' => ['type' => 'string', 'description' => 'Chave de acesso da NFe, caminho do XML ou conteudo do XML.'],
                                            'AExtrairEventos' => ['type' => 'integer', 'description' => 'Define se os eventos da NFe devem ser extraidos. Padrão: 1.', 'default' => 1],
                                        ],
                                    ],
                                ],
                            ]),
                            example: [
                                'payload' => [
                                    'eChaveOuNFe' => '35260412345678000123550010000012341000012345',
                                    'AExtrairEventos' => 1,
                                ],
                            ]
                        ),
                    ]),
                    required: true
                ),
                extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]
            ),
            extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'Consultar', 'acbr_payload' => ['AExtrairEventos' => 1]],
            normalizationContext: ['groups' => ['acbr_legacy_operation:read']],
            denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]
        ),
        new Post(
            uriTemplate: '/nfe/consultas/consultar-recibo',
            processor: AcbrLegacyOperationProcessor::class,
            openapi: new OpenApiOperation(
                requestBody: new RequestBody(
                    description: 'Informe o numero do recibo retornado no envio assincrono.',
                    content: new \ArrayObject([
                        'application/json' => new MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['payload'],
                                'properties' => [
                                    'payload' => [
                                        'type' => 'object',
                                        'required' => ['ARecibo'],
                                        'properties' => [
                                            'ARecibo' => ['type' => 'string', 'description' => 'Numero do recibo da NFe.'],
                                        ],
                                    ],
                                ],
                            ]),
                            example: [
                                'payload' => [
                                    'ARecibo' => '351000000000000',
                                ],
                            ]
                        ),
                    ]),
                    required: true
                ),
                extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]
            ),
            extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'ConsultarRecibo'],
            normalizationContext: ['groups' => ['acbr_legacy_operation:read']],
            denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]
        ),
        new Get(
            uriTemplate: '/nfe/consultas/consulta-cadastro',
            provider: AcbrLegacyOperationProvider::class,
            openapi: new OpenApiOperation(
                parameters: [
                    new Parameter('AcUF', 'query', 'UF para consulta cadastral.', true, schema: ['type' => 'string']),
                    new Parameter('AnDocumento', 'query', 'CPF ou CNPJ do contribuinte.', true, schema: ['type' => 'string']),
                    new Parameter('AnIE', 'query', 'Inscrição estadual do contribuinte.', true, schema: ['type' => 'string']),
                ],
                extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]
            ),
            extraProperties: [
                'acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php',
                'acbr_method' => 'ConsultaCadastro',
                'acbr_query_params' => ['AcUF', 'AnDocumento', 'AnIE'],
            ],
            normalizationContext: ['groups' => ['acbr_legacy_operation:read']]
        ),
    ]
)]
final class NfeConsultasResource extends AbstractAcbrLegacyOperationResource
{
}
