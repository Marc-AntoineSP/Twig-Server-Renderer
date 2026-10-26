<?php

namespace App\Tests\Functional;

use App\Tests\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Ulid;

final class CatalogueTest extends DatabaseTestCase
{
    private function csrf(KernelBrowser $client): string
    {
        $client->request('GET', '/api/v1/csrf');
        self::assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true, 32, JSON_THROW_ON_ERROR)['token'];
    }

    private function create(KernelBrowser $client, string $token, string $title = 'Example component', array $tags = []): \stdClass
    {
        $client->jsonRequest('POST', '/api/v1/items', ['name' => 'tests/item-'.strtolower((string) new Ulid()), 'kind' => 'component', 'title' => $title, 'tags' => $tags], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(201, $client->getResponse()->getContent());

        return json_decode($client->getResponse()->getContent(), false, 32, JSON_THROW_ON_ERROR);
    }

    private function input(\stdClass $draft): array
    {
        return ['title' => $draft->title, 'description' => $draft->description, 'tags' => $draft->tags, 'manifestData' => $draft->manifestData, 'sourceMap' => $draft->sourceMap];
    }

    public function testCreateEditReloadAndSearchThroughForms(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account());
        $name = 'tests/form-'.strtolower((string) new Ulid());
        $client->request('GET', '/catalogue/new');
        $client->submitForm('Create draft', ['new_item[name]' => $name, 'new_item[kind]' => 'component', 'new_item[title]' => 'Original form title', 'new_item[tagsText]' => 'ui, saved']);
        self::assertResponseStatusCodeSame(303, $client->getResponse()->getContent());
        $location = $client->getResponse()->headers->get('Location');
        $id = explode('/', $location)[2];
        $client->followRedirect();
        self::assertSelectorExists('input[name="draft[revision]"][value="1"]');
        $json = $client->getCrawler()->filter('#draft_sourceMapJson')->text();
        $sources = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        $path = 'templates/catalogue/'.$name.'/component.html.twig';
        $source = '<div>{{ app.secret }}</div><script>window.studioExecuted = true;</script>';
        $sources->$path = $source;
        $client->submitForm('Save draft', ['draft[title]' => 'Saved form title', 'draft[tagsText]' => 'ui, persisted', 'draft[sourceMapJson]' => json_encode($sources, JSON_THROW_ON_ERROR)]);
        self::assertResponseStatusCodeSame(303, $client->getResponse()->getContent());
        $client->followRedirect();
        self::assertSelectorExists('input[name="draft[revision]"][value="2"]');
        $client->request('GET', '/api/v1/items/'.$id.'/draft');
        $saved = json_decode($client->getResponse()->getContent(), false, 32, JSON_THROW_ON_ERROR);
        self::assertSame($source, $saved->sourceMap->$path);
        self::assertSame('Saved form title', $saved->title);
        self::assertSame(['ui', 'persisted'], $saved->tags);
        self::assertSame(2, $saved->revision);
        $client->request('GET', '/catalogue/'.$id);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('<script>window.studioExecuted', $client->getResponse()->getContent());
        self::assertStringContainsString('&lt;script&gt;', $client->getResponse()->getContent());
        $client->request('GET', '/catalogue', ['q' => $name]);
        self::assertSelectorTextContains('article', 'Saved form title');
    }

    public function testStaleApiAndFormSavesReturn412AndPreserveBothInputs(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account());
        $token = $this->csrf($client);
        $draft = $this->create($client, $token);
        $path = '/api/v1/items/'.$draft->id.'/draft';
        $client->request('GET', '/catalogue/'.$draft->id.'/edit');
        $stale = $client->getCrawler()->selectButton('Save draft')->form(['draft[title]' => 'My unsaved second edit']);
        $first = $this->input($draft);
        $first['title'] = 'First edit wins';
        $client->jsonRequest('PUT', $path, $first, ['HTTP_X_CSRF_TOKEN' => $token, 'HTTP_IF_MATCH' => '"1"']);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        self::assertResponseHeaderSame('ETag', '"2"');
        $second = $this->input($draft);
        $second['title'] = 'Stale API edit';
        $client->jsonRequest('PUT', $path, $second, ['HTTP_X_CSRF_TOKEN' => $token, 'HTTP_IF_MATCH' => '"1"']);
        self::assertResponseStatusCodeSame(412, $client->getResponse()->getContent());
        self::assertSame('revision_mismatch', json_decode($client->getResponse()->getContent(), true)['code']);
        $client->submit($stale);
        self::assertResponseStatusCodeSame(412);
        self::assertSelectorExists('input[name="draft[title]"][value="My unsaved second edit"]');
        $client->request('GET', $path);
        $saved = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('First edit wins', $saved['title']);
        self::assertSame(2, $saved['revision']);
    }

    public function testAnonymousAndReadersCannotMutateAndEditorsNeedCsrf(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/api/v1/items', ['name' => 'tests/denied', 'kind' => 'component', 'title' => 'Denied']);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasHeader('X-Request-Id');
        $reader = $this->account('ROLE_READER');
        $this->signIn($client, $reader);
        $client->request('GET', '/catalogue');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/catalogue/new"]');
        $client->request('GET', '/catalogue/new');
        self::assertResponseStatusCodeSame(403);
        $token = $this->csrf($client);
        $client->jsonRequest('POST', '/api/v1/items', ['name' => 'tests/denied', 'kind' => 'component', 'title' => 'Denied'], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(403);
        $this->signIn($client, $this->account('ROLE_ADMIN'));
        $token = $this->csrf($client);
        $draft = $this->create($client, $token);
        $client->jsonRequest('PUT', '/api/v1/items/'.$draft->id.'/draft', $this->input($draft), ['HTTP_IF_MATCH' => '"1"']);
        self::assertResponseStatusCodeSame(403);
        $this->signIn($client, $reader);
        $client->jsonRequest('PUT', '/api/v1/items/'.$draft->id.'/draft', $this->input($draft), ['HTTP_X_CSRF_TOKEN' => $this->csrf($client), 'HTTP_IF_MATCH' => '"1"']);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/catalogue/'.$draft->id.'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMissingRevisionDuplicateNameMalformedAndInvalidPayloads(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account());
        $token = $this->csrf($client);
        $draft = $this->create($client, $token);
        $client->jsonRequest('POST', '/api/v1/items', ['name' => $draft->name, 'kind' => 'component', 'title' => 'Duplicate'], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(409);
        $client->jsonRequest('PUT', '/api/v1/items/'.$draft->id.'/draft', $this->input($draft), ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(428);
        $client->jsonRequest('POST', '/api/v1/items', ['name' => '../bad', 'kind' => 'unknown', 'title' => ''], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/api/v1/items', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: '{broken');
        self::assertResponseStatusCodeSame(400);
    }

    public function testEmptyObjectsSurviveJsonbAndSourceInventoryIsRegenerated(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account());
        $token = $this->csrf($client);
        $draft = $this->create($client, $token);
        $input = $this->input($draft);
        $input['manifestData']->defaults = new \stdClass();
        $input['sourceMap']->{'meta/props.schema.json'} = '{"type":"object","properties":{},"additionalProperties":false}';
        $input['sourceMap']->{'meta/examples.json'} = '[{"name":"Empty","props":{}}]';
        $client->jsonRequest('PUT', '/api/v1/items/'.$draft->id.'/draft', $input, ['HTTP_X_CSRF_TOKEN' => $token, 'HTTP_IF_MATCH' => '"1"']);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        $client->request('GET', '/api/v1/items/'.$draft->id.'/draft');
        $saved = json_decode($client->getResponse()->getContent(), false);
        self::assertInstanceOf(\stdClass::class, $saved->manifestData->defaults);
        foreach ($saved->manifestData->files as $file) {
            self::assertSame(hash('sha256', $saved->sourceMap->{$file->path}), $file->sha256);
        }
    }

    public function testDatabaseSearchPaginationKindAndTags(): void
    {
        $client = self::createClient();
        $this->signIn($client, $this->account());
        $token = $this->csrf($client);
        $tag = 'page-'.strtolower((string) new Ulid());
        for ($index = 0; $index < 13; ++$index) {
            $this->create($client, $token, sprintf('Pagination %02d', $index), [$tag]);
        }
        $client->request('GET', '/catalogue', ['q' => $tag, 'kind' => 'component', 'sort' => 'name']);
        self::assertSelectorCount(12, 'article');
        self::assertSelectorTextContains('[role="status"]', '13 items');
        $client->clickLink('Next');
        self::assertSelectorCount(1, 'article');
        $client->request('GET', '/catalogue', ['q' => $tag, 'kind' => 'layout']);
        self::assertSelectorCount(0, 'article');
        $client->request('GET', '/catalogue', ['page' => '-1']);
        self::assertResponseStatusCodeSame(400);
    }
}
