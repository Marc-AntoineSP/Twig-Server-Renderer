<?php

namespace App\Tests\Functional;

use App\Catalogue\{CatalogueWriter, CreateItemInput};
use App\Composition\CompositionWorkspace;
use App\Entity\{CatalogueItem, User};
use App\Packaging\{PublishInput, ReleasePublisher};
use App\Tests\DatabaseTestCase;
use App\Tests\Fixtures\ReferenceCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Ulid;

final class ReferenceDiscoveryTest extends DatabaseTestCase
{
    private function csrf(KernelBrowser $client): string
    {
        $client->request('GET', '/api/v1/csrf'); return json_decode($client->getResponse()->getContent())->token;
    }
    private function composition(User $owner): array
    {
        $draft = self::getContainer()->get(CatalogueWriter::class)->create(new CreateItemInput('picker/target-'.strtolower((string) new Ulid()), 'layout', 'Reference workspace'), $owner);
        $document = self::getContainer()->get(CompositionWorkspace::class)->document($draft);
        return [$draft->getItem(), ['document' => $document, 'expectedRevision' => 1, 'selectedNodeId' => $document->root->id]];
    }
    private function search(KernelBrowser $client, string $id, array $input, string $csrf, array $query = []): array
    {
        $client->jsonRequest('POST', '/api/v1/items/'.$id.'/composition/references', [...$input, 'query' => (object) $query], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful($client->getResponse()->getContent());
        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testOldNonfavoriteVersionsAreSearchableAndManyFavoritesStayBoundedBeforePagination(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner);
        $prefix = 'load-'.strtolower((string) new Ulid());
        [$item, $input] = $this->composition($owner); $csrf = $this->csrf($client);
        $client->request('GET', '/catalogue/'.$item->getId().'/compose'); self::assertResponseIsSuccessful();
        $beforeBytes = strlen($client->getResponse()->getContent());
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $fixture = ReferenceCatalogue::create($em, $em->find(User::class, $owner->getId()), $prefix);
        $client->request('GET', '/catalogue/'.$item->getId().'/compose'); self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-composition-choices-value]');
        self::assertLessThan($beforeBytes + 256, strlen($client->getResponse()->getContent()));
        self::assertStringNotContainsString($fixture['manyVersions']['name'], $client->getResponse()->getContent());
        $found = $this->search($client, $item->getId(), $input, $csrf, ['q' => $fixture['old']['name'], 'version' => '1.0.0']);
        self::assertCount(1, $found['items']); self::assertSame($fixture['old']['name'], $found['items'][0]['name']); self::assertFalse($found['items'][0]['favorite']);
        self::assertArrayNotHasKey('props', $found['items'][0]); self::assertArrayNotHasKey('sourceMap', $found['items'][0]);
        $page = $this->search($client, $item->getId(), $input, $csrf, ['q' => $prefix, 'favorites' => true]);
        self::assertCount(20, $page['items']); self::assertTrue($page['hasMore']); self::assertLessThan(24000, strlen($client->getResponse()->getContent()));
        self::assertSame([true], array_values(array_unique(array_column($page['items'], 'favorite'))));
        $next = $this->search($client, $item->getId(), $input, $csrf, ['q' => $prefix, 'favorites' => true, 'page' => 2]);
        self::assertCount(20, $next['items']); self::assertSame([], array_intersect(array_column($page['items'], 'releaseId'), array_column($next['items'], 'releaseId')));
        $oldVersion = $this->search($client, $item->getId(), $input, $csrf, ['q' => $fixture['manyVersions']['name'], 'version' => '1.0.0']);
        self::assertCount(1, $oldVersion['items']); self::assertSame('1.0.0', $oldVersion['items'][0]['version']);
        $recent = $this->search($client, $item->getId(), $input, $csrf, ['q' => $fixture['manyVersions']['name']]); self::assertSame('1.0.249', $recent['items'][0]['version']);
        $none = $this->search($client, $item->getId(), $input, $csrf, ['q' => $prefix, 'kind' => 'block']); self::assertSame([], $none['items']);
        $literal = $this->search($client, $item->getId(), $input, $csrf, ['q' => '%']); self::assertSame([], $literal['items']);
        $client->request('GET', '/catalogue/'.$item->getId().'/references', ['q' => $fixture['old']['name']]); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main', $fixture['old']['name'].'@1.0.0');
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/commands', ['document' => $input['document'], 'expectedRevision' => $input['expectedRevision'], 'commands' => [(object) ['type' => 'add_reference', 'selectedId' => $input['selectedNodeId'], 'name' => $fixture['old']['name'], 'version' => '1.0.0']]], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful($client->getResponse()->getContent()); self::assertSame($fixture['old']['name'], json_decode($client->getResponse()->getContent(),true)['document']['root']['children'][0]['name']);
    }

    public function testEligibilityRejectsSelfTransitiveCyclesConflictingVersionsAndInvalidParentsBeforeLimits(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner);
        $writer = self::getContainer()->get(CatalogueWriter::class); $publisher = self::getContainer()->get(ReleasePublisher::class);
        [$item, $input] = $this->composition($owner); $publisher->publish($item, new PublishInput('1.0.0', 1));
        $leaf = $writer->create(new CreateItemInput('picker/leaf-'.strtolower((string) new Ulid()), 'component', 'Compatible reference'), $owner);
        $publisher->publish($leaf->getItem(), new PublishInput('1.0.0', 1)); $publisher->publish($leaf->getItem(), new PublishInput('2.0.0', 1));
        $dependent = $writer->create(new CreateItemInput('picker/dependent-'.strtolower((string) new Ulid()), 'block', 'Transitive owner reference'), $owner);
        $workspace = self::getContainer()->get(CompositionWorkspace::class);
        $tree = (object) ['schemaVersion' => 1, 'root' => (object) ['type' => 'reference', 'name' => $item->getName(), 'version' => '1.0.0', 'props' => new \stdClass()]];
        $workspace->save($dependent->getItem(), 1, $tree, $owner);
        $publisher->publish($dependent->getItem(), new PublishInput('1.0.0', 2));
        $csrf = $this->csrf($client);
        $results = $this->search($client, $item->getId(), $input, $csrf, ['q' => $dependent->getItem()->getName()]); self::assertSame([], $results['items']);
        $self = $this->search($client, $item->getId(), $input, $csrf, ['q' => $item->getName()]); self::assertSame([], $self['items']);
        $command = (object) ['type' => 'add_reference', 'selectedId' => $input['selectedNodeId'], 'name' => $dependent->getItem()->getName(), 'version' => '1.0.0'];
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/commands', ['document' => $input['document'], 'expectedRevision' => $input['expectedRevision'], 'commands' => [$command]], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertContains($client->getResponse()->getStatusCode(), [409, 422]);
        $node = (object) ['type' => 'reference', 'id' => 'n_'.str_repeat('a', 32), 'name' => $leaf->getItem()->getName(), 'version' => '1.0.0', 'props' => (object) ['message' => 'Keep instance properties']];
        $input['document']->root->children[] = $node;
        $versions = $this->search($client, $item->getId(), $input, $csrf, ['q' => $leaf->getItem()->getName()]); self::assertSame(['1.0.0'], array_column($versions['items'], 'version'));
        $replacement = [...$input, 'mode' => 'replace', 'selectedNodeId' => $node->id];
        $versions = $this->search($client, $item->getId(), $replacement, $csrf, ['q' => $leaf->getItem()->getName()]); self::assertSame(['2.0.0', '1.0.0'], array_column($versions['items'], 'version'));
        $client->request('GET', '/api/v1/items/'.$item->getId().'/composition/reference', ['name' => $node->name, 'version' => '1.0.0']); self::assertResponseIsSuccessful(); self::assertSame('1.0.0', json_decode($client->getResponse()->getContent())->version);
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/commands', ['document' => $input['document'], 'expectedRevision' => $input['expectedRevision'], 'commands' => [(object) ['type' => 'update_node', 'nodeId' => $node->id, 'changes' => (object) ['version' => '2.0.0']]]], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertResponseIsSuccessful(); self::assertStringContainsString('Keep instance properties', $client->getResponse()->getContent());
        $input['document']->root = $node; $input['selectedNodeId'] = $node->id;
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/references', $input, ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/commands', ['document' => $input['document'], 'expectedRevision' => $input['expectedRevision'], 'commands' => [(object) ['type' => 'add_reference', 'parentId' => $node->id, 'position' => 0, 'name' => $node->name, 'version' => '1.0.0']]], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
    }

    public function testReadersMissingVersionsStaleRevisionsAndInvalidQueriesCannotExposeOrMutateReferences(): void
    {
        $client = self::createClient(); $owner = $this->account(); $this->signIn($client, $owner); [$item, $input] = $this->composition($owner); $csrf = $this->csrf($client);
        $url = '/api/v1/items/'.$item->getId().'/composition/references';
        $client->jsonRequest('POST', $url, $input); self::assertResponseStatusCodeSame(403);
        foreach ([['page' => 0], ['page' => 10001], ['kind' => 'theme'], ['q' => str_repeat('x', 101)], ['version' => 'latest']] as $query) {
            $client->jsonRequest('POST', $url, [...$input, 'query' => $query], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(422);
        }
        $client->jsonRequest('POST', $url, [...$input, 'expectedRevision' => 2], ['HTTP_X_CSRF_TOKEN' => $csrf]); self::assertResponseStatusCodeSame(412);
        $client->request('GET', '/api/v1/items/'.$item->getId().'/composition/reference', ['name' => 'missing/reference', 'version' => '1.0.0']); self::assertResponseStatusCodeSame(404);
        $this->signIn($client, $this->account('ROLE_READER')); $readerCsrf = $this->csrf($client);
        $client->jsonRequest('POST', $url, $input, ['HTTP_X_CSRF_TOKEN' => $readerCsrf]); self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/catalogue/'.$item->getId().'/references'); self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/api/v1/items/'.$item->getId().'/composition/reference', ['name' => 'missing/reference', 'version' => '1.0.0']); self::assertResponseStatusCodeSame(403);
        // Revoke a real session after login: query, exact read and mutation all lose access.
        $this->signIn($client, $owner); $editorCsrf = $this->csrf($client);
        $em = self::getContainer()->get(EntityManagerInterface::class); $em->find(User::class, $owner->getId())->disable(); $em->flush();
        $client->jsonRequest('POST', $url, $input, ['HTTP_X_CSRF_TOKEN' => $editorCsrf]); self::assertResponseStatusCodeSame(401);
        $client->request('GET', '/api/v1/items/'.$item->getId().'/composition/reference', ['name' => 'missing/reference', 'version' => '1.0.0']); self::assertResponseStatusCodeSame(401);
        $client->jsonRequest('POST', '/api/v1/items/'.$item->getId().'/composition/commands', ['document' => $input['document'], 'expectedRevision' => 1, 'commands' => [(object) ['type' => 'add_reference', 'selectedId' => $input['selectedNodeId'], 'name' => 'missing/reference', 'version' => '1.0.0']]], ['HTTP_X_CSRF_TOKEN' => $editorCsrf]); self::assertResponseStatusCodeSame(401);
    }
}
