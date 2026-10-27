<?php
namespace App\Command;
use App\Entity\User;
use App\Mcp\Security\ComposeTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:mcp:token:issue',description:'Issue a scoped, expiring composer token for an enabled editor; plaintext is shown once.')]
final class CreateComposeTokenCommand extends Command
{
    public function __construct(private readonly ComposeTokens $tokens,private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void
    {
        $this->addOption('email',null,InputOption::VALUE_REQUIRED,'Existing enabled editor email.')->addOption('label',null,InputOption::VALUE_REQUIRED,'Recognizable token label.','Local Codex composer')->addOption('scope',null,InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,'Repeat composer scopes.',ComposeTokens::SCOPES)->addOption('catalogue',null,InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,'Repeat namespace/slug or namespace/* patterns.',['*'])->addOption('days',null,InputOption::VALUE_REQUIRED,'Expiry, 1–90 days.','7')->addOption('token-only',null,InputOption::VALUE_NONE,'Output only the one-time secret for a private environment/file.');
    }
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $user=$this->em->getRepository(User::class)->findOneBy(['email'=>$input->getOption('email')]);if(!$user)throw new \InvalidArgumentException('Choose an existing editor with --email.');
        [$record,$plain]=$this->tokens->issue($user,$input->getOption('label'),$input->getOption('scope'),$input->getOption('catalogue'),(int)$input->getOption('days'));
        if(!$input->getOption('token-only'))$output->writeln('Token ID: '.$record->getId().' (save this ID to revoke). Plaintext is shown only once:');
        $output->writeln($plain);return Command::SUCCESS;
    }
}
