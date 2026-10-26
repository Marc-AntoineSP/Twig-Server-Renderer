<?php

namespace App\Controller;

use App\Catalogue\CatalogueException;
use App\Entity\PreviewJob;
use App\Rendering\PreviewInput;
use App\Rendering\PreviewJobs;
use App\Rendering\PreviewStorage;
use App\Rendering\PreviewLifecycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[IsGranted('ROLE_EDITOR')]
final class PreviewController extends AbstractController
{
    #[Route('/api/v1/preview-jobs', name: 'preview_create', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function create(#[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 32, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] PreviewInput $input, PreviewJobs $jobs): JsonResponse
    {
        $job = $jobs->create($input, $this->getUser());

        return new JsonResponse(['id' => $job->getId(), 'status' => $job->getStatus(), 'sequence' => $job->getRequestSequence()], 202, ['Location' => $this->generateUrl('preview_status', ['id' => $job->getId()]), 'Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/preview-jobs/{id}', name: 'preview_status', methods: ['GET'], format: 'json')]
    public function status(#[MapEntity(id: 'id')] PreviewJob $job): JsonResponse
    {
        $this->owner($job);

        return new JsonResponse(['id' => $job->getId(), 'status' => $job->getStatus(), 'sequence' => $job->getRequestSequence(), 'error' => $job->getError()], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/preview-jobs/{id}/document', name: 'preview_document', methods: ['GET'], format: 'json')]
    public function document(#[MapEntity(id: 'id')] PreviewJob $job, PreviewStorage $storage): JsonResponse
    {
        $this->owner($job);
        if ('succeeded' !== $job->getStatus()) {
            throw new CatalogueException(409, 'A current successful preview document is required.');
        }

        return new JsonResponse(['html' => $storage->read($job->getId())['html'], 'sequence' => $job->getRequestSequence()], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/preview-jobs/{id}', name: 'preview_cancel', methods: ['DELETE'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function cancel(#[MapEntity(id: 'id')] PreviewJob $job, PreviewLifecycle $lifecycle): JsonResponse
    {
        $this->owner($job);
        $lifecycle->cancel($job);

        return new JsonResponse(['status' => $job->getStatus()], headers: ['Cache-Control' => 'private, no-store']);
    }

    private function owner(PreviewJob $job): void
    {
        if ($job->getOwner()->getId() !== $this->getUser()->getId()) {
            throw new CatalogueException(404, 'Preview not found.');
        }
    }
}
