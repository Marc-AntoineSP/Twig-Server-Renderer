<?php

namespace App\Controller;

use App\Composition\{CompositionWorkspace, ReferenceDiscovery, ReferenceQuery, ReferenceSearchInput};
use App\Entity\CatalogueItem;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Attribute\{MapQueryString, MapRequestPayload};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\{IsCsrfTokenValid, IsGranted};
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[IsGranted('ROLE_EDITOR')]
final class ReferencePickerController extends AbstractController
{
    #[Route('/api/v1/items/{id}/composition/references', name: 'composition_references', methods: ['POST'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function search(#[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 64, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] ReferenceSearchInput $input, ReferenceDiscovery $references): JsonResponse
    {
        return new JsonResponse($references->search($item, $input), headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/items/{id}/composition/reference', name: 'composition_reference_read', methods: ['GET'], format: 'json')]
    public function read(Request $request, #[MapEntity(id: 'id')] CatalogueItem $item, ReferenceDiscovery $references): JsonResponse
    {
        return new JsonResponse($references->read($item, $request->query->getString('name'), $request->query->getString('version')), headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/catalogue/{id}/references', name: 'composition_reference_browse', methods: ['GET'])]
    public function browse(#[MapEntity(id: 'id')] CatalogueItem $item, CompositionWorkspace $workspace, ReferenceDiscovery $references, #[MapQueryString(validationFailedStatusCode: 400)] ReferenceQuery $query = new ReferenceQuery()): Response
    {
        $draft = $workspace->require($item); $document = $workspace->document($draft);
        $mode = 'reference' === $document->root->type ? 'replace' : 'insert';
        $results = $references->search($item, new ReferenceSearchInput($document, $draft->getRevision(), $document->root->id, $mode, $query));
        return $this->render('composition/references.html.twig', $results + ['item' => $item, 'query' => $query], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
