<?php

namespace App\Tests\Functional;

use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Entity\CatalogueItem;
use App\Tests\DatabaseTestCase;
use App\Theme\{ThemeFactory, ThemeWriter};
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Ulid;

final class FavoriteTest extends DatabaseTestCase
{
    private function csrf(KernelBrowser $client): array
    {
        $client->request('GET', '/api/v1/csrf');
        return ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
    }

    private function item(string $kind = 'component', ?string $slug = null): CatalogueItem
    {
        return self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('favorites/'.($slug ?? 'item-'.strtolower((string) new Ulid())), $kind, 'Favorite '.$kind, 'Searchable content', ['preferred']), null)->getItem();
    }

    public function testExplicitRequestsPersistAcrossSessionsAreIdempotentAndIsolatedAndDoNotApplyThemes(): void
    {
        $client = self::createClient(); $owner = $this->account('ROLE_READER'); $other = $this->account();
        $items = array_map($this->item(...), ['component','block','layout']);
        $theme = self::getContainer()->get(ThemeWriter::class)->create(self::getContainer()->get(ThemeFactory::class)->create('favorites/theme-'.strtolower((string) new Ulid())));
        $targets = array_map(fn ($i) => ['catalogue', $i->getId()], $items); $targets[] = ['theme', $theme->getId()];
        $client->request('PUT', '/api/v1/favorites/catalogue/'.$items[0]->getId()); self::assertResponseStatusCodeSame(401);
        $this->signIn($client, $owner); $headers = $this->csrf($client);
        foreach ($targets as [$kind, $id]) {
            $url = '/api/v1/favorites/'.$kind.'/'.$id;
            $client->request('PUT', $url); self::assertResponseStatusCodeSame(403);
            foreach ([1,2] as $_) {
                $client->request('PUT', $url, server: $headers); self::assertResponseIsSuccessful($client->getResponse()->getContent());
                self::assertTrue(json_decode($client->getResponse()->getContent())->favorite);
                self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control')); self::assertStringContainsString('private', $client->getResponse()->headers->get('Cache-Control'));
            }
        }
        $db = self::getContainer()->get(Connection::class);
        self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM personal_favorite WHERE owner_id=?', [$owner->getId()]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM appearance_preference WHERE owner_id=?', [$owner->getId()]));
        $this->signIn($client, $other);
        foreach ($targets as [$kind, $id]) { $client->request('GET', '/api/v1/favorites/'.$kind.'/'.$id); self::assertFalse(json_decode($client->getResponse()->getContent())->favorite); }
        // A second authenticated session sees the same user's persisted choices, never another user's.
        $client->getCookieJar()->clear(); $this->signIn($client, $owner); $headers = $this->csrf($client);
        foreach ($targets as [$kind, $id]) {
            $url = '/api/v1/favorites/'.$kind.'/'.$id;
            $client->request('GET', $url); self::assertTrue(json_decode($client->getResponse()->getContent())->favorite);
            foreach ([1,2] as $_) { $client->request('DELETE', $url, server: $headers); self::assertResponseIsSuccessful(); self::assertFalse(json_decode($client->getResponse()->getContent())->favorite); }
        }
        self::assertSame(0, (int) self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM personal_favorite WHERE owner_id=?', [$owner->getId()]));
    }

    public function testCombinedCatalogueFiltersPaginationReturnLinksAndBothEmptyStates(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner); $headers = $this->csrf($client);
        $prefix = 'page-'.strtolower((string) new Ulid()); $ids = [];
        for ($i = 0; $i < 14; ++$i) $ids[] = $this->item('component', $prefix.'-'.sprintf('%02d', $i))->getId();
        $block = $this->item('block', $prefix.'-block');
        $client->request('GET', '/catalogue', ['favorites' => '1']); self::assertSelectorTextContains('[data-discovery-target="summary"]', '0 items');
        self::assertStringContainsString('No favorites yet.', $client->getResponse()->getContent());
        foreach ([...$ids, $block->getId()] as $id) $client->request('PUT', '/api/v1/favorites/catalogue/'.$id, server: $headers);
        $filters = ['q' => $prefix, 'kind' => 'component', 'sort' => 'name', 'favorites' => '1'];
        $client->request('GET', '/catalogue', $filters);
        self::assertResponseIsSuccessful(); self::assertSelectorCount(12, 'article.card'); self::assertSelectorCount(12, 'button[aria-pressed="true"][data-favorite-target="button"]');
        $next = $client->getCrawler()->selectLink('Next')->link()->getUri(); self::assertStringContainsString('favorites=1', $next); self::assertStringContainsString('kind=component', $next);
        $client->click($client->getCrawler()->selectLink('Next')->link()); self::assertSelectorCount(2, 'article.card');
        $listUrl = $client->getRequest()->getRequestUri(); $client->click($client->getCrawler()->selectLink('Open')->first()->link());
        self::assertSame($listUrl, $client->getCrawler()->selectLink('← Catalogue')->attr('href'));
        $client->request('GET', '/catalogue', [...$filters, 'q' => 'no-matching-'.(string) new Ulid()]);
        self::assertStringContainsString('You have favorites, but none match', $client->getResponse()->getContent());
        $client->request('GET', '/catalogue', ['q' => 'preferred', 'favorites' => '1', 'kind' => 'block']); self::assertSelectorCount(1, 'article.card');
        $returnTo = '/catalogue?'.http_build_query($filters);
        $client->request('GET', '/catalogue/'.$ids[0].'/edit?'.http_build_query(['returnTo' => $returnTo]));
        $client->submitForm('Save draft', ['draft[title]' => 'Favorite retains list context after save']); self::assertResponseStatusCodeSame(303);
        self::assertStringContainsString('returnTo=', $client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        $client->click($client->getCrawler()->selectLink('← favorites/'.$prefix.'-00')->link());
        self::assertSame($returnTo, $client->getCrawler()->selectLink('← Catalogue')->attr('href'));
        $client->request('GET', '/catalogue/'.$ids[0], ['returnTo' => '//evil.test/catalogue']);
        self::assertSame('/catalogue', $client->getCrawler()->selectLink('← Catalogue')->attr('href'));
    }

    public function testThemeVariantsFilterBeforePaginationAndKeepTheirIdentityAfterEditing(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner); $headers = $this->csrf($client);
        $prefix = 'favorites/variants-'.strtolower((string) new Ulid()); $ids = [];
        $factory = self::getContainer()->get(ThemeFactory::class); $writer = self::getContainer()->get(ThemeWriter::class);
        for ($i=0; $i<14; ++$i) {
            $doc = $factory->create($prefix.'-'.$i); $doc->colorScheme = $i === 13 ? 'dark' : 'light';
            $ids[] = $writer->create($doc)->getId();
        }
        foreach ($ids as $id) $client->request('PUT', '/api/v1/favorites/theme/'.$id, server: $headers);
        $client->request('GET', '/themes', ['q' => $prefix, 'mode' => 'light', 'sort' => 'name', 'favorites' => '1']);
        self::assertResponseIsSuccessful($client->getResponse()->getContent()); self::assertSelectorCount(12, 'article.card'); self::assertSelectorTextContains('[data-discovery-target="summary"]', '13 themes');
        $client->click($client->getCrawler()->selectLink('Next')->link()); self::assertSelectorCount(1, 'article.card');
        $client->request('GET', '/themes', ['q' => $prefix, 'mode' => 'dark', 'favorites' => '1']);
        self::assertSelectorCount(1, 'article.card'); self::assertStringContainsString('dark · revision', $client->getResponse()->getContent());
        $client->request('GET', '/api/v1/themes/'.$ids[13]); $theme = json_decode($client->getResponse()->getContent());
        $theme->document->colors->primary = '#345678';
        $client->jsonRequest('PUT', '/api/v1/themes/'.$ids[13], ['document' => $theme->document, 'expectedRevision' => 1], $headers); self::assertResponseIsSuccessful();
        $client->request('GET', '/api/v1/favorites/theme/'.$ids[13]); self::assertTrue(json_decode($client->getResponse()->getContent())->favorite);
        $client->request('GET', '/themes', ['q' => $prefix, 'favorites' => '1', 'mode' => 'dark']); self::assertStringContainsString('revision 2', $client->getResponse()->getContent());
    }

    public function testStableCatalogueRevisionAndDeletionCascadesAndInvalidTargets(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner); $headers = $this->csrf($client); $item = $this->item(); $id = $item->getId();
        $client->request('PUT', '/api/v1/favorites/catalogue/'.$id, server: $headers);
        $client->request('GET', '/api/v1/items/'.$id.'/draft'); $draft = json_decode($client->getResponse()->getContent());
        $client->jsonRequest('PUT', '/api/v1/items/'.$id.'/draft', ['title'=>'Updated favorite', 'manifestData'=>$draft->manifestData, 'sourceMap'=>$draft->sourceMap], [...$headers, 'HTTP_IF_MATCH'=>'"1"']); self::assertResponseIsSuccessful();
        $client->request('GET', '/api/v1/favorites/catalogue/'.$id); self::assertTrue(json_decode($client->getResponse()->getContent())->favorite);
        $em = self::getContainer()->get(EntityManagerInterface::class); $em->remove($em->find(CatalogueItem::class, $id)); $em->flush();
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM personal_favorite WHERE catalogue_item_id=?', [$id]));
        foreach (['GET','PUT','DELETE'] as $method) { $client->request($method, '/api/v1/favorites/catalogue/'.$id, server: $headers); self::assertResponseStatusCodeSame(404); }
        $client->request('PUT', '/api/v1/favorites/theme/'.(string) new Ulid(), server: $headers); self::assertResponseStatusCodeSame(404);
        $em = self::getContainer()->get(EntityManagerInterface::class); $user = $em->find(\App\Entity\User::class, $owner->getId()); $user->disable(); $em->flush();
        $client->request('GET', '/catalogue?favorites=1'); self::assertResponseRedirects('/login');
    }
}
