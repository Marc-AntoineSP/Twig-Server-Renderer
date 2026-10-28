<?php

namespace App\Tests\Functional;

use App\Tests\DatabaseTestCase;

final class ShellTest extends DatabaseTestCase
{
    public function testNavigationContextAndAccountActionsRespectRoles(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account('ROLE_READER'));
        foreach (['/' => 'Workspace', '/catalogue' => 'Catalogue', '/themes' => 'Themes', '/fonts' => 'Fonts', '/profile' => 'Profile'] as $url => $label) {
            $client->request('GET', $url); self::assertResponseIsSuccessful();
            self::assertSelectorExists('header a[aria-current="page"]');
            self::assertStringContainsString($label, $client->getCrawler()->filter('header a[aria-current="page"]')->first()->text());
            self::assertSelectorExists('a[href="#main"]');
            self::assertSelectorExists('a[href="#appearance-recovery"]');
            self::assertSelectorExists('summary[aria-controls="account-options"]');
            self::assertSelectorNotExists('#account-options a[href="/registry-tokens"]');
        }
        $this->signIn($client, $this->account('ROLE_ADMIN'));
        $client->request('GET', '/registry-tokens');
        self::assertSelectorExists('#account-options a[aria-current="page"][href="/registry-tokens"]');
    }

    public function testRecoveryFormRequiresCsrfAndResetsOnlyTheCurrentPreferenceWithoutJavascript(): void
    {
        $client = self::createClient(); $owner = $this->account('ROLE_READER');
        $this->signIn($client, $owner);
        $client->request('GET', '/');
        $client->request('POST', '/appearance/reset'); self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/');
        $client->submit($client->getCrawler()->filter('#appearance-recovery form')->form());
        self::assertResponseRedirects('/', 303);
        $client->request('GET', '/api/v1/appearance');
        $state = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('system', $state['mode']);
        self::assertSame('Studio default', $state['familyName']);
        self::assertSame(['light', 'dark'], array_keys($state['variants']));
        $this->signIn($client, $this->account('ROLE_READER'));
        $client->request('GET', '/api/v1/appearance');
        self::assertSame(0, json_decode($client->getResponse()->getContent(), true)['revision']);
    }

    public function testGuestRecoveryFormUsesTheExistingModeCookieWithoutCatalogueAccess(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        self::assertSelectorNotExists('#account-options');
        $client->submit($client->getCrawler()->filter('#appearance-recovery form')->form());
        self::assertResponseRedirects('/login', 303);
        $client->request('GET', '/api/v1/appearance');
        $state = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('system', $state['mode']);
        self::assertSame('guest', $state['scope']);
        $client->request('GET', '/catalogue'); self::assertResponseRedirects('/login');
    }
}
