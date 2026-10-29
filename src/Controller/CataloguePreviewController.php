<?php

namespace App\Controller;

use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Rendering\CataloguePreviewInput;
use App\Rendering\CataloguePreviews;
use App\Rendering\ReleaseThumbnails;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Styleguide\Contracts\CatalogueException;

#[IsGranted('ROLE_READER')]
final class CataloguePreviewController extends AbstractController
{
    #[Route('/api/v1/catalogue-previews/{id}', name: 'catalogue_preview_create', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function create(#[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 32, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] CataloguePreviewInput $input, CataloguePreviews $previews): JsonResponse
    {
        $job = $previews->create($item, $input, $this->getUser(), $this->isGranted('ROLE_EDITOR'));
        return new JsonResponse(['id' => $job->getId(), 'status' => $job->getStatus(), 'sequence' => $job->getRequestSequence(), 'selection' => $job->getPayload()['selection']], 202, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/release-thumbnails/{id}', name: 'release_thumbnail', methods: ['GET'], format: 'json')]
    public function thumbnail(#[MapEntity(id: 'id')] Release $release, ReleaseThumbnails $thumbnails): JsonResponse
    {
        $html = $thumbnails->read($release->getArchiveHash()) ?? throw new CatalogueException(404, 'No cached published preview. Open the item to build one.');
        return new JsonResponse(['html' => $html, 'version' => $release->getVersion()], headers: ['Cache-Control' => 'private, no-store']);
    }
}
