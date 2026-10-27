<?php
namespace App\Command;
use App\Entity\ReadToken;
use App\Registry\Tokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:token:revoke',description:'Revoke a registry token by its nonsecret ID.')]
final class RevokeReadTokenCommand extends Command
{
    public function __construct(private readonly Tokens $tokens,private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('id',InputArgument::REQUIRED);}
    protected function execute(InputInterface $input,OutputInterface $output):int{$token=$this->em->find(ReadToken::class,$input->getArgument('id'));if(!$token){$output->writeln('Token not found.');return Command::INVALID;}$this->tokens->revoke($token);$output->writeln('Token revoked.');return Command::SUCCESS;}
}
