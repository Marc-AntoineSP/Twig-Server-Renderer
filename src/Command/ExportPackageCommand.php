<?php
namespace App\Command;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Packaging\ExportFile;
use App\Packaging\TransferExporter;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageReference;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:export',description:'Export a verified immutable release without overwriting existing output.')]
final class ExportPackageCommand extends Command
{
    public function __construct(private readonly TransferExporter $exporter,private readonly ExportFile $files,private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('package',InputArgument::REQUIRED,'namespace/slug@x.y.z')->addOption('format',null,InputOption::VALUE_REQUIRED,'source, collection, static, json or yaml','source')->addOption('output',null,InputOption::VALUE_REQUIRED,'New output file')->addOption('theme',null,InputOption::VALUE_REQUIRED,'Optional immutable namespace/slug@x.y.z');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{[$name,$version]=PackageReference::parse($input->getArgument('package'));$theme=null;
            if($input->getOption('theme')){[$tn,$tv]=PackageReference::parse($input->getOption('theme'));$t=$this->em->getRepository(Theme::class)->findOneBy(['name'=>$tn]);$theme=$t?$this->em->getRepository(ThemeRelease::class)->findOneBy(['theme'=>$t,'version'=>$tv]):null;if(!$theme)throw new CatalogueException(404,'Theme release not found.');}
            $artifact=$this->exporter->export($this->exporter->find($name,$version),$input->getOption('format'),$theme);$this->files->write((string)$input->getOption('output'),$artifact['bytes']);$output->writeln('Exported '.$artifact['name'].' SHA-256 '.hash('sha256',$artifact['bytes']));return Command::SUCCESS;
        }catch(CatalogueException $e){$output->writeln(json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR),OutputInterface::OUTPUT_RAW);return Command::FAILURE;}
    }
}
