<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Command\SeedMockNfeDistributionCommand;

function assertTrueSeedValue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$reflection = new ReflectionClass(SeedMockNfeDistributionCommand::class);
$command = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('buildDistributionXml');
$method->setAccessible(true);

$xml = $method->invoke($command, 1, 'ES', '06013812000158', '000000000642079', 1, false);

assertTrueSeedValue(is_string($xml), 'Distribution XML should be a string.');
assertTrueSeedValue(str_contains($xml, '<tpAmb>1</tpAmb>'), 'Distribution XML should be production by default.');
assertTrueSeedValue(str_contains($xml, 'NSU="000000000642080"'), 'Distribution XML should create the next NSU from ultNSU.');

$document = new DOMDocument();
$document->loadXML($xml);
$xpath = new DOMXPath($document);
$encoded = $xpath->evaluate('string((//*[local-name()="docZip"])[1])');
$decoded = gzdecode(base64_decode((string) $encoded, true) ?: '');

assertTrueSeedValue(is_string($decoded), 'docZip payload should decode as gzip XML.');
assertTrueSeedValue(str_contains($decoded, '<resNFe'), 'docZip payload should be a resNFe summary.');
assertTrueSeedValue(str_contains($decoded, '<xNome>FORNECEDOR MOCK 1 LTDA</xNome>'), 'resNFe should represent a third-party issuer.');
assertTrueSeedValue(str_contains($decoded, '<tpNF>1</tpNF>'), 'resNFe should represent an outbound note from the issuer against the consulted company.');
preg_match('/<chNFe>(\d{44})<\/chNFe>/', $decoded, $summaryKeyMatches);
$summaryKey = $summaryKeyMatches[1] ?? '';

$xmlWithFullDocument = $method->invoke($command, 1, 'ES', '06013812000158', '000000000642079', 1, true);
$documentWithFullDocument = new DOMDocument();
$documentWithFullDocument->loadXML($xmlWithFullDocument);
$xpathWithFullDocument = new DOMXPath($documentWithFullDocument);
$fullEncoded = $xpathWithFullDocument->evaluate('string((//*[local-name()="docZip"])[2])');
$fullDecoded = gzdecode(base64_decode((string) $fullEncoded, true) ?: '');

assertTrueSeedValue(str_contains($xmlWithFullDocument, 'NSU="000000000642081"'), 'Distribution XML with full document should create a second NSU.');
assertTrueSeedValue(is_string($fullDecoded), 'Full docZip payload should decode as gzip XML.');
assertTrueSeedValue(str_contains($fullDecoded, '<nfeProc'), 'Full docZip payload should be a procNFe document.');
assertTrueSeedValue($summaryKey !== '' && str_contains($fullDecoded, '<chNFe>' . $summaryKey . '</chNFe>'), 'procNFe should have the same access key as the summary.');

fwrite(STDOUT, "OK\n");
