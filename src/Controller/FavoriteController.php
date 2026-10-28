<?php

namespace App\Controller;

use App\Favorites\Favorites;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\{IsCsrfTokenValid, IsGranted};

#[IsGranted('ROLE_READER')]
final class FavoriteController extends AbstractController
{
    #[Route('/api/v1/favorites/{kind}/{id}', name: 'favorite_read', requirements: ['kind' => 'theme|catalogue'], methods: ['GET'], format: 'json')]
    public function read(string $kind, string $id, Favorites $favorites): JsonResponse
    {
        return $this->answer($kind, $id, $favorites->contains($favorites->target($kind, $id)));
    }

    #[Route('/api/v1/favorites/{kind}/{id}', name: 'favorite_write', requirements: ['kind' => 'theme|catalogue'], methods: ['PUT', 'DELETE'], format: 'json')]
    #[IsCsrfTokenValid('api', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function write(Request $request, string $kind, string $id, Favorites $favorites): JsonResponse
    {
        return $this->answer($kind, $id, $favorites->set($kind, $id, $request->isMethod('PUT')));
    }

    private function answer(string $kind, string $id, bool $favorite): JsonResponse
    {
        return new JsonResponse(['kind' => $kind, 'id' => $id, 'favorite' => $favorite], headers: ['Cache-Control' => 'private, no-store', 'Vary' => 'Cookie']);
    }
}
