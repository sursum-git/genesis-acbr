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
    shortName: 'NFeInutilizacao',
    input: NfeOperationInput::class,
    output: NfeOperationOutput::class,
    operations: [
        new Post(uriTemplate: '/nfe/inutilizacao/inutilizar', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'Inutilizar'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/inutilizacao/imprimir-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'ImprimirInutilizacaoPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/inutilizacao/salvar-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'SalvarInutilizacaoPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
    ]
)]
final class NfeInutilizacaoResource
{
}
