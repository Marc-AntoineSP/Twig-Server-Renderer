<?php

namespace App\Tests\Functional;

use App\Entity\AppearancePreference;
use App\Entity\Theme;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Ulid;

final class AppearanceTest extends DatabaseTestCase
{
    private KernelBrowser $browser;
    private array $headers;
    private int $sequence = 0;
    protected function setUp(): void
    {
        parent::setUp(); $this->browser = self::createClient(); $this->signIn($this->browser, $this->account());
        $this->browser->request('GET', '/api/v1/csrf');
        $this->headers = ['HTTP_X_CSRF_TOKEN' => json_decode($this->browser->getResponse()->getContent())->token];
    }
    private function document(): \stdClass { return self::getContainer()->get(ThemeFactory::class)->create('appearance/test-'.strtolower((string) new Ulid())); }
    private function family(string $variants = 'pair'): array
    {
        $this->browser->jsonRequest('POST', '/api/v1/themes', ['document' => $this->document(), 'variants' => $variants], $this->headers);
        self::assertResponseStatusCodeSame(201, $this->browser->getResponse()->getContent());
        return json_decode($this->browser->getResponse()->getContent(), true);
    }
    private function read(): array
    {
        $this->browser->request('GET', '/api/v1/appearance'); self::assertResponseIsSuccessful();
        return json_decode($this->browser->getResponse()->getContent(), true);
    }
    private function prepare(array $input): array
    {
        $this->browser->jsonRequest('POST', '/api/v1/appearance/prepare', [...$input, 'clientId' => str_repeat('a', 32), 'sequence' => ++$this->sequence], $this->headers);
        self::assertResponseIsSuccessful($this->browser->getResponse()->getContent());
        return json_decode($this->browser->getResponse()->getContent(), true);
    }
    private function commit(array $prepared, int $revision): array
    {
        $this->browser->jsonRequest('POST', '/api/v1/appearance/commit', ['clientId' => str_repeat('a', 32), 'sequence' => $this->sequence, 'token' => $prepared['token'], 'expectedRevision' => $revision], $this->headers);
        self::assertResponseIsSuccessful($this->browser->getResponse()->getContent());
        return json_decode($this->browser->getResponse()->getContent(), true);
    }

    public function testPrepareIsReadOnlyForPreferencesAndApplicationSnapshotsStayImmutableAcrossThemeEdits(): void
    {
        $initial = $this->read(); $family = $this->family();
        self::assertCount(2, $family['family']['variants']);
        $prepared = $this->prepare(['kind' => 'family', 'familyId' => $family['family']['id'], 'mode' => 'dark']);
        self::assertSame($initial, $this->read());
        $state = $this->commit($prepared, $initial['revision']);
        self::assertSame('dark', $state['mode']);
        $url = $state['variants']['light']['url'];
        $this->browser->request('GET', $url); self::assertResponseIsSuccessful();
        $css = $this->browser->getResponse()->getContent(); self::assertStringContainsString('--color-primary:#FFA100', $css);
        self::assertStringNotContainsString('@plugin', $css); self::assertStringNotContainsString('@import', $css);
        self::assertSame($state['variants']['light']['cssHash'], hash('sha256', $css));
        $document = json_decode(json_encode($family['document'])); $document->colors->primary = '#123456';
        $this->browser->jsonRequest('PUT', '/api/v1/themes/'.$family['id'], ['expectedRevision' => 1, 'document' => $document], $this->headers); self::assertResponseIsSuccessful();
        self::assertSame($state, $this->read());
        $this->browser->request('GET', $url); self::assertSame($css, $this->browser->getResponse()->getContent());
        $updated = $this->commit($this->prepare(['kind' => 'family', 'themeId' => $family['id']]), $state['revision']);
        self::assertNotSame($state['variants']['light']['id'], $updated['variants']['light']['id']);
        self::assertSame($state['variants']['dark']['id'], $updated['variants']['dark']['id']);
        self::assertSame('dark', $updated['mode']);
    }

    public function testPersonalPreferencesCannotMutateAnotherAccountAndReadersCanSelectTheirOwnAppearance(): void
    {
        $family = $this->family(); $state = $this->commit($this->prepare(['kind' => 'family', 'themeId' => $family['id']]), 0);
        $other = $this->account('ROLE_READER'); $this->signIn($this->browser, $other);
        self::assertTrue($this->read()['default']);
        $this->browser->request('GET', '/api/v1/csrf'); $this->headers = ['HTTP_X_CSRF_TOKEN' => json_decode($this->browser->getResponse()->getContent())->token];
        $own = $this->commit($this->prepare(['kind' => 'family', 'familyId' => $family['family']['id'], 'mode' => 'light']), 0);
        self::assertSame('light', $own['mode']); self::assertNotSame($state['scope'], $own['scope']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertCount(2, $em->getRepository(AppearancePreference::class)->findBy(['family' => $family['family']['id']]));
    }

    public function testSequenceFencingCsrfAndOptimisticPreferenceRevisionRejectObsoleteWrites(): void
    {
        $family = $this->family();
        $this->browser->jsonRequest('POST', '/api/v1/appearance/prepare', ['kind' => 'default']); self::assertResponseStatusCodeSame(403);
        $first = $this->prepare(['kind' => 'family', 'themeId' => $family['id']]); $oldSequence = $this->sequence;
        $latest = $this->prepare(['kind' => 'default']);
        $this->browser->jsonRequest('POST', '/api/v1/appearance/commit', ['clientId' => str_repeat('a', 32), 'sequence' => $oldSequence, 'token' => $first['token'], 'expectedRevision' => 0], $this->headers); self::assertResponseStatusCodeSame(409);
        $state = $this->commit($latest, 0);
        $fresh = $this->prepare(['kind' => 'mode', 'mode' => 'dark']);
        $this->browser->jsonRequest('POST', '/api/v1/appearance/commit', ['clientId' => str_repeat('a', 32), 'sequence' => $this->sequence, 'token' => $fresh['token'], 'expectedRevision' => 0], $this->headers); self::assertResponseStatusCodeSame(412);
        self::assertSame($state, $this->read());
    }

    public function testTrialStylesAreOwnerOnlyBoundedAndRejectTokenInjectionWithoutSavingAnything(): void
    {
        $initial = $this->read(); $document = $this->document();
        $this->browser->jsonRequest('POST', '/api/v1/appearance/trial', ['document' => $document], $this->headers); self::assertResponseIsSuccessful();
        $trial = json_decode($this->browser->getResponse()->getContent(), true); self::assertTrue($trial['trial']);
        $url = $trial['variants']['light']['url'];
        self::assertSame($initial, $this->read());
        $this->browser->request('GET', $url); self::assertResponseIsSuccessful(); self::assertTrue($this->browser->getResponse()->headers->hasCacheControlDirective('no-store')); self::assertTrue($this->browser->getResponse()->headers->hasCacheControlDirective('private'));
        $document->colors->primary = '#FFFFFF;}body{display:none}';
        $this->browser->jsonRequest('POST', '/api/v1/appearance/trial', ['document' => $document], $this->headers); self::assertResponseStatusCodeSame(422);
        self::assertSame($initial, $this->read());
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->getRepository(Theme::class)->findOneBy(['name' => $document->name]));
        $this->signIn($this->browser, $this->account()); $this->browser->request('GET', $url); self::assertResponseStatusCodeSame(404);
        $this->browser->restart(); $this->browser->request('GET', $url); self::assertResponseStatusCodeSame(403);
    }

    public function testSingleVariantKeepsRequestedModeAndMissingThemeRecoversToDefaults(): void
    {
        $family = $this->family('single');
        $state = $this->commit($this->prepare(['kind' => 'family', 'themeId' => $family['id'], 'mode' => 'dark']), 0);
        self::assertSame('dark', $state['mode']); self::assertArrayHasKey('light', $state['variants']); self::assertArrayNotHasKey('dark', $state['variants']);
        $em = self::getContainer()->get(EntityManagerInterface::class); $em->remove($em->find(Theme::class, $family['id'])); $em->flush();
        $recovered = $this->read(); self::assertTrue($recovered['default']); self::assertSame('system', $recovered['mode']); self::assertArrayHasKey('notice', $recovered);
        self::assertCount(2, $recovered['variants']);
    }

    public function testInitialHtmlIncludesBothPrivateRuntimeSheetsAndEarlySystemBootstrap(): void
    {
        $state = $this->read(); $this->browser->request('GET', '/'); self::assertResponseIsSuccessful();
        self::assertSelectorExists('script[src*="appearance_bootstrap"]'); self::assertSelectorCount(2, 'link[data-appearance-sheet]');
        self::assertSelectorCount(2, 'link[data-appearance-sheet][integrity^="sha256-"]');
        self::assertSelectorExists('#appearance-recovery button[type="submit"]'); self::assertSelectorExists('html[data-color-mode]');
        self::assertTrue($this->browser->getResponse()->headers->hasCacheControlDirective('no-store')); self::assertTrue($this->browser->getResponse()->headers->hasCacheControlDirective('private'));
        $this->browser->request('GET', $state['variants']['light']['url']); self::assertResponseIsSuccessful();
        self::assertFalse($this->browser->getResponse()->isCacheable() && $this->browser->getResponse()->headers->hasCacheControlDirective('public'));
    }

    public function testGuestCookieIsValidatedAndCannotOverrideTheAuthenticatedPreference(): void
    {
        $this->browser->restart(); $this->browser->request('GET', '/login'); self::assertResponseIsSuccessful();
        $this->headers = ['HTTP_X_CSRF_TOKEN' => $this->browser->getCrawler()->filter('body')->attr('data-appearance-csrf-value')];
        $guest = $this->commit($this->prepare(['kind' => 'mode', 'mode' => 'dark']), 0); self::assertSame('dark', $guest['mode']);
        self::assertSame('dark', $this->read()['mode']);
        $this->signIn($this->browser, $this->account()); self::assertSame('system', $this->read()['mode']);
        $this->browser->restart();
        $this->browser->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('studio_appearance', 'dark.corrupt'));
        self::assertSame('system', $this->read()['mode']);
    }

    public function testRawCssUnregisteredFontsAndInvalidAssetHashesAreRejected(): void
    {
        $initial = $this->read(); $document = $this->document();
        $this->browser->jsonRequest('POST', '/api/v1/appearance/trial', ['document' => $document, 'css' => 'body{display:none}'], $this->headers); self::assertResponseStatusCodeSame(422);
        $document->typography->bodyFontId = 'missing-font';
        $this->browser->jsonRequest('POST', '/api/v1/appearance/trial', ['document' => $document], $this->headers); self::assertResponseStatusCodeSame(422);
        self::assertSame($initial, $this->read());
        $url = $initial['variants']['light']['url']; $url = substr($url, 0, -68).str_repeat('0', 64).'.css';
        $this->browser->request('GET', $url); self::assertResponseStatusCodeSame(404);
    }
}
