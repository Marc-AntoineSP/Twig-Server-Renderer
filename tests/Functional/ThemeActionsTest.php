<?php

namespace App\Tests\Functional;

use App\Entity\Theme;
use App\Rendering\RendererPresets;
use App\Rendering\RendererSettings;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use App\Theme\ThemeSample;
use App\Theme\ThemeWriter;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Ulid;

final class ThemeActionsTest extends DatabaseTestCase
{
    public function testDraftBrowsingAndPublishedPreviewIdentifyDifferentSourcesWithoutWrites(): void
    {
        $client = self::createClient(); $this->signIn($client, $this->account());
        $theme = $this->theme();
        $release = self::getContainer()->get(ThemeWriter::class)->publish($theme, 1);
        $document = $theme->getDocument(); $document->colors->primary = '#ABCDEF'; $document->version = '1.1.0';
        self::getContainer()->get(ThemeWriter::class)->save($theme, 1, $document);
        $client->request('GET', '/'); // Initialize the existing built-in family before measuring reads.
        $before = $this->state();
        $client->request('GET', '/themes?q='.urlencode($theme->getName()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-theme-actions]', 'Draft revision 2');
        self::assertSelectorTextContains('[data-theme-actions]', 'Published preview: '.$theme->getName().'@1.0.0 · light');
        self::assertSelectorExists('input[name="releaseId"][value="'.$release->getId().'"]');
        self::assertSelectorTextContains('button[data-action="appearance#applyTheme"]', 'Apply to application');
        $link = $client->getCrawler()->selectLink('Browse component showcase')->link();
        self::assertStringEndsWith('#component-showcase', $link->getUri());
        $client->click($link);
        self::assertSelectorTextContains('[data-showcase-revision]', 'Draft revision 2 · '.$theme->getName().' · light');
        $css = $client->getCrawler()->filter('link[data-showcase-stylesheet]')->attr('href');
        $client->request('GET', $css);
        self::assertStringContainsString('--color-primary:#ABCDEF', $client->getResponse()->getContent());
        self::assertSame($before, $this->state(), 'Gallery, detail and CSS reads must not save either preference.');
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->request('GET', '/themes/'.$theme->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href$="#component-showcase"]');
        self::assertSelectorNotExists('form[action$="/apply"]');
        self::assertSelectorTextContains('[data-theme-actions]', 'Editor access is required');
    }

    public function testExplicitReleaseStaysPinnedAndLegacyPostStillSelectsLatest(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner);
        $theme = $this->theme('dark');
        $writer = self::getContainer()->get(ThemeWriter::class);
        $first = $writer->publish($theme, 1);
        $item = self::getContainer()->get(ThemeSample::class)->item();
        self::getContainer()->get(RendererPresets::class)->save($item, $owner, new RendererSettings(width: 375, scripts: false, codeFont: 'system-mono'));
        $client->request('GET', '/themes/'.$theme->getId());
        $form = $client->getCrawler()->selectButton('Preview published theme in component editor')->form();
        self::assertSame($first->getId(), $form['releaseId']->getValue());
        $theme = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->find(Theme::class, $theme->getId());
        $document = $theme->getDocument(); $document->version = '2.0.0'; $document->colors->primary = '#FEDCBA';
        $writer = self::getContainer()->get(ThemeWriter::class);
        $writer->save($theme, 1, $document); $second = $writer->publish($theme, 2);
        $before = $this->state();
        $client->submit($form);
        self::assertResponseRedirects('/catalogue/'.$item->getId().'/edit', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[data-flash]', $theme->getName().'@1.0.0 (dark)');
        self::assertSelectorExists('iframe[sandbox="allow-scripts"]');
        $client->request('GET', '/api/v1/items/'.$item->getId().'/renderer-preset');
        $settings = json_decode($client->getResponse()->getContent());
        self::assertSame($first->getId(), $settings->themeReleaseId);
        self::assertSame(375, $settings->width); self::assertFalse($settings->scripts);
        self::assertSame('system-mono', $settings->codeFont); self::assertSame('theme', $settings->bodyFont);
        self::assertSame($before['appearance_preference'], $this->state()['appearance_preference']);
        self::assertSame($before['theme'], $this->state()['theme']);
        $client->request('GET', '/themes/'.$theme->getId());
        self::assertSelectorExists('input[name="releaseId"][value="'.$second->getId().'"]');
        $token = $client->getCrawler()->filter('form[action$="/apply"] input[name="_token"]')->attr('value');
        $client->request('POST', '/themes/'.$theme->getId().'/apply', ['_token' => $token]);
        self::assertResponseStatusCodeSame(303);
        $client->request('GET', '/api/v1/items/'.$item->getId().'/renderer-preset');
        self::assertSame($second->getId(), json_decode($client->getResponse()->getContent())->themeReleaseId);
        $this->signIn($client, $this->account());
        $client->request('GET', '/api/v1/items/'.$item->getId().'/renderer-preset');
        self::assertNull(json_decode($client->getResponse()->getContent())->themeReleaseId);
    }

    public function testDraftOnlyMissingAndWrongThemeReleaseCannotWriteAndPermissionsRemain(): void
    {
        $client = self::createClient(); $this->signIn($client, $this->account());
        $theme = $this->theme(); $other = $this->theme();
        $release = self::getContainer()->get(ThemeWriter::class)->publish($other, 1);
        $client->request('GET', '/themes/'.$theme->getId());
        self::assertSelectorExists('[data-theme-actions] button[disabled]');
        self::assertSelectorTextContains('[data-theme-actions]', 'No published release');
        self::assertSelectorTextContains('[data-theme-actions]', 'Use Edit theme');
        self::assertSelectorExists('a[href$="#component-showcase"]');
        // Obtain the same fixed-action token as a published form without adding an unusable form to a draft card.
        $client->request('GET', '/themes/'.$other->getId());
        $token = $client->getCrawler()->filter('form[action$="/apply"] input[name="_token"]')->attr('value');
        $before = $this->state(); $url = '/themes/'.$theme->getId().'/apply';
        $client->request('GET', $url); self::assertResponseStatusCodeSame(405);
        $client->request('POST', $url); self::assertResponseStatusCodeSame(403);
        foreach ([null, (string) new Ulid(), $release->getId()] as $releaseId) {
            $client->request('POST', $url, ['_token' => $token, ...($releaseId ? ['releaseId' => $releaseId] : [])]);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('[role="alert"]', 'No renderer settings or application appearance changed');
            self::assertSelectorExists('a[href="/themes/'.$theme->getId().'#component-showcase"]');
        }
        self::assertSame($before, $this->state());
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->request('POST', $url, ['_token' => $token, 'releaseId' => $release->getId()]);
        self::assertResponseStatusCodeSame(403);
        $client->getCookieJar()->clear();
        $client->request('POST', $url, ['_token' => $token]);
        self::assertResponseRedirects('http://localhost/login');
    }

    private function theme(string $mode = 'light'): Theme
    {
        $document = self::getContainer()->get(ThemeFactory::class)->create('actions/test-'.strtolower((string) new Ulid()));
        $document->colorScheme = $mode;
        return self::getContainer()->get(ThemeWriter::class)->create($document);
    }

    private function state(): array
    {
        $db = self::getContainer()->get(Connection::class); $state = [];
        foreach (['theme' => 'id', 'theme_release' => 'id', 'catalogue_item' => 'id', 'draft' => 'item_id', 'appearance_preference' => 'owner_id', 'renderer_preset' => 'id'] as $table => $key) $state[$table] = $db->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY '.$key);
        return $state;
    }
}
