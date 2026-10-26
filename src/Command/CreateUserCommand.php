<?php

namespace App\Command;

use App\Security\UserCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'styleguide:user:create', description: 'Create a private studio account with a hidden password prompt.')]
final class CreateUserCommand extends Command
{
    public function __construct(private readonly UserCreator $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin', null, InputOption::VALUE_NONE, 'Grant administrator access.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email (otherwise prompted).')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'ROLE_READER or ROLE_EDITOR (or use --admin).', 'ROLE_READER');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Run interactively to enter the password securely. No password argument or default is provided.');

            return Command::INVALID;
        }
        $email = $input->getOption('email') ?? $io->ask('Email');
        $password = $io->askHidden('Password (at least 12 characters)');
        $confirmation = $io->askHidden('Confirm password');
        if (!$password || $password !== $confirmation) {
            $io->error('Passwords must be nonempty and match.');

            return Command::INVALID;
        }
        try {
            $user = $this->users->create((string) $email, $password, $input->getOption('admin') ? 'ROLE_ADMIN' : $input->getOption('role'));
        } catch (\InvalidArgumentException $error) {
            $io->error($error->getMessage());

            return Command::INVALID;
        }
        $io->success(sprintf('Created %s.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
