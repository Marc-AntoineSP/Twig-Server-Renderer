<?php

namespace App\Command;

use App\Entity\User;
use App\Security\UserCreator;
use App\Testing\BrowserRunGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'styleguide:test:accounts', description: 'Provision owned browser-test accounts in the disposable harness only.')]
final class BrowserAccountsCommand extends Command
{
    public function __construct(
        private readonly UserCreator $users,
        private readonly \App\Mcp\Security\ComposeTokens $composeTokens,
        private readonly EntityManagerInterface $entityManager,
        private readonly BrowserRunGuard $guard,
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
        $this->guard->verify();
        $path = BrowserRunGuard::DIRECTORY.'/accounts.json';
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $output->writeln('Browser accounts are available only in development/test.');

            return Command::INVALID;
        }
        if (is_file($path) && !$input->getOption('cleanup')) {
            $output->writeln('Owned browser accounts already prepared.');
            return Command::SUCCESS;
        }
        if (is_file($path)) {
            foreach (json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR) as $role => $account) {
                $user = $this->entityManager->find(User::class, $account['id']);
                if ($user && $user->getEmail() === $account['email'] && $user->getEmail() === $role.'@browser.example.test') {
                    $this->entityManager->remove($user);
                }
            }
            $this->entityManager->flush();
            unlink($path);
        }
        if (!$input->getOption('cleanup')) {
            $accounts = [];
            foreach (['reader', 'editor', 'admin', 'visual'] as $role) {
                $password = bin2hex(random_bytes(24));
                $user = $this->users->create($role.'@browser.example.test', $password, 'ROLE_'.($role === 'visual' ? 'EDITOR' : strtoupper($role)));
                $accounts[$role] = ['id' => $user->getId(), 'email' => $user->getEmail(), 'password' => $password];
                if($role==='editor'){$accounts[$role]['composeToken']=$this->composeTokens->issue($user,'Browser composition acceptance',\App\Mcp\Security\ComposeTokens::SCOPES,['browser/*'])[1];}
            }
            file_put_contents($path, json_encode($accounts, JSON_THROW_ON_ERROR));
            chmod($path, 0600);
        }
        $output->writeln('Temporary browser accounts '.($input->getOption('cleanup') ? 'removed.' : 'prepared.'));

        return Command::SUCCESS;
    }
}
