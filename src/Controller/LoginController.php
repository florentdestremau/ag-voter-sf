<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function __invoke(AuthenticationUtils $authUtils): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_index');
        }

        return $this->render('login.html.twig', [
            'error' => $authUtils->getLastAuthenticationError(),
            'last_username' => $authUtils->getLastUsername(),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }
}
