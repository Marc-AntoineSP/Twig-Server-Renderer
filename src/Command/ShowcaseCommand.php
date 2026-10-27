<?php
namespace App\Command;
use App\Showcase\Showcase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'styleguide:showcase',description:'Add portable showcase drafts; --publish validates immutable samples/compositions without replacing edits.')]
final class ShowcaseCommand extends Command
{
    public function __construct(private readonly Showcase $showcase){parent::__construct();}
    protected function configure():void{$this->addOption('publish',null,InputOption::VALUE_NONE);}
    protected function execute(InputInterface $input,OutputInterface $output):int{foreach($this->showcase->seed($input->getOption('publish')) as $name=>$status)$output->writeln($name.': '.$status);return Command::SUCCESS;}
}
