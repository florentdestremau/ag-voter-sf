<?php

namespace App\Controller\Admin;

use App\Entity\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionShowController extends AbstractController
{
    #[Route('/admin/sessions/{id}', name: 'admin_session_show')]
    public function __invoke(Session $session): Response
    {
        return $this->render('admin/session_show.html.twig', ['session' => $session]);
    }
}
