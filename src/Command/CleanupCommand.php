<?php
namespace App\Command;
use App\Operations\Cleanup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:cleanup',description:'Review expired jobs, exports and old unreferenced archives; --apply deletes candidates.')]
final class CleanupCommand extends Command implements SignalableCommandInterface
{
    private bool $stop=false;
    public function __construct(private readonly Cleanup $cleanup){parent::__construct();}
    protected function configure():void{$this->addOption('apply',null,InputOption::VALUE_NONE)->addOption('loop',null,InputOption::VALUE_REQUIRED,'Repeat every 60–3600 seconds; production maintenance service.');}
    public function getSubscribedSignals():array{return [SIGTERM,SIGINT];}
    public function handleSignal(int $signal,int|false $previousExitCode=0):int|false{$this->stop=true;return false;}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $interval=$input->getOption('loop');if(null!==$interval&&(!ctype_digit($interval)||(int)$interval<60||(int)$interval>3600))throw new \InvalidArgumentException('Loop must be 60–3600 seconds.');
        do{$output->writeln(json_encode($this->cleanup->run($input->getOption('apply')),JSON_THROW_ON_ERROR));if(null===$interval)break;for($i=0;$i<(int)$interval&&!$this->stop;$i++)sleep(1);}while(!$this->stop);return Command::SUCCESS;
    }
}
