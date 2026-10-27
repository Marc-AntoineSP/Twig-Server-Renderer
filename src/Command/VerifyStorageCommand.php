<?php
namespace App\Command;
use App\Operations\StorageVerifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:storage:verify',description:'Verify every referenced immutable archive against its hash and the shared package contract.')]
final class VerifyStorageCommand extends Command
{
    public function __construct(private readonly StorageVerifier $storage){parent::__construct();}
    protected function execute(InputInterface $input,OutputInterface $output):int{$result=$this->storage->verify();$output->writeln(json_encode($result,JSON_THROW_ON_ERROR));return $result['healthy']?Command::SUCCESS:Command::FAILURE;}
}
