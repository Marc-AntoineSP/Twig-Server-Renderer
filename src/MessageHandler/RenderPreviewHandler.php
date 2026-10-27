<?php

namespace App\MessageHandler;

use App\Entity\PreviewJob;
use App\Message\RenderPreview;
use App\Rendering\PreviewRenderer;
use App\Rendering\PreviewStorage;
use App\Rendering\PreviewLifecycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RenderPreviewHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private PreviewRenderer $renderer, private PreviewStorage $storage, private PreviewLifecycle $lifecycle, private \Psr\Log\LoggerInterface $logger)
    {
    }

    public function __invoke(RenderPreview $message): void
    {
        $job = $this->entityManager->find(PreviewJob::class, $message->jobId);
        if (!$job || !($token=$this->lifecycle->claim($message->jobId))) {
            return;
        }
        $started=microtime(true);$errorCode='none';
        try {
            $result = $this->renderer->render($job->getPayload());
            if ($result['ok']) {
                $this->storage->write($job->getId(), $result, $token);
                $this->lifecycle->finish($job->getId(), $token, 'succeeded');
            } else {
                $errorCode=$result['error']['code']??'invalid_source';
                $this->lifecycle->finish($job->getId(), $token, 'failed', $result['error']);
            }
        } catch (\Throwable) {
            $errorCode='worker_unavailable';
            $this->lifecycle->finish($job->getId(), $token, 'failed', ['code' => 'worker_unavailable', 'message' => 'Preview could not be completed. Retry later.']);
        }
        $this->logger->info('Preview finished.',['jobId'=>$job->getId(),'inputHash'=>$job->getInputHash(),'durationMs'=>(int)((microtime(true)-$started)*1000),'queueWaitMs'=>max(0,(int)(($started-(float)$job->getCreatedAt()->format('U.u'))*1000)),'errorCode'=>$errorCode]);
        try {
            $this->entityManager->refresh($job);
        } catch (\Doctrine\ORM\EntityNotFoundException) {
            // The owner may have been removed while the bounded job was running.
        }
    }
}
