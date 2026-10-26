<?php

namespace App\Tests\Integration;

use App\Catalogue\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use App\Catalogue\Validation\PackageValidator;
use App\Entity\Release;
use App\Rendering\DependencyResolver;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class DependencyResolverTest extends DatabaseTestCase
{
    private array $archives = [];

    protected function tearDown(): void
    {
        foreach ($this->archives as $path) {
            if (is_file($path)) { unlink($path); }
        }
        parent::tearDown();
    }

    private function packageName(string $label): string
    {
        return 'graphs/'.$label.'-'.strtolower((string) new Ulid());
    }

    private function release(string $name, string $version, array $dependencies = []): Release
    {
        $input = new CreateItemInput($name, 'component', 'Graph fixture');
        [$manifest, $sources] = self::getContainer()->get(SourceFactory::class)->initial($input);
        $manifest->version = $version;
        $manifest->dependencies = array_map(static fn (array $dependency): object => (object) $dependency, $dependencies);
        $manifest = self::getContainer()->get(PackageValidator::class)->validate($manifest, $sources, true);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        [$namespace, $slug] = explode('/', $name);
        $item = $entityManager->getRepository(\App\Entity\CatalogueItem::class)->findOneBy(['namespace' => $namespace, 'slug' => $slug]);
        if (!$item) {
            $item = self::getContainer()->get(CatalogueWriter::class)->create($input, $this->account(), $manifest, $sources)->getItem();
        }
        $key = 'graph-tests/'.strtolower((string) new Ulid()).'.zip';
        $path = self::getContainer()->getParameter('kernel.project_dir').'/var/catalogue/'.$key;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        foreach ($sources as $file => $source) { $zip->addFromString($file, $source); }
        $zip->close();
        $this->archives[] = $path;
        $release = new Release($item, $version, $manifest, hash_file('sha256', $path), $key);
        $entityManager->persist($release);
        $entityManager->flush();

        return $release;
    }

    private function resolve(array $dependencies): array
    {
        [$manifest, $sources] = self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput($this->packageName('root'), 'block', 'Root'));
        $manifest->dependencies = array_map(static fn (array $dependency): object => (object) $dependency, $dependencies);

        return self::getContainer()->get(DependencyResolver::class)->resolve($manifest, $sources);
    }

    public function testPinnedDependencyClosureUsesVerifiedImmutableArchive(): void
    {
        self::bootKernel();
        $name = $this->packageName('leaf');
        $this->release($name, '1.0.0');
        $result = $this->resolve([['name' => $name, 'version' => '1.0.0']]);
        self::assertSame('1.0.0', $result['versions'][$name]);
        self::assertObjectHasProperty('catalogue/'.$name.'/component.html.twig', $result['templates']);
    }

    public function testConflictingExactVersionsAreRejected(): void
    {
        self::bootKernel();
        $leaf = $this->packageName('leaf');
        $branch = $this->packageName('branch');
        $this->release($leaf, '1.0.0');
        $this->release($leaf, '2.0.0');
        $this->release($branch, '1.0.0', [['name' => $leaf, 'version' => '2.0.0']]);
        $this->expectException(CatalogueException::class);
        $this->resolve([['name' => $leaf, 'version' => '1.0.0'], ['name' => $branch, 'version' => '1.0.0']]);
    }

    public function testDependencyCyclesAreRejected(): void
    {
        self::bootKernel();
        $a = $this->packageName('a');
        $b = $this->packageName('b');
        $this->release($a, '1.0.0', [['name' => $b, 'version' => '1.0.0']]);
        $this->release($b, '1.0.0', [['name' => $a, 'version' => '1.0.0']]);
        $this->expectException(CatalogueException::class);
        $this->resolve([['name' => $a, 'version' => '1.0.0']]);
    }

    public function testTamperedArchivesAreRejected(): void
    {
        self::bootKernel();
        $name = $this->packageName('tampered');
        $this->release($name, '1.0.0');
        file_put_contents(end($this->archives), 'tampered', FILE_APPEND);
        $this->expectException(CatalogueException::class);
        $this->resolve([['name' => $name, 'version' => '1.0.0']]);
    }

    public function testMissingImmutableDependencyIsRejected(): void
    {
        self::bootKernel();
        $this->expectException(CatalogueException::class);
        $this->resolve([['name' => $this->packageName('missing'), 'version' => '1.0.0']]);
    }
}
