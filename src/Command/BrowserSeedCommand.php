<?php

namespace App\Command;

use App\Appearance\{DefaultThemes, ThemeFamilies};
use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Entity\{Favorite, ThemeFamily, User};
use App\Packaging\{PublishInput, ReleasePublisher};
use App\Repository\CatalogueRepository;
use App\Testing\BrowserRunGuard;
use App\Theme\ThemeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'styleguide:test:seed', description: 'Seed bounded deterministic fixtures in a verified disposable browser run.')]
final class BrowserSeedCommand extends Command
{
    public function __construct(private readonly BrowserRunGuard $guard, private readonly CatalogueWriter $writer,
        private readonly CatalogueRepository $catalogue, private readonly ReleasePublisher $publisher,
        private readonly EntityManagerInterface $em, private readonly ThemeWriter $themes, private readonly ThemeFamilies $families)
    { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->guard->verify();
        $path = BrowserRunGuard::DIRECTORY.'/fixtures.json';
        if (is_file($path)) { $output->writeln('Owned seed already complete.'); return Command::SUCCESS; }
        $editor = $this->em->getRepository(User::class)->findOneBy(['email' => 'editor@browser.example.test']);
        if (!$editor) throw new \RuntimeException('Prepare owned accounts first.');
        $fixtures = [];
        foreach (['component', 'block', 'layout'] as $kind) {
            $name = 'browser/seed-'.$kind;
            $item = $this->catalogue->named($name) ?? $this->writer->create(new CreateItemInput($name, $kind, 'Seed '.ucfirst($kind), 'Deterministic acceptance fixture.', ['acceptance']), $editor)->getItem();
            if (!$this->em->getRepository(\App\Entity\Release::class)->findOneBy(['item' => $item, 'version' => '1.0.0'])) {
                $this->publisher->publish($item, new PublishInput('1.0.0', 1));
            }
            $fixtures[$kind] = ['id' => $item->getId(), 'name' => $name];
        }
        $name = 'browser/seed-workspace';
        $item = $this->catalogue->named($name) ?? $this->writer->create(new CreateItemInput($name, 'layout', 'Acceptance workspace', 'Deterministic composer workspace.', ['acceptance']), $editor)->getItem();
        $fixtures['workspace'] = ['id' => $item->getId(), 'name' => $name];
        $family = $this->em->getRepository(ThemeFamily::class)->findOneBy(['key' => 'browser/seed-family']);
        if (!$family) { $family = new ThemeFamily('browser/seed-family', 'Acceptance theme'); $this->em->persist($family); }
        foreach (['light', 'dark'] as $mode) {
            $theme = $family->variants()[$mode] ?? $this->themes->create(DefaultThemes::document($mode, 'browser/seed-theme-'.$mode), $family);
            $release = $this->em->getRepository(\App\Entity\ThemeRelease::class)->findOneBy(['theme' => $theme, 'version' => '1.0.0']) ?? $this->themes->publish($theme, 1);
            $fixtures['theme-'.$mode] = ['id' => $theme->getId(), 'name' => $theme->getName(), 'releaseId' => $release->getId()];
        }
        $this->families->default();
        // Favorites are private; readers still share the existing catalogue by design.
        foreach ([$this->catalogue->named('browser/seed-component'), $family->getDarkTheme()] as $target) {
            $column = $target instanceof \App\Entity\Theme ? 'theme_id' : 'catalogue_item_id';
            if (!$this->em->getConnection()->fetchOne('SELECT 1 FROM personal_favorite WHERE owner_id=? AND '.$column.'=?', [$editor->getId(), $target->getId()])) {
                $this->em->persist(new Favorite($editor, $target));
            }
        }
        $this->em->flush();
        $visual = $this->em->getRepository(User::class)->findOneBy(['email' => 'visual@browser.example.test']);
        if ($visual && !$this->em->getConnection()->fetchOne('SELECT 1 FROM personal_favorite WHERE owner_id=? AND catalogue_item_id=?', [$visual->getId(), $fixtures['component']['id']])) {
            $this->em->persist(new Favorite($visual, $this->catalogue->named('browser/seed-component')));
            $this->em->flush();
        }
        // Freeze only explicitly seeded rows; screenshots never hide timestamp content.
        foreach (['component', 'block', 'layout', 'workspace'] as $key) {
            $this->em->getConnection()->executeStatement("UPDATE catalogue_item SET updated_at='2026-01-01 12:00:00' WHERE id=?", [$fixtures[$key]['id']]);
        }
        file_put_contents($path, json_encode($fixtures, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        $output->writeln('Seeded 4 catalogue items, 3 item releases, 2 theme releases, 1 light/dark family and private editor/visual favorites.');
        return Command::SUCCESS;
    }
}
