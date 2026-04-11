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
    shortName: 'NFeEventos',
    input: NfeOperationInput::class,
    output: NfeOperationOutput::class,
    operations: [
        new Post(uriTemplate: '/nfe/eventos/cancelar', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'Cancelar'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/eventos/enviar-evento', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'EnviarEvento'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/eventos/imprimir-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'ImprimirEventoPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/eventos/salvar-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'SalvarEventoPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfe/eventos/enviar-email', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'EnviarEmailEvento'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
    ]
)]
final class NfeEventosResource
{
}
