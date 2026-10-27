<?php

namespace App\Command;

use App\Entity\User;
use App\Security\UserCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'styleguide:test:accounts', description: 'Provision random, temporary browser-test accounts (dev/test only).')]
final class BrowserAccountsCommand extends Command
{
    public function __construct(
        private readonly UserCreator $users,
        private readonly \App\Mcp\Security\ComposeTokens $composeTokens,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/var/browser/accounts.json')]
        private readonly string $path,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('cleanup', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $output->writeln('Browser accounts are available only in development/test.');

            return Command::INVALID;
        }
        if (is_file($this->path)) {
            foreach (json_decode(file_get_contents($this->path), true, 16, JSON_THROW_ON_ERROR) as $account) {
                $user = $this->entityManager->find(User::class, $account['id']);
                if ($user && str_starts_with($user->getEmail(), 'browser-')) {
                    $this->entityManager->remove($user);
                }
            }
            $this->entityManager->flush();
            unlink($this->path);
        }
        if (!$input->getOption('cleanup')) {
            $accounts = [];
            foreach (['reader', 'editor', 'admin'] as $role) {
                $password = bin2hex(random_bytes(24));
                $user = $this->users->create('browser-'.bin2hex(random_bytes(8)).'@example.test', $password, 'ROLE_'.strtoupper($role));
                $accounts[$role] = ['id' => $user->getId(), 'email' => $user->getEmail(), 'password' => $password];
                if($role==='editor'){$accounts[$role]['composeToken']=$this->composeTokens->issue($user,'Browser composition acceptance',\App\Mcp\Security\ComposeTokens::SCOPES,['browser/*'])[1];}
            }
            if (!is_dir(dirname($this->path))) {
                mkdir(dirname($this->path), 0700, true);
            }
            file_put_contents($this->path, json_encode($accounts, JSON_THROW_ON_ERROR));
            chmod($this->path, 0600);
        }
        $output->writeln('Temporary browser accounts '.($input->getOption('cleanup') ? 'removed.' : 'prepared.'));

        return Command::SUCCESS;
    }
}
