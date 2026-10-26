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
        self::assertTrue($lifecycle->claim($job->getId()));
        self::assertFalse($lifecycle->claim($job->getId()));
        $lifecycle->cancel($job);
        self::assertSame('cancelled', $job->getStatus());
        self::assertFalse($lifecycle->finish($job->getId(), 'succeeded'));
        self::assertFalse($lifecycle->finish($job->getId(), 'failed', ['message' => 'Too late']));
        $entityManager->clear();
        self::assertSame('cancelled', $entityManager->find(PreviewJob::class, $job->getId())->getStatus());
    }
}
