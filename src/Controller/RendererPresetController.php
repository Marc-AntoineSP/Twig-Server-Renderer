<?php

namespace App\Controller;

use App\Entity\CatalogueItem;
use App\Rendering\RendererPresets;
use App\Rendering\RendererSettings;
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
final class RendererPresetController extends AbstractController
{
    #[Route('/api/v1/items/{id}/renderer-preset', name: 'renderer_preset_read', methods: ['GET'], format: 'json')]
    public function read(#[MapEntity(id: 'id')] CatalogueItem $item, RendererPresets $presets): JsonResponse
    {
        return new JsonResponse($presets->read($item, $this->getUser()), headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/v1/items/{id}/renderer-preset', name: 'renderer_preset_save', methods: ['PUT'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function save(#[MapEntity(id: 'id')] CatalogueItem $item, #[MapRequestPayload(acceptFormat: 'json', serializationContext: [JsonDecode::ASSOCIATIVE => false, JsonDecode::RECURSION_DEPTH => 24, AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])] RendererSettings $settings, RendererPresets $presets): JsonResponse
    {
        return new JsonResponse($presets->save($item, $this->getUser(), $settings), headers: ['Cache-Control' => 'private, no-store']);
    }
}
