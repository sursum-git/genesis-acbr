<?php

namespace App\Command;

use App\Repository\ApiAuditRepository;
use App\Service\Api\InternalApiRequestRunner;
use App\Support\ApiRequestStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:api-request-worker', description: 'Processa requisicoes assíncronas pendentes da API.')]
final class ApiRequestWorkerCommand extends Command
{
    public function __construct(
        private readonly ApiAuditRepository $auditRepository,
        private readonly InternalApiRequestRunner $requestRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Quantidade maxima de requisicoes a processar.', '10')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Intervalo entre ciclos no modo continuo, em segundos.', '2')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Executa apenas um ciclo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $sleepSeconds = max(1, (int) $input->getOption('sleep'));
        $runOnce = (bool) $input->getOption('once');

        do {
            $processed = 0;

            while ($processed < $limit) {
                $requestRow = $this->auditRepository->claimNextQueuedRequest();
                if ($requestRow === null) {
                    break;
                }

                $processed++;
                $attemptId = $this->auditRepository->createAttempt((int) $requestRow['id_t99001']);

                try {
                    $response = $this->requestRunner->run($requestRow);
                    $statusProcessamento = $response->getStatusCode() >= 400 ? ApiRequestStatus::FALHA : ApiRequestStatus::CONCLUIDA;
                    $responseBody = $response->getContent();
                    $errorMessage = $statusProcessamento === ApiRequestStatus::FALHA ? $responseBody : null;

                    $this->auditRepository->finalizeAttempt(
                        $attemptId,
                        $response->getStatusCode(),
                        $responseBody,
                        $errorMessage,
                        $statusProcessamento
                    );

                    $this->auditRepository->finalizeRequest(
                        (string) $requestRow['u_c_request_id'],
                        $response->getStatusCode(),
                        $responseBody,
                        null,
                        $errorMessage,
                        $statusProcessamento,
                        null
                    );
                } catch (\Throwable $throwable) {
                    $this->auditRepository->finalizeAttempt(
                        $attemptId,
                        500,
                        null,
                        $throwable->getMessage(),
                        ApiRequestStatus::FALHA
                    );

                    $this->auditRepository->finalizeRequest(
                        (string) $requestRow['u_c_request_id'],
                        500,
                        null,
                        null,
                        $throwable->getMessage(),
                        ApiRequestStatus::FALHA,
                        null
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
