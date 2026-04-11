<?php

namespace App\ApiResource\Nfe;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Legacy\AbstractAcbrLegacyOperationResource;
use App\State\Legacy\AcbrLegacyOperationProvider;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFeFerramentas',
    operations: [
        new Get(uriTemplate: '/nfe/ferramentas/openssl-info', provider: AcbrLegacyOperationProvider::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'OpenSSLInfo'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']]),
        new Get(uriTemplate: '/nfe/ferramentas/obter-certificados', provider: AcbrLegacyOperationProvider::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfe']]), extraProperties: ['acbr_script' => 'NFe/MT/ACBrNFeServicosMT.php', 'acbr_method' => 'ObterCertificados'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']]),
    ]
)]
final class NfeFerramentasResource extends AbstractAcbrLegacyOperationResource
{
}
