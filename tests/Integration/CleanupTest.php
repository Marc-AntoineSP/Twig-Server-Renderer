<?php
namespace App\Tests\Integration;
use App\Entity\CatalogueItem;
use App\Entity\PreviewJob;
use App\Entity\Release;
use App\Operations\Cleanup;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
final class CleanupTest extends DatabaseTestCase
{
    public function testDryRunAndCleanupRemoveExpiredArtifactsAndNeverReferencedReleases():void
    {
        self::bootKernel();$em=self::getContainer()->get(EntityManagerInterface::class);$root=sys_get_temp_dir().'/cleanup-'.bin2hex(random_bytes(6));mkdir($root.'/releases',0770,true);mkdir($root.'/previews');mkdir($root.'/exports/owner',0770,true);
        $item=new CatalogueItem('cleanup','example-'.bin2hex(random_bytes(4)),'component','Retained release');$em->persist($item);$hash=hash('sha256','referenced');$release=new Release($item,'1.0.0',new \stdClass(),$hash,'releases/'.$hash.'.zip');$em->persist($release);
        $job=new PreviewJob($this->account(),1,str_repeat('a',64),new \stdClass());$em->persist($job);$em->flush();$em->getConnection()->executeStatement("UPDATE preview_job SET expires_at = CURRENT_TIMESTAMP - INTERVAL '2 hours' WHERE id = ?",[$job->getId()]);
        $retained=$root.'/releases/'.$hash.'.zip';file_put_contents($retained,'referenced');touch($retained,time()-172800);
        $orphan=$root.'/releases/'.hash('sha256','orphan').'.zip';file_put_contents($orphan,'orphan');touch($orphan,time()-172800);
        $new=$root.'/releases/'.hash('sha256','new').'.zip';file_put_contents($new,'new');
        $preview=$root.'/previews/'.$job->getId().'.json';file_put_contents($preview,'{}');touch($preview,time()-7200);
        file_put_contents($root.'/exports/owner/expired.json',json_encode(['expiresAt'=>time()-5]));file_put_contents($root.'/exports/owner/expired.bin','expired');file_put_contents($root.'/exports/owner/live.json',json_encode(['expiresAt'=>time()+1800]));file_put_contents($root.'/exports/owner/live.bin','live');
        $cleanup=new Cleanup($em->getConnection(),$root);
        try{$plan=$cleanup->run();self::assertTrue($plan['dryRun']);self::assertSame(4,$plan['candidateFiles']);self::assertFileExists($orphan);self::assertFileExists($preview);$applied=$cleanup->run(true);self::assertSame(4,$applied['removedFiles']);self::assertFileDoesNotExist($orphan);self::assertFileDoesNotExist($preview);self::assertFileExists($retained);self::assertFileExists($new);self::assertFileExists($root.'/exports/owner/live.bin');self::assertFalse($em->getConnection()->fetchOne('SELECT id FROM preview_job WHERE id = ?',[$job->getId()]));}
        finally{$em->getConnection()->executeStatement('DELETE FROM catalogue_release WHERE item_id = ?',[$item->getId()]);$em->getConnection()->executeStatement('DELETE FROM catalogue_item WHERE id = ?',[$item->getId()]);$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}
    }
}
