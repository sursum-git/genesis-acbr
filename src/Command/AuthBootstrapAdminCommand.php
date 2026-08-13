<?php

namespace App\Command;

use App\Repository\Auth\AuthSchemaManager;
use App\Repository\Auth\AuthUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:auth:bootstrap-admin', description: 'Cria o usuario administrador geral inicial.')]
final class AuthBootstrapAdminCommand extends Command
{
    public function __construct(
        private readonly AuthSchemaManager $schemaManager,
        private readonly AuthUserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('usuario', InputArgument::REQUIRED, 'Usuario do administrador geral')
            ->addArgument('senha', InputArgument::REQUIRED, 'Senha inicial');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getArgument('usuario');
        $password = (string) $input->getArgument('senha');

        $this->schemaManager->ensureSchema();
        if ($this->users->findActiveByUsername($username) !== null) {
            $output->writeln('<error>Usuario ja existe.</error>');

            return Command::FAILURE;
        }

        $this->users->createUser($username, password_hash($password, PASSWORD_DEFAULT), 'super_admin', true, 'Administrador Geral');
        $output->writeln('<info>Administrador geral criado.</info>');

        return Command::SUCCESS;
    }
}
