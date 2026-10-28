<?php

namespace App\Tests\Functional;

use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Entity\{Favorite, ThemeFamily};
use App\Packaging\{PublishInput, ReleasePublisher};
use App\Repository\WorkspaceRepository;
use App\Tests\DatabaseTestCase;
use App\Theme\{ThemeFactory, ThemeWriter};
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class WorkspaceTest extends DatabaseTestCase
{
    private function draft(string $kind = 'component', string $title = 'Workspace item'): \App\Entity\Draft
    {
        return self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput(
            'workspace/item-'.strtolower((string) new Ulid()), $kind, $title,
        ), null);
    }

    public function testBoundedOrderingUsesDraftUpdatesAndFiltersPersonalFavoritesBeforeLimiting(): void
    {
        self::bootKernel();
        $owner = $this->account('ROLE_READER');
        $other = $this->account();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $ids = [];
        for ($index = 0; $index < 14; ++$index) {
            $item = $this->draft()->getItem();
            $ids[] = $item->getId();
            $em->persist(new Favorite($index < 7 ? $owner : $other, $item));
            $em->flush();
            $db->executeStatement("UPDATE catalogue_item SET updated_at='2098-01-01 00:00:00+00' WHERE id=?", [$item->getId()]);
            $db->executeStatement("UPDATE draft SET updated_at='2098-01-01 00:00:00+00' WHERE item_id=?", [$item->getId()]);
        }
        // Source changes count even when the metadata timestamp has not changed.
        $db->executeStatement("UPDATE draft SET updated_at='2099-01-01 00:00:00+00', revision=2 WHERE item_id=?", [$ids[0]]);
        // A stale favorite without an accessible draft must not consume a result slot.
        $orphan = new \App\Entity\CatalogueItem('workspace', 'orphan-'.strtolower((string) new Ulid()), 'component', 'Missing draft');
        $em->persist($orphan); $em->persist(new Favorite($owner, $orphan)); $em->flush();
        $db->executeStatement("UPDATE catalogue_item SET updated_at='2100-01-01 00:00:00+00' WHERE id=?", [$orphan->getId()]);
        $repository = self::getContainer()->get(WorkspaceRepository::class);
        $result = $repository->overview($owner);
        $descending = array_slice($ids, 1); rsort($descending, SORT_STRING);
        self::assertSame([$ids[0], ...array_slice($descending, 0, 5)], array_column($result['recent'], 'id'));
        self::assertSame(2, $result['recent'][0]['revision']);
        $own = array_slice($ids, 1, 6); rsort($own, SORT_STRING);
        self::assertSame([$ids[0], ...array_slice($own, 0, 5)], array_column($result['favorites'], 'id'));
        self::assertSame($result, $repository->overview($owner));
        self::assertTrue($result['recent'][0]['favorite']);
        self::assertFalse($result['recent'][1]['favorite']);
    }

    public function testRoleAwareDestinationsReleaseSummaryVariantsAndDeletedFavorites(): void
    {
        $client = self::createClient();
        $reader = $this->account('ROLE_READER'); $editor = $this->account();
        $source = $this->draft('component', '<script>Workspace title</script>');
        $composition = $this->draft('layout');
        self::getContainer()->get(ReleasePublisher::class)->publish($source->getItem(), new PublishInput('1.0.0', 1));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $factory = self::getContainer()->get(ThemeFactory::class);
        $writer = self::getContainer()->get(ThemeWriter::class);
        $family = new ThemeFamily('workspace-'.strtolower((string) new Ulid()), 'Workspace pair');
        $em->persist($family);
        $light = $writer->create($factory->create('workspace/light-'.strtolower((string) new Ulid())), $family);
        $document = $factory->create('workspace/dark-'.strtolower((string) new Ulid()));
        $document->colorScheme = 'dark';
        $dark = $writer->create($document, $family);
        $builtIn = new ThemeFamily('workspace-default-'.strtolower((string) new Ulid()), 'Workspace default', true);
        $em->persist($builtIn);
        $default = $writer->create($factory->create('workspace/default-'.strtolower((string) new Ulid())), $builtIn);
        foreach ([$source->getItem(), $composition->getItem(), $dark, $default] as $target) $em->persist(new Favorite($reader, $target));
        $em->persist(new Favorite($editor, $light)); $em->flush();
        foreach ([$source->getItem(), $composition->getItem()] as $item) {
            $em->getConnection()->executeStatement("UPDATE catalogue_item SET updated_at='2099-02-01 00:00:00+00' WHERE id=?", [$item->getId()]);
        }
        $this->signIn($client, $reader);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorCount(4, '#personal-favorites article');
        self::assertSelectorNotExists('#personal-favorites [data-workspace-id="'.$light->getId().'"]');
        self::assertSelectorTextContains('#personal-favorites [data-workspace-id="'.$dark->getId().'"]', 'Dark');
        self::assertSelectorTextContains('#personal-favorites', 'Released v1.0.0');
        self::assertStringNotContainsString('<script>Workspace title</script>', $client->getResponse()->getContent());
        foreach (['/catalogue/new', '/imports', '/themes/new'] as $path) self::assertSelectorNotExists('main a[href="'.$path.'"]');
        self::assertSelectorNotExists('main a[href$="/edit"], main a[href$="/compose"]');
        // Every read destination actually resolves with the reader's permissions.
        $links = $client->getCrawler()->filter('#personal-favorites .card-actions a')->each(fn ($node) => $node->attr('href'));
        foreach ($links as $link) { $client->request('GET', $link); self::assertResponseIsSuccessful(); }

        $this->signIn($client, $editor); $client->request('GET', '/');
        self::assertSelectorExists('main a[href="/catalogue/new"]');
        self::assertSelectorExists('#recent-items a[href="/catalogue/'.$source->getItem()->getId().'/edit"]');
        self::assertSelectorExists('#recent-items a[href="/catalogue/'.$composition->getItem()->getId().'/compose"]');
        self::assertSelectorExists('#personal-favorites a[href="/themes/'.$light->getId().'/edit"]');
        foreach (['/catalogue/'.$source->getItem()->getId().'/edit', '/catalogue/'.$composition->getItem()->getId().'/compose', '/themes/'.$light->getId().'/edit'] as $link) {
            $client->request('GET', $link); self::assertResponseIsSuccessful();
        }
        // Deleted targets cascade out of shortcuts; another account's favorites remain private.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->remove($em->find(\App\Entity\Theme::class, $dark->getId())); $em->flush();
        $this->signIn($client, $reader); $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '#personal-favorites article');
        self::assertSelectorNotExists('#personal-favorites [data-workspace-id="'.$dark->getId().'"]');
        self::assertSelectorNotExists('#personal-favorites [data-workspace-id="'.$light->getId().'"]');
        $this->signIn($client, $this->account('ROLE_READER')); $client->request('GET', '/');
        self::assertSelectorTextContains('#personal-favorites', 'No favorites yet');
        self::assertSelectorExists('#recent-items article');
    }

    public function testIndependentEmptyStatesAndAnonymousAccess(): void
    {
        $client = self::createClient();
        $client->request('GET', '/'); self::assertResponseRedirects('/login');
        $client->disableReboot();
        $reader = $this->account('ROLE_READER'); $editor = $this->account();
        $db = self::getContainer()->get(Connection::class);
        self::assertStringEndsWith('_test', $db->fetchOne('SELECT current_database()'));
        $db->beginTransaction();
        try {
            // Transactional empty catalogue, only in the disposable PHPUnit database.
            $db->executeStatement('TRUNCATE catalogue_item, theme CASCADE');
            $this->signIn($client, $reader); $client->request('GET', '/');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#recent-items', 'No drafts yet');
            self::assertSelectorTextContains('#personal-favorites', 'No favorites yet');
            self::assertSelectorExists('#recent-items a[href="/themes"]');
            self::assertSelectorNotExists('main a[href="/catalogue/new"]');
            $this->signIn($client, $editor); $client->request('GET', '/');
            self::assertSelectorExists('#recent-items a[href="/catalogue/new"]');
            // Built-in themes can be personal shortcuts without becoming recent work.
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $family = new ThemeFamily('empty-'.strtolower((string) new Ulid()), 'Empty default', true);
            $em->persist($family);
            $theme = self::getContainer()->get(ThemeWriter::class)->create(self::getContainer()->get(ThemeFactory::class)->create('workspace/empty-'.strtolower((string) new Ulid())), $family);
            $em->persist(new Favorite($em->getReference(\App\Entity\User::class, $editor->getId()), $theme)); $em->flush();
            $client->request('GET', '/');
            self::assertSelectorTextContains('#recent-items', 'No drafts yet');
            self::assertSelectorCount(1, '#personal-favorites article');
            self::assertSelectorExists('#personal-favorites a[href="/themes/'.$theme->getId().'"]');
            self::assertSelectorNotExists('#personal-favorites a[href$="/edit"]');
        } finally { $db->rollBack(); }
    }
}
