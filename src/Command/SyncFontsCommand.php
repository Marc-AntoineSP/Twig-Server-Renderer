<?php

namespace App\Command;

use App\Font\FontLibrary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'styleguide:fonts:sync', description: 'Atomically register pinned, validated maintenance downloads. Online acquisition: make fonts-sync.')]
final class SyncFontsCommand extends Command
{
    public function __construct(private readonly FontLibrary $library, #[Autowire('%kernel.project_dir%')] private readonly string $project) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('collection', null, InputOption::VALUE_REQUIRED, 'Font collection.', 'popular')->addOption('family', null, InputOption::VALUE_REQUIRED)->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('popular' !== $input->getOption('collection')) { $output->writeln('Unknown font collection.'); return Command::INVALID; }
        $lockPath = $this->project.'/resources/library/fonts.lock.json';
        if (!is_file($lockPath)) { $output->writeln('Run make fonts-download to acquire the pinned collection.'); return Command::FAILURE; }
        $lock = json_decode(file_get_contents($lockPath), true, 24, JSON_THROW_ON_ERROR);
        $manifest = json_decode(file_get_contents($this->project.'/resources/library/fonts.json'), true, 24, JSON_THROW_ON_ERROR);
        $results = []; $failed = false;
        foreach ($manifest['families'] as $family) {
            $id = $family['id'];
            if ($input->getOption('family') && $id !== $input->getOption('family')) continue;
            try {
                $pin = $lock['families'][$id] ?? throw new \RuntimeException('No eligible pinned download; inspect the acquisition report.');
                $results[] = $this->library->importStaged($this->project.'/var/library-staging/fonts/'.$id.'/'.$pin['version'], $pin, (bool) $input->getOption('dry-run'));
            } catch (\Throwable $error) { $failed = true; $results[] = ['family' => $family['family'], 'status' => 'unavailable', 'reason' => $error->getMessage()]; }
            $output->writeln(json_encode(end($results), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        if (!$results) { $output->writeln('Unknown font family.'); return Command::INVALID; }
        if (!$input->getOption('dry-run')) file_put_contents($this->project.'/var/library-staging/fonts/import-report.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
