<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'studio_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('studio/home.html.twig');
    }
}
