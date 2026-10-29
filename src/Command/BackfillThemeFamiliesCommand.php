<?php

namespace App\Command;

use App\Appearance\ThemeFamilies;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'styleguide:themes:backfill-families', description: 'Explicitly attach a bounded batch of legacy themes to families; repeat until remaining=0.')]
final class BackfillThemeFamiliesCommand extends Command
{
    private const ORPHANS = 'FROM theme t WHERE NOT EXISTS (SELECT 1 FROM theme_family f WHERE f.light_theme_id=t.id OR f.dark_theme_id=t.id)';
    public function __construct(private readonly EntityManagerInterface $em, private readonly ThemeFamilies $families) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum themes in this invocation (1–1000).', '100');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if (false === $limit) { $output->writeln('Use --limit=1 through --limit=1000.'); return Command::INVALID; }
        $this->families->default();
        $db = $this->em->getConnection();
        $ids = $db->fetchFirstColumn('SELECT t.id '.self::ORPHANS.' ORDER BY t.id LIMIT '.$limit);
        $processed = 0;
        foreach ($ids as $id) {
            // Each successful attachment commits independently. A retry selects only
            // remaining orphans; interruption does not roll back completed work.
            if ($theme = $this->em->find(Theme::class, $id)) { $this->families->ensureForTheme($theme); ++$processed; }
            $this->em->clear();
        }
        $output->writeln('processed='.$processed.' remaining='.$db->fetchOne('SELECT COUNT(*) '.self::ORPHANS));
        return Command::SUCCESS;
    }
}
