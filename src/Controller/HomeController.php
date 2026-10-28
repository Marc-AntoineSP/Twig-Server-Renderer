<?php

namespace App\Controller;

use App\Favorites\Favorites;
use App\Repository\WorkspaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_READER')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'studio_home', methods: ['GET'])]
    public function __invoke(WorkspaceRepository $workspace, Favorites $favorites): Response
    {
        return $this->render('studio/home.html.twig', $workspace->overview($favorites->owner()));
    }
}
