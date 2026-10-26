<?php

namespace App\Controller;

use App\Rendering\WorkerClient;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'studio_health', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'studio_ready', methods: ['GET'])]
    public function ready(Connection $connection, WorkerClient $workers): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();
            if (!$workers->ready()) {
                return $this->json(['status' => 'unavailable'], 503);
            }
        } catch (\Throwable) {
            return $this->json(['status' => 'unavailable'], 503);
        }

        return $this->json(['status' => 'ready']);
    }
}
