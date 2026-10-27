<?php
namespace App\Command;
use App\Packaging\TransferImporter;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:import',description:'Validate or atomically import a local package/collection/envelope; never run imported hooks.')]
final class ImportPackageCommand extends Command
{
    public function __construct(private readonly TransferImporter $importer,private readonly CanonicalJson $json){parent::__construct();}
    protected function configure():void{$this->addArgument('file',InputArgument::REQUIRED)->addOption('dry-run',null,InputOption::VALUE_NONE,'Validate and show a real plan without applying.');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{$prepared=$this->importer->prepare($input->getArgument('file'),adjacent:true);$output->writeln($this->json->encode($prepared['plan'],true),OutputInterface::OUTPUT_RAW);
            if($input->getOption('dry-run')){$output->writeln('Dry run; no catalogue changes applied.');return Command::SUCCESS;}
            $result=$this->importer->apply($prepared,null,$prepared['planHash']);$output->writeln($this->json->encode($result,true),OutputInterface::OUTPUT_RAW);return Command::SUCCESS;
        }catch(CatalogueException $e){$output->writeln(json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR),OutputInterface::OUTPUT_RAW);return Command::FAILURE;}
    }
}
