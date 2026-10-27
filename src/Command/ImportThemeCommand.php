<?php
namespace App\Command;
use App\Entity\Theme;
use App\Theme\ThemeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\ThemeImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:theme:import',description:'Validate/import canonical theme JSON, safe YAML or own generated CSS.')]
final class ImportThemeCommand extends Command
{
    public function __construct(private readonly ThemeImporter $importer,private readonly ThemeWriter $writer,private readonly EntityManagerInterface $em,private readonly CanonicalJson $json){parent::__construct();}
    protected function configure():void{$this->addArgument('file',InputArgument::REQUIRED)->addOption('dry-run',null,InputOption::VALUE_NONE)->addOption('revision',null,InputOption::VALUE_REQUIRED,'Required when replacing an existing theme draft.');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{$file=$input->getArgument('file');if(!is_file($file)||filesize($file)>262144)throw new CatalogueException(413,'Choose a theme file within 256 KiB.');$format=strtolower(pathinfo($file,PATHINFO_EXTENSION));if('yml'===$format)$format='yaml';$document=$this->importer->parse(file_get_contents($file),$format);$theme=$this->em->getRepository(Theme::class)->findOneBy(['name'=>$document->name]);
            $output->writeln($this->json->encode(['name'=>$document->name,'version'=>$document->version,'currentRevision'=>$theme?->getRevision(),'canonicalSha256'=>hash('sha256',$this->json->encode($document))],true));
            if($input->getOption('dry-run')){$output->writeln('Dry run; no changes applied.');return Command::SUCCESS;}
            if($theme){if(!$input->getOption('revision'))throw new CatalogueException(428,'Supply --revision for an existing theme.');$this->writer->save($theme,(int)$input->getOption('revision'),$document);}else{$this->writer->create($document);}
            $output->writeln('Theme imported.');return Command::SUCCESS;
        }catch(CatalogueException $e){$output->writeln(json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR),OutputInterface::OUTPUT_RAW);return Command::FAILURE;}
    }
}
