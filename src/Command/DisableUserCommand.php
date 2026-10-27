<?php
namespace App\Command;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:user:disable',description:'Disable an account, its sessions and registry-token authority.')]
final class DisableUserCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addOption('email',null,InputOption::VALUE_REQUIRED);}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $email=$input->getOption('email');if(!is_string($email)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Supply --email.');$user=$this->em->getRepository(User::class)->findOneBy(['email'=>strtolower(trim($email))]);if(!$user){$output->writeln('Account not found.');return Command::FAILURE;}$user->disable();$this->em->flush();$output->writeln('Account disabled. Sessions and registry tokens no longer authorize access.');return Command::SUCCESS;
    }
}
