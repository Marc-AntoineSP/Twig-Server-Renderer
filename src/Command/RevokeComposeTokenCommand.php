<?php
namespace App\Command;
use App\Entity\ComposeToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument,InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:mcp:token:revoke',description:'Revoke one local composer bearer token by ID.')]
final class RevokeComposeTokenCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('id',InputArgument::REQUIRED,'Token ID, never the bearer secret.');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $record=$this->em->find(ComposeToken::class,$input->getArgument('id'));if(!$record)throw new \InvalidArgumentException('Token not found.');$record->revoke();$this->em->flush();$output->writeln('Composer token revoked.');return Command::SUCCESS;
    }
}
