<?php

namespace App\Controller;

use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\CreateItemInput;
use App\Catalogue\DraftInput;
use App\Entity\CatalogueItem;
use App\Entity\Draft;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[IsGranted('ROLE_READER')]
final class CatalogueApiController extends AbstractController
{
    #[Route('/api/v1/csrf', name: 'api_csrf', methods: ['GET'], format: 'json')]
    public function csrf(CsrfTokenManagerInterface $tokens): JsonResponse
    {
        return new JsonResponse(['token' => $tokens->getToken('api')->getValue()], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/items', name: 'api_item_create', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function create(#[MapRequestPayload(acceptFormat: 'json', serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] CreateItemInput $input, CatalogueWriter $writer): JsonResponse
    {
        $draft = $writer->create($input, $this->getUser());

        return new JsonResponse($this->data($draft), 201, ['Location' => $this->generateUrl('api_item_draft', ['id' => $draft->getItem()->getId()]), 'ETag' => '"'.$draft->getRevision().'"', 'Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/items/{id}/draft', name: 'api_item_draft', methods: ['GET'], format: 'json')]
    public function draft(#[MapEntity(id: 'id')] CatalogueItem $item, CatalogueWriter $writer): JsonResponse
    {
        $draft = $writer->draft($item);

        return new JsonResponse($this->data($draft), headers: ['ETag' => '"'.$draft->getRevision().'"', 'Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/items/{id}/draft', name: 'api_item_save', methods: ['PUT'], format: 'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function save(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 32, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] DraftInput $input, CatalogueWriter $writer): JsonResponse
    {
        $match = $request->headers->get('If-Match');
        if (null === $match) {
            throw new CatalogueException(428, 'Supply the expected draft revision in If-Match.', [], 'revision_required');
        }
        if (!preg_match('/^"([1-9][0-9]{0,8})"$/D', $match, $matches)) {
            throw new CatalogueException(400, 'If-Match must be a quoted positive revision, for example "1".');
        }
        $draft = $writer->save($item, (int) $matches[1], $input, $this->getUser());

        return new JsonResponse($this->data($draft), headers: ['ETag' => '"'.$draft->getRevision().'"', 'Cache-Control' => 'private, no-store']);
    }

    private function data(Draft $draft): array
    {
        $item = $draft->getItem();

        return ['id' => $item->getId(), 'name' => $item->getName(), 'kind' => $item->getKind(), 'title' => $item->getTitle(), 'description' => $item->getDescription(), 'tags' => $item->getTags(), 'revision' => $draft->getRevision(), 'manifestData' => $draft->getManifestData(), 'sourceMap' => $draft->getSourceMap()];
    }
}
