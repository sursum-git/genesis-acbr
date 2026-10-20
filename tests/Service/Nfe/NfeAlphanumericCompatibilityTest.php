<?php

declare(strict_types=1);
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Dto\Nfe\NfeConsultaCadastroInput;
use App\Service\Nfe\NfeAuthorizedArtifactLocator;
use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Callback;

$failures = [];
function checkAlpha(mixed $expected, mixed $actual, string $message): void {
    global $failures;
    if ($expected !== $actual) { $failures[] = $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true); }
}
function invokeAlpha(string $class, string $method, mixed ...$args): mixed {
    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    try { return (new ReflectionMethod($class, $method))->invoke($object, ...$args); }
    catch (Throwable $e) { global $failures; $failures[] = $method . ': ' . $e->getMessage(); return null; }
}
$cnpj = '12ABC34501DE35';
$key = '322610' . $cnpj . '550030001972521191972527';
checkAlpha(44, strlen($key), 'fixture key length');
foreach (['consultas.xml'=>'eChaveOuNFe', 'distribuicao_dfe.xml'=>'AechNFe'] as $file=>$parameter) {
    $mapping = new DOMDocument();
    $mapping->load(dirname(__DIR__, 3) . '/config/api_platform/resources/nfe/' . $file);
    $xpath = new DOMXPath($mapping);
    $pattern = $xpath->evaluate('string(//*[local-name()="parameter"][@key="'.$parameter.'"]//*[local-name()="value"][@name="pattern"])');
    checkAlpha(1, preg_match('~'.$pattern.'~D', $key), 'public API query validation accepts alpha key ' . $parameter);
}
$validator = Validation::createValidator();
foreach ([$cnpj, strtolower($cnpj), '06013812000158', '12345678901'] as $document) {
    $dto = new NfeConsultaCadastroInput('SP', $document, 'cpf_cnpj');
    checkAlpha(0, count($validator->validate($dto, new Callback('validate'))), 'accept compatible document ' . $document);
    checkAlpha(strtoupper($document), $dto->toLegacyPayload()['AnDocumento'], 'preserve uppercase document payload');
}
foreach (['12ABC34501DE3X', '12ABC34501DE3', '12ABC34501DE350', '../12ABC3450135'] as $document) {
    checkAlpha(1, count($validator->validate(new NfeConsultaCadastroInput('SP', $document, 'cpf_cnpj'), new Callback('validate'))), 'reject malformed document');
}
$base = sys_get_temp_dir() . '/acbr-alpha-' . bin2hex(random_bytes(5));
mkdir($base);
$path = $base . '/' . $key . '-nfe.xml';
file_put_contents($path, '<xml/>');
try {
    $locator = new NfeAuthorizedArtifactLocator($base);
    checkAlpha($key, $locator->extractAccessKeyFromResult(['mensagem' => "chDFe=$key\n"]), 'extract alpha key');
    checkAlpha($key, $locator->extractAccessKeyFromResult(['mensagem' => 'chNFe=' . strtolower($key) . "\r\n"]), 'normalize lowercase key');
    checkAlpha(str_replace('\\', '/', $path), str_replace('\\', '/', (string) $locator->locateAuthorizedXmlPath($key)), 'locate XML without deleting letters');
    checkAlpha($base . '/danfes/' . $key . '-danfe.pdf', $locator->buildDanfePath($key), 'preserve letters in PDF path');
    checkAlpha(null, $locator->locateAuthorizedXmlPath('../' . $key), 'reject traversal');
    checkAlpha(null, $locator->extractAccessKeyFromResult(['mensagem' => 'chDFe=' . $key . '1']), 'reject oversized key');
} finally { unlink($path); rmdir($base); }
foreach (['chNFe=' . $key, '<chNFe>' . $key . '</chNFe>', '<infNFe Id="NFe' . $key . '"/>'] as $text) {
    checkAlpha($key, invokeAlpha(App\Repository\NfeOutputMonitorRepository::class, 'extractAccessKeyFallback', $text, null), 'monitor fallback key');
}
checkAlpha('197252', invokeAlpha(App\Repository\NfeInputMonitorRepository::class, 'extractNoteNumberFromAccessKey', $key), 'preserve note-number offsets');
checkAlpha($key, invokeAlpha(App\Service\Api\ApiExtractionProcessor::class, 'extractFallbackAccessKey', ['eChaveOuNFe'=>$key], '', ''), 'audit extraction from query');
checkAlpha($key, invokeAlpha(App\Service\Api\ApiExtractionProcessor::class, 'extractAccessKeyFromString', '<infNFe Id="NFe'.$key.'"/>'), 'audit extraction from XML Id');
$xml = '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.$key.'"><ide><dhEmi>2026-10-20T10:00:00-03:00</dhEmi></ide><emit><CNPJ>'.$cnpj.'</CNPJ></emit></infNFe></NFe>';
checkAlpha($key, invokeAlpha(App\State\Nfe\NfeConsultaXmlProcessor::class, 'extractAccessKey', $xml), 'consult XML with alpha key');
$metadata=invokeAlpha(App\State\Nfe\NfeEnviarEmailProcessor::class, 'extractXmlMetadata', $xml, '');
checkAlpha($key, $metadata['chave'] ?? null, 'email XML key');
checkAlpha($cnpj, $metadata['cnpj'] ?? null, 'email XML issuer');
foreach ([App\Controller\NfeOutputMonitorController::class=>'buildCorrectionEventXml', App\Controller\NfeInputMonitorController::class=>'buildManifestationEventXml'] as $class=>$method) {
    $detail=['chave_nfe'=>$key,'emitente_documento'=>$cnpj,'cliente_documento'=>$cnpj,'ambiente'=>'2'];
    $event=$method==='buildCorrectionEventXml' ? invokeAlpha($class,$method,$detail,'Correcao das informacoes adicionais.',1) : invokeAlpha($class,$method,$detail,'210210','',1);
    checkAlpha(true, str_contains($event,'<chNFe>'.$key.'</chNFe>'), 'event key ' . $method);
    checkAlpha(true, str_contains($event,'<CNPJ>'.$cnpj.'</CNPJ>'), 'event document ' . $method);
}
$c=DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
(new AuthSchemaManager($c))->ensureSchema();
$r=new AuthUserRepository($c);
try {
    $id=$r->createCompany('Empresa alfanumerica','12.ABC.345/01DE-35');
    checkAlpha($cnpj,$c->fetchOne('SELECT c_cnpj FROM t00006 WHERE id_t00006 = ?',[$id]),'store alpha company');
    checkAlpha($id,$r->searchCompanies('abc345')[0]['id']??null,'search alpha CNPJ');
    checkAlpha([$cnpj],(new ReflectionMethod($r,'normalizeDocuments'))->invoke($r,[$cnpj]),'company permission scope');
} catch (InvalidArgumentException $e) { $failures[]='alpha company rejected: '.$e->getMessage(); }
if ($failures !== []) { fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL); exit(1); }
echo "OK\n";
