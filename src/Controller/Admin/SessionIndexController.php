<?php

namespace App\Controller\Admin;

use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionIndexController extends AbstractController
{
    #[Route('/admin', name: 'admin_index')]
    public function __invoke(SessionRepository $sessionRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'sessions' => $sessionRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
