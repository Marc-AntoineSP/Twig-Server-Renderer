<?php

namespace App\Tests\Integration;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Command\FixturesCommand;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Repository\CatalogueRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

final class PersistenceTest extends DatabaseTestCase
{
    public function testFixturesAreValidatedAndDoNotOverwriteExistingEdits(): void
    {
        self::bootKernel();
        $tester = new CommandTester(self::getContainer()->get(FixturesCommand::class));
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        $repository = self::getContainer()->get(CatalogueRepository::class);
        $item = $repository->named('awui/alert');
        self::assertNotNull($item);
        $before=self::getContainer()->get(CatalogueWriter::class)->draft($item)->getSourceMap();
        $release=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>'1.0.0']);self::assertNotNull($release);$path=self::getContainer()->getParameter('kernel.project_dir').'/var/catalogue/'.$release->getArchiveKey();self::assertFileExists($path);self::assertSame($release->getArchiveHash(),hash_file('sha256',$path));
        $item->reviseMetadata('Preserved fixture edit', 'My description', ['my-tag']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('Preserved fixture edit', $repository->named('awui/alert')->getTitle());
        self::assertNotNull($repository->named('awui/notice-grid'));
        self::assertEquals($before,self::getContainer()->get(CatalogueWriter::class)->draft($item)->getSourceMap());
    }

    public function testReleaseSnapshotIsImmutableAndDatabaseEnforcesVersionUniqueness(): void
    {
        self::bootKernel();
        $name = 'tests/release-'.strtolower((string) new Ulid());
        $writer = self::getContainer()->get(CatalogueWriter::class);
        $draft = $writer->create(new CreateItemInput($name, 'component', 'Release metadata'), $this->account());
        $manifest = $draft->getManifestData();
        $manifest->version = '1.0.0';
        $archive = tempnam(sys_get_temp_dir(), 'studio-release-');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive, \ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        foreach ($draft->getSourceMap() as $path => $source) {
            $zip->addFromString($path, $source);
        }
        $zip->close();
        try {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $release = new Release($draft->getItem(), '1.0.0', $manifest, hash_file('sha256', $archive), 'test/'.basename($archive).'.zip');
            $entityManager->persist($release);
            $entityManager->flush();
            $id = $release->getId();
            $itemId = $draft->getItem()->getId();
            $manifest->title = 'External mutation';
            $release->getManifestData()->title = 'Returned object mutation';
            $entityManager->flush();
            $entityManager->clear();
            $saved = $entityManager->find(Release::class, $id);
            self::assertSame('Release metadata', $saved->getManifestData()->title);
            self::assertSame('1.0.0', $saved->getVersion());
            $duplicate = new Release($entityManager->find(CatalogueItem::class, $itemId), '1.0.0', $saved->getManifestData(), $saved->getArchiveHash(), $saved->getArchiveKey());
            $entityManager->persist($duplicate);
            $this->expectException(UniqueConstraintViolationException::class);
            $entityManager->flush();
        } finally {
            unlink($archive);
        }
    }
}
