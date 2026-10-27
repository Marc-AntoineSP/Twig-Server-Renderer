<?php
namespace App\Command;
use App\Entity\User;
use App\Registry\Tokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(name:'styleguide:token:create',description:'Issue a scoped registry read token to an administrator; show plaintext once.')]
final class CreateReadTokenCommand extends Command
{
    public function __construct(private readonly Tokens $tokens,private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('owner',InputArgument::REQUIRED,'Administrator email')->addOption('label',null,InputOption::VALUE_REQUIRED,'Token label','Consumer')->addOption('scope',null,InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,'namespace/slug or namespace/*; use * explicitly for all.')->addOption('days',null,InputOption::VALUE_REQUIRED,'Validity (1–365 days)',30);}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $io=new SymfonyStyle($input,$output);$user=$this->em->getRepository(User::class)->findOneBy(['email'=>strtolower($input->getArgument('owner'))]);
        if(!$user){$io->error('Administrator not found.');return Command::INVALID;}
        try{[$token,$plain]=$this->tokens->issue($user,$input->getOption('label'),$input->getOption('scope'),(int)$input->getOption('days'));}
        catch(\Styleguide\Contracts\CatalogueException $e){$io->error($e->getMessage());return Command::INVALID;}
        $io->writeln('Token ID: '.$token->getId());$io->writeln('Plaintext (shown once): '.$plain);return Command::SUCCESS;
    }
}
