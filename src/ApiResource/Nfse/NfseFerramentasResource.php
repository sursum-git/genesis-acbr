<?php

namespace App\ApiResource\Nfse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Nfse\NfseOperationInput;
use App\Dto\Nfse\NfseOperationOutput;
use App\State\Legacy\AcbrLegacyOperationProvider;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFSeFerramentas',
    input: NfseOperationInput::class,
    output: NfseOperationOutput::class,
    operations: [
        new Get(uriTemplate: '/nfse/ferramentas/openssl-info', provider: AcbrLegacyOperationProvider::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'OpenSSLInfo'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']]),
        new Get(uriTemplate: '/nfse/ferramentas/obter-certificados', provider: AcbrLegacyOperationProvider::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'ObterCertificados'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']]),
    ]
)]
final class NfseFerramentasResource
{
}
