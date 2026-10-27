<?php

namespace App\Tests\Integration;

use App\Entity\PreviewJob;
use App\Rendering\PreviewLifecycle;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class PreviewLifecycleTest extends DatabaseTestCase
{
    public function testCancellationWinsAgainstLateCompletionAndClaimsAreExclusive(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $job = new PreviewJob($this->account(), 1, str_repeat('a', 64), new \stdClass());
        $entityManager->persist($job);
        $entityManager->flush();
        $lifecycle = self::getContainer()->get(PreviewLifecycle::class);
        self::assertIsString($token=$lifecycle->claim($job->getId()));
        self::assertNull($lifecycle->claim($job->getId()));
        $lifecycle->cancel($job);
        self::assertSame('cancelled', $job->getStatus());
        self::assertFalse($lifecycle->finish($job->getId(), $token, 'succeeded'));
        self::assertFalse($lifecycle->finish($job->getId(), $token, 'failed', ['message' => 'Too late']));
        $entityManager->clear();
        self::assertSame('cancelled', $entityManager->find(PreviewJob::class, $job->getId())->getStatus());
    }

    public function testAbandonedLeaseCanBeReclaimedAndOldWorkerCannotFinish():void
    {
        self::bootKernel();$em=self::getContainer()->get(EntityManagerInterface::class);$job=new PreviewJob($this->account(),1,str_repeat('b',64),new \stdClass());$em->persist($job);$em->flush();$lifecycle=self::getContainer()->get(PreviewLifecycle::class);
        $old=$lifecycle->claim($job->getId());self::assertIsString($old);
        $em->getConnection()->executeStatement("UPDATE preview_job SET started_at = CURRENT_TIMESTAMP - INTERVAL '46 seconds' WHERE id = ?",[$job->getId()]);
        $new=$lifecycle->claim($job->getId());self::assertIsString($new);self::assertNotSame($old,$new);self::assertFalse($lifecycle->finish($job->getId(),$old,'succeeded'));self::assertTrue($lifecycle->finish($job->getId(),$new,'succeeded'));
    }
}
