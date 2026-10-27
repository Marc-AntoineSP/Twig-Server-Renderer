<?php

namespace App\Command;

use App\Entity\Theme;
use App\Entity\ThemeRelease;
use App\Font\FontLibrary;
use App\Theme\Contrast;
use App\Theme\ThemeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ThemeImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'styleguide:themes:collect', description: 'Validate staged website observations and import managed theme revisions. Online research: make themes-collect.')]
final class CollectWebsiteThemesCommand extends Command
{
    public function __construct(private readonly FontLibrary $fonts, private readonly ThemeImporter $importer, private readonly ThemeWriter $writer, private readonly EntityManagerInterface $em, private readonly CanonicalJson $json, private readonly Contrast $contrast, #[Autowire('%kernel.project_dir%')] private readonly string $project) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('collection', null, InputOption::VALUE_REQUIRED, 'Website collection.', 'website-inspired')->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('website-inspired' !== $input->getOption('collection')) { $output->writeln('Unknown theme collection.'); return Command::INVALID; }
        $path = $this->project.'/resources/library/theme-candidates.json';
        if (!is_file($path)) { $output->writeln('Run make themes-research and make themes-candidates first.'); return Command::FAILURE; }
        $collection = json_decode(file_get_contents($path), false, 32, JSON_THROW_ON_ERROR);
        $results = []; $failed = false;
        foreach ($collection->candidates as $candidate) {
            $document = $candidate->document;
            try {
                $evidence = $this->project.'/resources/library/evidence/'.$document->provenance->slug;
                $reference = preg_replace('/-inspired-(?:light|dark)$/D', '', $document->provenance->slug);
                $evidence = $this->project.'/resources/library/evidence/'.$reference.'.json';
                if (!is_file($evidence) || !hash_equals($candidate->evidenceSha256, hash_file('sha256', $evidence))) throw new \RuntimeException('Evidence changed; regenerate candidates.');
                $ids = array_unique([$document->typography->bodyFontId, $document->typography->headingFontId, $document->typography->codeFontId]);
                $document->fonts = array_map(fn ($id) => $this->fonts->definition($id), array_values($ids));
                // The same importer and validator used by the theme UI, packages and consumers.
                $document = $this->importer->parse($this->json->encode($document), 'json');
                $ratios = $this->contrast->ratios($document);
                if (min($ratios) < 4.5) throw new \RuntimeException('A complete semantic text pair fails the 4.5:1 palette gate.');
                $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $document->name]);
                $same = $theme && $this->json->encode($theme->getDocument()) === $this->json->encode($document);
                if ($theme && !$same && !($theme->getDocument()->provenance->managed ?? false)) throw new \RuntimeException('Name belongs to a user theme; preserved without changes.');
                if ($theme && !$same && version_compare($document->version, $theme->getDocument()->version, '<=')) throw new \RuntimeException('Managed candidate differs at an existing version. Bump the declarative collection version before applying; the existing draft is preserved.');
                $result = ['dryRun' => (bool) $input->getOption('dry-run'), 'name' => $document->name, 'version' => $document->version, 'status' => $same ? 'unchanged' : ($theme ? 'new-revision' : 'imported'), 'ratios' => $ratios];
                if ($theme && !$same) {
                    $changes = [];
                    $compare = function ($before, $after, string $path = '') use (&$compare, &$changes): void {
                        if ($this->json->encode($before) === $this->json->encode($after)) return;
                        if ($before instanceof \stdClass && $after instanceof \stdClass) {
                            foreach (array_unique([...array_keys((array) $before), ...array_keys((array) $after)]) as $key) $compare($before->$key ?? null, $after->$key ?? null, ltrim($path.'.'.$key, '.'));
                        } else $changes[$path] = ['before' => $before, 'after' => $after];
                    };
                    $compare($theme->getDocument(), $document); $result['diff'] = $changes;
                }
                if (!$input->getOption('dry-run')) {
                    if (!$theme) $theme = $this->writer->create($document);
                    elseif (!$same) $this->writer->save($theme, $theme->getRevision(), $document, managedImport: true);
                    if (!$this->em->getRepository(ThemeRelease::class)->findOneBy(['theme' => $theme, 'version' => $document->version])) $this->writer->publish($theme, $theme->getRevision());
                    $result['id'] = $theme->getId();
                }
                $results[] = $result;
            } catch (\Throwable $error) { $failed = true; $results[] = ['name' => $document->name, 'status' => 'failed', 'reason' => $error->getMessage()]; }
            $output->writeln($this->json->encode(end($results)));
        }
        foreach ($collection->results as $result) if ('pending' === $result->status) $output->writeln($this->json->encode($result));
        if (!$input->getOption('dry-run')) {
            $directory = $this->project.'/var/library-staging/themes';
            if (!is_dir($directory)) mkdir($directory, 0770, true);
            file_put_contents($directory.'/import-report.json', $this->json->encode(['results' => $results, 'sourceResults' => $collection->results], true)."\n");
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
