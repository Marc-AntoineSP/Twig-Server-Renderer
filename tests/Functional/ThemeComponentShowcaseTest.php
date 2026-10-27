<?php

namespace App\Tests\Functional;

use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use App\Theme\ThemeWriter;
use Doctrine\DBAL\Connection;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class ThemeComponentShowcaseTest extends DatabaseTestCase
{
    public function testReaderSeesServerRenderedIsolatedExamplesWithoutDataOrPreferenceChanges(): void
    {
        $client = self::createClient();$this->signIn($client, $this->account('ROLE_READER'));
        $tokens = self::getContainer()->get(ThemeFactory::class)->create('showcase/test-'.strtolower((string) new Ulid()));
        $tokens->colors->primary = '#512F79';
        $theme = self::getContainer()->get(ThemeWriter::class)->create($tokens);
        $client->request('GET', '/');self::assertResponseIsSuccessful();
        $db = self::getContainer()->get(Connection::class);
        $before = $this->state($db);
        $client->request('GET', '/themes/'.$theme->getId());self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[data-action="appearance#applyTheme"]');
        self::assertSelectorExists('nav[aria-label="Showcase theme variant"] a[aria-current="page"]');
        self::assertSelectorNotExists('a[href$="/edit"]');
        $frame = $client->getCrawler()->filter('iframe[title="Theme component showcase"]');
        self::assertSame('allow-scripts', $frame->attr('sandbox'));
        $html = $frame->attr('srcdoc');$examples = new Crawler($html);
        self::assertCount(65, $examples->filter('[data-showcase-card]'));
        self::assertCount(7, $examples->filter('section[data-category]'));
        self::assertSame($tokens->cssName, $examples->filter('html')->attr('data-theme'));
        self::assertSame('light', $examples->filter('html')->attr('data-color-mode'));
        self::assertStringContainsString('--color-primary:#512F79', $html);
        self::assertStringContainsString('connect-src &#039;none&#039;', $html);
        self::assertCount(0, $examples->filter('form, script[src], link[href], a[href^="http"], .theme-controller'));
        foreach ($examples->filter('img') as $image) self::assertStringStartsWith('data:image/', $image->getAttribute('src'));
        $ids = $examples->filter('[id]')->each(static fn (Crawler $node) => $node->attr('id'));
        self::assertSame($ids, array_values(array_unique($ids)), 'Example IDs must stay unique.');
        foreach (['priority' => 8, 'rating-stars' => 5, 'rating-hearts' => 5] as $suffix => $count) self::assertCount($count, $examples->filter('input[name$="-'.$suffix.'"]'));
        foreach (['actions','forms','navigation','data','feedback','layout','mockups'] as $category) self::assertGreaterThan(0, $examples->filter('section[data-category="'.$category.'"] [data-showcase-card]')->count());
        self::assertSame($before, $this->state($db));
    }

    public function testDetailPreviewRemainsPrivate(): void
    {
        $client = self::createClient();$client->request('GET', '/themes/01AAAAAAAAAAAAAAAAAAAAAAAA');
        self::assertResponseRedirects('http://localhost/login');
    }

    private function state(Connection $db): array
    {
        $state = [];
        foreach (['theme','catalogue_item','draft','theme_release','renderer_preset','appearance_snapshot','appearance_preference'] as $table) $state[$table] = $db->fetchAllAssociative('SELECT * FROM '.$table);
        return $state;
    }
}
