<?php

namespace App\Command;

use App\Repository\ApiAuditRepository;
use App\Repository\ApiExtractionRepository;
use App\Service\Api\ApiExtractionProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:api-extraction-worker', description: 'Processa a fila de extração de NFe, NSU e documentos da distribuição DF-e.')]
final class ApiExtractionWorkerCommand extends Command
{
    public function __construct(
        private readonly ApiExtractionRepository $extractionRepository,
        private readonly ApiExtractionProcessor $extractionProcessor,
        private readonly ApiAuditRepository $auditRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de requisicoes por ciclo.', '10')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Intervalo entre ciclos no modo continuo, em segundos.', '2')
            ->addOption('worker-id', null, InputOption::VALUE_REQUIRED, 'Identificador logico deste worker. Se omitido, usa hostname:pid.')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Executa apenas um ciclo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $sleepSeconds = max(1, (int) $input->getOption('sleep'));
        $runOnce = (bool) $input->getOption('once');
        $pid = getmypid() ?: null;
        $workerId = trim((string) ($input->getOption('worker-id') ?? ''));

        if ($workerId === '') {
            $workerId = sprintf('%s:%s', gethostname() ?: 'extractor', $pid ?? 'sem-pid');
        }

        do {
            $processed = 0;

            while ($processed < $limit) {
                $requestRow = $this->extractionRepository->claimNextPendingRequest();
                if ($requestRow === null) {
                    break;
                }

                $processed++;
                $requestInternalId = (int) $requestRow['id_t99001'];

                $this->auditRepository->createEvent(
                    $requestInternalId,
                    'extraction.started',
                    sprintf('Worker de extração %s iniciou a requisição. PID %s.', $workerId, $pid ?? 'n/d')
                );

                try {
                    $counts = $this->extractionProcessor->extract($requestRow);
                    $this->extractionRepository->markCompleted($requestInternalId);
                    $this->auditRepository->createEvent(
                        $requestInternalId,
                        'extraction.finished',
                        sprintf('Extração concluída com %d documento(s) NFe normalizado(s) e %d documento(s) NSU processado(s).', $counts['nfe_count'], $counts['nsu_count'])
                    );
                } catch (\Throwable $throwable) {
                    $this->extractionRepository->markFailed($requestInternalId, $throwable->getMessage());
                    $this->auditRepository->createEvent(
                        $requestInternalId,
                        'extraction.failed',
                        $throwable->getMessage()
                    );
                }
            }

            if ($runOnce) {
                break;
            }

            sleep($sleepSeconds);
        } while (true);

        return Command::SUCCESS;
    }
}
