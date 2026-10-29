<?php

namespace App\Tests\Functional;

use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Font\FontLibrary;
use App\Tests\DatabaseTestCase;
use App\Theme\{ThemeFactory, ThemeWriter};
use Symfony\Component\Uid\Ulid;

final class DiscoveryTest extends DatabaseTestCase
{
    private function csrf($client): array
    {
        $client->request('GET', '/api/v1/csrf');
        return ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
    }

    public function testCatalogueCombinesExactTagsBeforePagingAndFramesRemainPrivateAndCanonical(): void
    {
        $client = self::createClient(); $owner = $this->account(); $other = $this->account('ROLE_READER');
        $this->signIn($client, $owner); $headers = $this->csrf($client);
        $prefix = 'discovery-'.strtolower((string) new Ulid()); $ids = [];
        for ($i = 0; $i < 16; ++$i) {
            $item = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('browser/'.$prefix.'-'.sprintf('%02d', $i), $i === 15 ? 'block' : 'component', 'Discovery item', '', $i === 14 ? ['ui'] : ['ui', 'preferred']), null)->getItem();
            $ids[] = $item->getId();
            if ($i !== 13) $client->request('PUT', '/api/v1/favorites/catalogue/'.$item->getId(), server: $headers);
        }
        $filters = ['q' => $prefix, 'kind' => 'component', 'tags' => 'UI, preferred', 'favorites' => '1', 'sort' => 'name'];
        $client->request('GET', '/catalogue', $filters); self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, 'article.card'); self::assertSelectorTextContains('[data-discovery-target="summary"]', '13 items');
        $direct = $client->getCrawler()->filter('#catalogue-results [data-favorite-id-value]')->extract(['data-favorite-id-value']);
        $client->request('GET', '/catalogue', $filters, server: ['HTTP_TURBO_FRAME' => 'catalogue-results', 'HTTP_X_DISCOVERY_REQUEST' => 'test-1']);
        self::assertResponseIsSuccessful(); self::assertSame($direct, $client->getCrawler()->filter('#catalogue-results [data-favorite-id-value]')->extract(['data-favorite-id-value']));
        self::assertResponseHeaderSame('X-Discovery-Request', 'test-1'); self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store')); self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertStringContainsString('Cookie', $client->getResponse()->headers->get('Vary'));
        self::assertSelectorExists('a[aria-label="Remove filter: Tags: UI, preferred"]');
        $client->clickLink('Next'); self::assertSelectorCount(1, 'article.card');
        $star = $client->getCrawler()->filter('[data-favorite-id-value]')->attr('data-favorite-id-value'); self::assertSame($ids[12], $star);
        $client->request('DELETE', '/api/v1/favorites/catalogue/'.$star, server: $headers); self::assertResponseIsSuccessful();
        $client->request('GET', '/catalogue', [...$filters, 'page' => 2], server: ['HTTP_TURBO_FRAME' => 'catalogue-results']);
        self::assertResponseStatusCodeSame(303); self::assertStringContainsString('page=1', $client->getResponse()->headers->get('Location'));
        $client->followRedirect(); self::assertResponseIsSuccessful(); self::assertSelectorCount(12, 'article.card');
        self::assertSelectorTextContains('[data-discovery-target="summary"]', '12 items · Page 1 of 1');
        $this->signIn($client, $other);
        // Unknown owner parameters never change the authenticated actor or counts.
        $client->request('GET', '/catalogue', [...$filters, 'owner' => $owner->getId(), 'userId' => $owner->getId()], server: ['HTTP_TURBO_FRAME' => 'catalogue-results']);
        self::assertSelectorCount(0, 'article.card'); self::assertSelectorTextContains('[data-discovery-target="summary"]', '0 items');
        $client->request('GET', '/catalogue?tags='.str_repeat('a', 241)); self::assertResponseStatusCodeSame(400);
        $client->getCookieJar()->clear(); $client->request('GET', '/catalogue?favorites=1', server: ['HTTP_TURBO_FRAME' => 'catalogue-results']); self::assertResponseRedirects('/login');
    }

    public function testThemeModeFavoritesAndLastPageUseTheSameResultsForFramesAndDirectVisits(): void
    {
        $client = self::createClient(); $owner = $this->account(); $other = $this->account('ROLE_READER');
        $this->signIn($client, $owner); $headers = $this->csrf($client); $prefix = 'browser/discovery-'.strtolower((string) new Ulid());
        for ($i = 0; $i < 14; ++$i) {
            $document = self::getContainer()->get(ThemeFactory::class)->create($prefix.'-'.sprintf('%02d', $i)); $document->colorScheme = $i === 13 ? 'dark' : 'light';
            $theme = self::getContainer()->get(ThemeWriter::class)->create($document);
            $client->request('PUT', '/api/v1/favorites/theme/'.$theme->getId(), server: $headers);
        }
        $filters = ['q' => $prefix, 'mode' => 'light', 'sort' => 'name', 'favorites' => '1'];
        $client->request('GET', '/themes', $filters); self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, 'article.card'); self::assertSelectorTextContains('[data-discovery-target="summary"]', '13 themes');
        $direct = $client->getCrawler()->filter('#theme-results [data-favorite-id-value]')->extract(['data-favorite-id-value']);
        $client->request('GET', '/themes', $filters, server: ['HTTP_TURBO_FRAME' => 'theme-results']);
        self::assertSame($direct, $client->getCrawler()->filter('#theme-results [data-favorite-id-value]')->extract(['data-favorite-id-value']));
        $client->clickLink('Next'); self::assertSelectorCount(1, 'article.card');
        $id = $client->getCrawler()->filter('[data-favorite-id-value]')->attr('data-favorite-id-value');
        $client->request('DELETE', '/api/v1/favorites/theme/'.$id, server: $headers);
        $client->request('GET', '/themes', [...$filters, 'page' => 2]); self::assertResponseStatusCodeSame(303);
        $client->followRedirect(); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('[data-discovery-target="summary"]', '12 themes · Page 1 of 1');
        $this->signIn($client, $other); $client->request('GET', '/themes', [...$filters, 'owner' => $owner->getId()], server: ['HTTP_TURBO_FRAME' => 'theme-results']);
        self::assertSelectorCount(0, 'article.card'); self::assertSelectorTextContains('[data-discovery-target="summary"]', '0 themes');
    }

    public function testFontGetFiltersValidateAndKeepLicensedSpecimensLazyAndIndependent(): void
    {
        $client = self::createClient(); $client->disableReboot(); $this->signIn($client, $this->account('ROLE_READER'));
        $library = new FontLibrary(sys_get_temp_dir().'/discovery-fonts-'.bin2hex(random_bytes(8)));
        foreach (['inter', 'poppins'] as $family) {
            $directory = dirname(__DIR__, 2).'/fixtures/fonts/'.$family;
            $library->importStaged($directory, json_decode(file_get_contents($directory.'/pin.json'), true));
        }
        self::getContainer()->set(FontLibrary::class, $library);
        $client->request('GET', '/fonts?q=inter&category=sans'); self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#font-results article.card'); self::assertInputValueSame('q', 'inter'); self::assertSelectorExists('select[name="category"] option[value="sans"][selected]');
        self::assertSelectorTextContains('[data-discovery-target="summary"]', '1 family');
        self::assertSelectorCount(2, '#specimen-family option'); self::assertSelectorExists('a[href$=".txt"]');
        self::assertSelectorNotExists('link[rel="preload"][as="font"]');
        $direct = $client->getCrawler()->filter('#font-results')->html();
        $client->request('GET', '/fonts?q=inter&category=sans', server: ['HTTP_TURBO_FRAME' => 'font-results']);
        self::assertSame($direct, $client->getCrawler()->filter('#font-results')->html());
        $client->request('GET', '/fonts?q=inter&category=serif&page=2'); self::assertResponseStatusCodeSame(303);
        $client->followRedirect(); self::assertResponseIsSuccessful(); self::assertSelectorCount(0, '#font-results article.card');
        self::assertSelectorTextContains('#font-results', 'No font families match');
        foreach (['category=unknown', 'page=0', 'q='.str_repeat('x', 121)] as $query) {$client->request('GET', '/fonts?'.$query); self::assertResponseStatusCodeSame(400);}
    }
}
