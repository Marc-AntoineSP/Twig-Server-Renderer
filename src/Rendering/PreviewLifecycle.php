<?php

namespace App\Rendering;

use App\Entity\PreviewJob;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PreviewLifecycle
{
    public function __construct(private Connection $connection, private EntityManagerInterface $entityManager)
    {
    }

    public function claim(string $id): bool
    {
        return 1 === $this->connection->executeStatement("UPDATE preview_job SET status = 'running' WHERE id = :id AND status = 'queued' AND expires_at > CURRENT_TIMESTAMP", ['id' => $id]);
    }

    public function finish(string $id, string $status, ?array $error = null): bool
    {
        if (!in_array($status, ['succeeded', 'failed'], true)) {
            throw new \InvalidArgumentException('Unsupported completion state.');
        }

        return 1 === $this->connection->executeStatement("UPDATE preview_job SET status = :status, error = :error WHERE id = :id AND status = 'running' AND expires_at > CURRENT_TIMESTAMP", ['status' => $status, 'error' => $error, 'id' => $id], ['error' => Types::JSONB]);
    }

    public function cancel(PreviewJob $job): void
    {
        $this->connection->executeStatement("UPDATE preview_job SET status = 'cancelled' WHERE id = :id AND status IN ('queued', 'running')", ['id' => $job->getId()]);
        $this->entityManager->refresh($job);
    }
}
