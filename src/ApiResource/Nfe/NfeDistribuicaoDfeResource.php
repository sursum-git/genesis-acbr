<?php

namespace App\ApiResource\Nfe;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Nfe\NfeOperationInput;
use App\Dto\Nfe\NfeOperationOutput;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFeDistribuicaoDFe',
    input: NfeOperationInput::class,
    output: NfeOperationOutput::class,
    operations: [
        new Post(uriTemplate: '/nfe/distribuicao-dfe/por-chave', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'DistribuicaoDFePorChave'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/distribuicao-dfe/por-nsu', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'DistribuicaoDFePorNSU'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/distribuicao-dfe/por-ult-nsu', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'DistribuicaoDFePorUltNSU'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
    ]
)]
final class NfeDistribuicaoDfeResource
{
}
