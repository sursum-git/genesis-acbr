<?php

namespace App\Command;

use App\Repository\ApiAuditRepository;
use App\Repository\ApiExtractionRepository;
use App\Service\Api\ApiExtractionProcessor;
use App\Support\ApiExtractionStatus;
use App\Support\ApiRequestStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-mock-nfe-distribution',
    description: 'Gera uma resposta mock de distribuicao DF-e com resumos de NFe e popula t99007 até t99014.'
)]
final class SeedMockNfeDistributionCommand extends Command
{
    public function __construct(
        private readonly ApiAuditRepository $auditRepository,
        private readonly ApiExtractionRepository $extractionRepository,
        private readonly ApiExtractionProcessor $extractionProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Quantidade de resumos a gerar.', '10')
            ->addOption('uf', null, InputOption::VALUE_REQUIRED, 'UF do autor da consulta.', 'ES')
            ->addOption('cnpj', null, InputOption::VALUE_REQUIRED, 'CNPJ do autor da consulta.', '06013812000158')
            ->addOption('ult-nsu', null, InputOption::VALUE_REQUIRED, 'NSU inicial da consulta.', '000000000000000')
            ->addOption('ambiente', null, InputOption::VALUE_REQUIRED, '1 producao, 2 homologacao.', '1')
            ->addOption('assinante-id', null, InputOption::VALUE_REQUIRED, 'Identificador do assinante mock.', 'mock-nsu')
            ->addOption('assinante-nome', null, InputOption::VALUE_REQUIRED, 'Nome do assinante mock.', 'Assinante Mock NSU')
            ->addOption('somente-xml', null, InputOption::VALUE_NONE, 'Somente imprime o XML gerado, sem gravar no banco.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = max(1, min(100, (int) $input->getOption('count')));
        $uf = strtoupper(trim((string) $input->getOption('uf')));
        $cnpjAutor = preg_replace('/\D+/', '', (string) $input->getOption('cnpj')) ?? '';
        $ultNsu = $this->normalizeNsu((string) $input->getOption('ult-nsu'));
        $tpAmb = max(1, min(2, (int) $input->getOption('ambiente')));
        $assinanteId = trim((string) $input->getOption('assinante-id'));
        $assinanteNome = trim((string) $input->getOption('assinante-nome'));

        $responseXml = $this->buildDistributionXml($count, $uf, $cnpjAutor, $ultNsu, $tpAmb);

        if ((bool) $input->getOption('somente-xml')) {
            $output->writeln($responseXml);

            return Command::SUCCESS;
        }

        $queryString = http_build_query([
            'AcUFAutor' => $uf,
            'AeCNPJCPF' => $cnpjAutor,
            'AeultNSU' => $ultNsu,
        ]);

        $requestId = $this->auditRepository->createRequest([
            'c_metodo' => 'GET',
            'c_caminho' => '/nfe/distribuicao-dfe/por-ult-nsu',
            'c_cod_programa' => 'nfe',
            'c_nome_programa' => 'ACBrNFe',
            'c_versao_programa' => 'mock-nsu',
            't_query_string' => $queryString,
            't_assinante_json' => json_encode([
                'c_identificador' => $assinanteId,
                'c_nome' => $assinanteNome,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'si_status_extracao' => ApiExtractionStatus::PROCESSANDO,
            'c_modo_execucao' => 'sync',
            'c_nome_operacao' => 'mock_nfe_distribuicao_resnfe',
        ]);

        $this->auditRepository->finalizeRequest(
            $requestId,
            200,
            $responseXml,
            'Content-Type: application/xml; charset=UTF-8',
            null,
            ApiRequestStatus::CONCLUIDA,
            1
        );

        $requestRow = $this->auditRepository->findRequestByPublicId($requestId);
        if ($requestRow === null) {
            $output->writeln('<error>Falha ao localizar a requisicao mock criada.</error>');

            return Command::FAILURE;
        }

        try {
            $counts = $this->extractionProcessor->extract($requestRow);
            $this->extractionRepository->markCompleted((int) $requestRow['id_t99001']);
        } catch (\Throwable $throwable) {
            $this->extractionRepository->markFailed((int) $requestRow['id_t99001'], $throwable->getMessage());
            $output->writeln('<error>Falha na extração mock: ' . $throwable->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Mock de distribuicao gravado com sucesso.</info>');
        $output->writeln('request_id: ' . $requestId);
        $output->writeln('documentos_nfe: ' . (string) $counts['nfe_count']);
        $output->writeln('documentos_nsu: ' . (string) $counts['nsu_count']);
        $output->writeln('rota: /nfe/distribuicao-dfe/por-ult-nsu');
        $output->writeln('assinante: ' . $assinanteNome . ' (' . $assinanteId . ')');

        return Command::SUCCESS;
    }

    private function buildDistributionXml(int $count, string $uf, string $cnpjAutor, string $ultNsu, int $tpAmb): string
    {
        $docZips = [];
        $baseDate = new \DateTimeImmutable('2026-07-14 09:00:00-03:00');

        for ($index = 1; $index <= $count; $index++) {
            $nsuNumber = ((int) $ultNsu) + $index;
            $numeroNota = $nsuNumber;
            $nsu = str_pad((string) $nsuNumber, 15, '0', STR_PAD_LEFT);
            $cnpjEmitente = str_pad((string) (11111111000000 + $index), 14, '0', STR_PAD_LEFT);
            $chave = $this->buildAccessKey($cnpjEmitente, $numeroNota, $index);
            $dhEmi = $baseDate->modify('+' . $index . ' minutes');
            $dhRecbto = $dhEmi->modify('+1 minutes');
            $valor = number_format(150 + ($index * 17.35), 2, '.', '');
            $digVal = base64_encode(hash('sha1', $chave, true));

            $resumoXml = <<<XML
<resNFe versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe">
  <chNFe>{$chave}</chNFe>
  <CNPJ>{$cnpjEmitente}</CNPJ>
  <xNome>FORNECEDOR MOCK {$index} LTDA</xNome>
  <IE>08234567{$index}</IE>
  <dhEmi>{$dhEmi->format('Y-m-d\\TH:i:sP')}</dhEmi>
  <tpNF>1</tpNF>
  <vNF>{$valor}</vNF>
  <digVal>{$digVal}</digVal>
  <dhRecbto>{$dhRecbto->format('Y-m-d\\TH:i:sP')}</dhRecbto>
  <cSitNFe>100</cSitNFe>
</resNFe>
XML;

            $docZips[] = sprintf(
                '  <docZip NSU="%s" schema="resNFe_v1.01.xsd">%s</docZip>',
                $nsu,
                base64_encode(gzencode($resumoXml))
            );
        }

        $lastNsu = str_pad((string) (((int) $ultNsu) + $count), 15, '0', STR_PAD_LEFT);
        $dhResp = $baseDate->modify('+20 minutes')->format('Y-m-d\\TH:i:sP');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>{$tpAmb}</tpAmb>
  <verAplic>SVRS202607141030</verAplic>
  <cStat>138</cStat>
  <xMotivo>Documentos localizados</xMotivo>
  <dhResp>{$dhResp}</dhResp>
  <ultNSU>{$lastNsu}</ultNSU>
  <maxNSU>{$lastNsu}</maxNSU>
  <loteDistDFeInt>
{$this->implodeLines($docZips)}
  </loteDistDFeInt>
</retDistDFeInt>
XML;
    }

    private function buildAccessKey(string $cnpjEmitente, int $numeroNota, int $index): string
    {
        $uf = '32';
        $aamm = '2607';
        $modelo = '55';
        $serie = '003';
        $numero = str_pad((string) $numeroNota, 9, '0', STR_PAD_LEFT);
        $tpEmis = '1';
        $codigo = str_pad((string) (90000000 + $index), 8, '0', STR_PAD_LEFT);

        $base = $uf . $aamm . $cnpjEmitente . $modelo . $serie . $numero . $tpEmis . $codigo;
        $dv = $this->calculateModulo11($base);

        return $base . $dv;
    }

    private function calculateModulo11(string $base): string
    {
        $factor = 2;
        $sum = 0;

        for ($index = strlen($base) - 1; $index >= 0; $index--) {
            $sum += ((int) $base[$index]) * $factor;
            $factor = $factor === 9 ? 2 : $factor + 1;
        }

        $rest = $sum % 11;
        $digit = $rest < 2 ? 0 : 11 - $rest;

        return (string) $digit;
    }

    private function normalizeNsu(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return str_pad(substr($digits, 0, 15), 15, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<string> $lines
     */
    private function implodeLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
