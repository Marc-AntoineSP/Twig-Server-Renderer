<?php
namespace App\Tests\Integration;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\DraftInput;
use App\Entity\Release;
use App\Repository\CatalogueRepository;
use App\Showcase\Showcase;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
final class ShowcaseTest extends DatabaseTestCase
{
    public function testRealShowcasePublicationIsIdempotentAndPreservesEditedDrafts():void
    {
        self::bootKernel();$seed=self::getContainer()->get(Showcase::class);$first=$seed->seed(true);self::assertCount(12,$first);$second=$seed->seed(true);self::assertCount(12,$second);self::assertSame(['verified immutable release preserved'],array_values(array_unique($second)));
        $catalogue=self::getContainer()->get(CatalogueRepository::class);$writer=self::getContainer()->get(CatalogueWriter::class);$item=$catalogue->named('studio-showcase/button');$draft=$writer->draft($item);$original=$draft->getSourceMap();$manifest=$draft->getManifestData();$changed=clone $original;$changed->{$manifest->entry}.="\n{# Local editing must survive seeding. #}\n";$user=$this->account();$writer->save($item,$draft->getRevision(),new DraftInput($manifest,$changed,$item->getTitle(),$item->getDescription(),$item->getTags()),$user);$revision=$draft->getRevision();
        try{$seed->seed(true);self::assertSame($revision,$writer->draft($item)->getRevision());self::assertSame($changed->{$manifest->entry},$writer->draft($item)->getSourceMap()->{$manifest->entry});self::assertCount(1,self::getContainer()->get(EntityManagerInterface::class)->getRepository(Release::class)->findBy(['item'=>$item]));}
        finally{$writer->save($item,$draft->getRevision(),new DraftInput($manifest,$original,$item->getTitle(),$item->getDescription(),$item->getTags()),$user);}
    }
}
