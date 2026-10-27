<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
final class InstallerCommand extends Command
{
    public function __construct(private readonly Installer $installer,private readonly Registries $registries,private readonly Project $project,private readonly CanonicalJson $json,private readonly string $operation,string $name){parent::__construct($name);}
    protected function configure():void
    {
        if('registry'===$this->operation){$this->setDescription('Configure a registry origin; credentials stay outside the project.')->addArgument('name',InputArgument::REQUIRED)->addArgument('url',InputArgument::REQUIRED)->addOption('allow-http',null,InputOption::VALUE_NONE,'Explicit local development only.')->addOption('css-input',null,InputOption::VALUE_REQUIRED)->addOption('css-output',null,InputOption::VALUE_REQUIRED)->addOption('bootstrap',null,InputOption::VALUE_REQUIRED);return;}
        if('doctor'===$this->operation){$this->setDescription('Verify compatibility, owned hashes and interrupted journals.')->addOption('recover',null,InputOption::VALUE_NONE,'Recover an interrupted installer transaction.');return;}
        $this->setDescription('Validate, plan and journal portable package '.$this->operation.'.')->addArgument('package',InputArgument::REQUIRED,'install: file; remove: name; otherwise namespace/slug@x.y.z')->addOption('dry-run',null,InputOption::VALUE_NONE,'Stage a concrete plan and code review without destination writes.');
        if(in_array($this->operation,['require','theme'],true))$this->addOption('registry',null,InputOption::VALUE_REQUIRED,'Configured registry alias.');
    }
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        try{
            if('registry'===$this->operation){$cfg=$this->project->config();$css=$input->getOption('css-input')??$cfg['css']['input'];$compiled=$input->getOption('css-output')??$cfg['css']['output'];$bootstrap=$input->getOption('bootstrap')??$cfg['bootstrap'];if($this->project->lock()['packages']&&([$css,$compiled,$bootstrap]!==[$cfg['css']['input'],$cfg['css']['output'],$cfg['bootstrap']]))throw new CatalogueException(409,'Integration paths are pinned while packages are installed.');$this->registries->add($input->getArgument('name'),$input->getArgument('url'),$input->getOption('allow-http'),$css,$compiled,$bootstrap);$output->writeln('Registry configured. Keep its read token in a private auth file or STYLEGUIDE_TOKEN_ALIAS.');return Command::SUCCESS;}
            if('doctor'===$this->operation){$result=$this->installer->doctor($input->getOption('recover'));$output->writeln($this->json->encode($result,true),OutputInterface::OUTPUT_RAW);return $result['healthy']?Command::SUCCESS:Command::FAILURE;}
            $result=$this->installer->run($this->operation,$input->getArgument('package'),in_array($this->operation,['require','theme'],true)?$input->getOption('registry'):null,$input->getOption('dry-run'));$output->writeln($this->json->encode($result,true),OutputInterface::OUTPUT_RAW);if(!$result['dryRun'])$output->writeln('Source and CSS installed. For production deployment run asset-map:compile and clear the application cache.');return Command::SUCCESS;
        }catch(CatalogueException $e){$output->writeln($this->json->encode(['status'=>$e->getStatusCode(),'error'=>$e->getMessage()],true),OutputInterface::OUTPUT_RAW);return Command::FAILURE;}
    }
}
