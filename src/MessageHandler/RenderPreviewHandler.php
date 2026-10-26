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
    public function __construct(private EntityManagerInterface $entityManager, private PreviewRenderer $renderer, private PreviewStorage $storage, private PreviewLifecycle $lifecycle)
    {
    }

    public function __invoke(RenderPreview $message): void
    {
        $job = $this->entityManager->find(PreviewJob::class, $message->jobId);
        if (!$job || !$this->lifecycle->claim($message->jobId)) {
            return;
        }
        try {
            $result = $this->renderer->render($job->getPayload());
            if ($result['ok']) {
                $this->storage->write($job->getId(), $result);
                $this->lifecycle->finish($job->getId(), 'succeeded');
            } else {
                $this->lifecycle->finish($job->getId(), 'failed', $result['error']);
            }
        } catch (\Throwable) {
            $this->lifecycle->finish($job->getId(), 'failed', ['code' => 'worker_unavailable', 'message' => 'Preview could not be completed. Retry later.']);
        }
        try {
            $this->entityManager->refresh($job);
        } catch (\Doctrine\ORM\EntityNotFoundException) {
            // The owner may have been removed while the bounded job was running.
        }
    }
}
