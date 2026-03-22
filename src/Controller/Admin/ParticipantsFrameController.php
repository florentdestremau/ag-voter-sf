<?php

namespace App\Controller\Admin;

use App\Entity\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParticipantsFrameController extends AbstractController
{
    #[Route('/admin/sessions/{id}/participants-frame', name: 'admin_participants_frame')]
    public function __invoke(Session $session): Response
    {
        return $this->render('admin/_participants_frame.html.twig', ['session' => $session]);
    }
}
