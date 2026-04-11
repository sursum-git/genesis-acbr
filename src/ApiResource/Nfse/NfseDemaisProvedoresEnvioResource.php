<?php

namespace App\ApiResource\Nfse;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Nfse\NfseOperationInput;
use App\Dto\Nfse\NfseOperationOutput;
use App\State\Legacy\AcbrLegacyOperationProcessor;

#[ApiResource(
    shortName: 'NFSeDemaisProvedoresEnvio',
    input: NfseOperationInput::class,
    output: NfseOperationOutput::class,
    operations: [
        new Post(uriTemplate: '/nfse/demais-provedores/envio/emitir-nota', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'Emitir', 'acbr_payload' => ['AModoEnvio' => 0]], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/enviar-lote-rps-assincrono', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'Emitir', 'acbr_payload' => ['AModoEnvio' => 1]], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/enviar-lote-rps-sincrono', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'Emitir', 'acbr_payload' => ['AModoEnvio' => 2]], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/enviar-um-rps', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'Emitir', 'acbr_payload' => ['AModoEnvio' => 3]], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/substituir-nfse', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'SubstituirNFSe'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/enviar-email', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'EnviarEmail'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/link-nfse', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'LinkNFSe'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/gerar-token', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'GerarToken'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/salvar-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'SalvarPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
        new Post(uriTemplate: '/nfse/demais-provedores/envio/imprimir-pdf', processor: AcbrLegacyOperationProcessor::class, openapi: new OpenApiOperation(extensionProperties: [OpenApiFactory::API_PLATFORM_TAG => ['nfse']]), extraProperties: ['acbr_script' => 'NFSe/MT/ACBrNFSeServicosMT.php', 'acbr_method' => 'ImprimirPDF'], normalizationContext: ['groups' => ['acbr_legacy_operation:read']], denormalizationContext: ['groups' => ['acbr_legacy_operation:write']]),
    ]
)]
final class NfseDemaisProvedoresEnvioResource
{
}
