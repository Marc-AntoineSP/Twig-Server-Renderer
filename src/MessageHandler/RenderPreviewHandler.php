<?php

namespace App\MessageHandler;

use App\Entity\PreviewJob;
use App\Message\RenderPreview;
use App\Rendering\PreviewRenderer;
use App\Rendering\PreviewStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RenderPreviewHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private PreviewRenderer $renderer, private PreviewStorage $storage)
    {
    }

    public function __invoke(RenderPreview $message): void
    {
        $job = $this->entityManager->find(PreviewJob::class, $message->jobId);
        if (!$job || 'queued' !== $job->getStatus()) {
            return;
        }
        $job->running();
        $this->entityManager->flush();
        try {
            $result = $this->renderer->render($job->getPayload());
            $this->entityManager->refresh($job);
            if ('running' !== $job->getStatus()) {
                return;
            }
            if ($result['ok']) {
                $this->storage->write($job->getId(), $result);
                $job->succeed();
            } else {
                $job->fail($result['error']);
            }
        } catch (\Throwable) {
            $this->entityManager->refresh($job);
            if ('running' !== $job->getStatus()) {
                return;
            }
            $job->fail(['code' => 'worker_unavailable', 'message' => 'Preview could not be completed. Retry later.']);
        }
        $this->entityManager->flush();
    }
}
