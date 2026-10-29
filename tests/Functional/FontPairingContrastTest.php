<?php

namespace App\Tests\Functional;

use App\Font\FontLibrary;
use App\Tests\DatabaseTestCase;
use App\Theme\Contrast;
use App\Theme\ThemeFactory;
use Doctrine\DBAL\Connection;

final class FontPairingContrastTest extends DatabaseTestCase
{
    public function testDiagnosticsRequireEditorCsrfAndDoNotWriteThemesReleasesOrAppearance(): void
    {
        $client = self::createClient(); $this->signIn($client, $this->account());
        $client->request('GET', '/api/v1/csrf');
        $headers = ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
        $doc = self::getContainer()->get(ThemeFactory::class)->create();
        $typography = (object) ['bodyFontId' => 'system-serif', 'headingFontId' => 'system-sans', 'codeFontId' => 'system-mono', 'bodyWeight' => 400, 'headingWeight' => 700, 'codeWeight' => 400, 'bodyStyle' => 'normal', 'headingStyle' => 'normal', 'codeStyle' => 'normal'];
        $before = $this->state();
        $client->jsonRequest('POST', '/api/v1/themes/contrast', ['colors' => $doc->colors]);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/api/v1/themes/contrast', ['colors' => $doc->colors], $headers);
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertSame((new Contrast())->ratios($doc), array_map(static fn ($row) => $row['ratio'], $result['results']));
        self::assertStringContainsString('example-primary', $result['html']);
        self::assertStringContainsString("connect-src 'none'", htmlspecialchars_decode($result['html']));
        $doc->colors->primary = 'rgba(0,0,0,.5)';
        $client->jsonRequest('POST', '/api/v1/themes/contrast', ['colors' => $doc->colors], $headers);
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertNull($result['results']['primary']['ratio']);
        self::assertStringNotContainsString('rgba(', $result['html']);
        $client->jsonRequest('POST', '/api/v1/themes/contrast', ['colors' => (object) ['primary' => str_repeat('x', 101)]], $headers);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $typography], $headers);
        self::assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('system-serif', $result['roles']['body']['family']);
        self::assertStringContainsString('data-font-role="code"', $result['html']);
        self::assertStringContainsString('font-synthesis:none', $result['html']);
        self::assertSame($before, $this->state());
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $typography], $headers);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/api/v1/themes/contrast', ['colors' => $doc->colors], $headers);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPairingEmbedsOnlyRequestedRealFacesAndSubsetsAndEscapesText(): void
    {
        $client = self::createClient(); $this->signIn($client, $this->account());
        $client->disableReboot();
        $root = sys_get_temp_dir().'/pairing-test-'.bin2hex(random_bytes(8));
        $library = new FontLibrary($root);
        $directory = dirname(__DIR__, 2).'/fixtures/fonts/inter';
        $library->importStaged($directory, json_decode(file_get_contents($directory.'/pin.json'), true));
        $client->request('GET', '/api/v1/csrf'); $headers = ['HTTP_X_CSRF_TOKEN' => json_decode($client->getResponse()->getContent())->token];
        self::getContainer()->set(FontLibrary::class, $library);
        $type = ['bodyFontId' => 'inter', 'headingFontId' => 'inter', 'codeFontId' => 'system-mono', 'bodyWeight' => 472, 'headingWeight' => 631, 'codeWeight' => 400, 'bodyStyle' => 'italic', 'headingStyle' => 'normal', 'codeStyle' => 'normal'];
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $type, 'text' => '<img src=x> 漢'], $headers);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(4, $result['fonts'][0]['faces'], 'Transfer receives the full installed definition, independently of preview subset pruning.');
        self::assertContains('U+6F22', $result['roles']['body']['missing']);
        self::assertStringContainsString('&lt;img src=x&gt;', $result['html']);
        self::assertStringNotContainsString('<img src=x>', $result['html']);
        preg_match_all('~data:font/woff2;base64,([A-Za-z0-9+/=]+)~', $result['html'], $matches);
        $hashes = array_unique(array_map(static fn ($encoded) => hash('sha256', base64_decode($encoded)), $matches[1])); sort($hashes);
        $expected = array_unique(array_column([...$result['roles']['body']['faces'], ...$result['roles']['heading']['faces']], 'sha256')); sort($expected);
        self::assertNotEmpty($expected); self::assertSame($expected, $hashes);
        $type['bodyWeight'] = 950;
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $type], $headers);
        self::assertResponseStatusCodeSame(422); self::assertStringContainsString('no real face', $client->getResponse()->getContent());
        $type['bodyFontId'] = 'missing-family'; $type['bodyWeight'] = 400;
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $type], $headers);
        self::assertResponseStatusCodeSame(422); self::assertStringContainsString('not installed', $client->getResponse()->getContent());
        // Remove only this test's private temporary font blob, never the installed library.
        unlink($root.'/blobs/'.$result['roles']['body']['faces'][0]['sha256'].'.woff2');
        $type['bodyFontId'] = 'inter'; $type['bodyWeight'] = 472;
        $client->jsonRequest('POST', '/api/v1/fonts/pairing', ['typography' => $type], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('No pairing preview was loaded', $client->getResponse()->getContent());
        self::assertStringNotContainsString('last good preview', $client->getResponse()->getContent());
    }

    private function state(): array
    {
        $db = self::getContainer()->get(Connection::class); $state = [];
        foreach (['theme' => 'id', 'theme_release' => 'id', 'appearance_preference' => 'owner_id', 'renderer_preset' => 'id'] as $table => $key) $state[$table] = $db->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY '.$key);
        return $state;
    }
}
