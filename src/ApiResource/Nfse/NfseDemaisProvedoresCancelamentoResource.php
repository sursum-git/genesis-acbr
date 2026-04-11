<?php

namespace App\ApiResource\Nfse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Legacy\AbstractAcbrLegacyOperationResource;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFSeDemaisProvedoresCancelamento',
    operations: [
        new Post(uriTemplate: '/nfse/demais-provedores/cancelamento/cancelar', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'Cancelar'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
    ]
)]
final class NfseDemaisProvedoresCancelamentoResource extends AbstractAcbrLegacyOperationResource
{
}
