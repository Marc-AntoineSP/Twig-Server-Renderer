<?php
namespace App\Command;
use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Packaging\ThemePackager;
use App\Packaging\ExportFile;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageReference;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:theme:export',description:'Export immutable typed theme source, compiled CSS, tokens and licence metadata.')]
final class ExportThemeCommand extends Command
{
    public function __construct(private readonly ThemePackager $packager,private readonly EntityManagerInterface $em,private readonly ExportFile $files){parent::__construct();}
    protected function configure():void{$this->addArgument('theme',InputArgument::REQUIRED,'namespace/slug@x.y.z')->addOption('output',null,InputOption::VALUE_REQUIRED);}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{[$name,$version]=PackageReference::parse($input->getArgument('theme'));$theme=$this->em->getRepository(Theme::class)->findOneBy(['name'=>$name]);$release=$theme?$this->em->getRepository(ThemeRelease::class)->findOneBy(['theme'=>$theme,'version'=>$version]):null;if(!$release)throw new CatalogueException(404,'Immutable theme release not found.');$package=$this->packager->package($release);$this->files->write((string)$input->getOption('output'),$package['archiveBytes']);$output->writeln('Theme exported SHA-256 '.$package['sha256']);return Command::SUCCESS;
        }catch(CatalogueException $e){$output->writeln(json_encode(['error'=>$e->getMessage()],JSON_THROW_ON_ERROR),OutputInterface::OUTPUT_RAW);return Command::FAILURE;}
    }
}
