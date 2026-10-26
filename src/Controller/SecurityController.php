<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'studio_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('studio_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'studio_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('The security firewall handles logout.');
    }
}
