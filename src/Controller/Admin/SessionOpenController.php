<?php

namespace App\Controller\Admin;

use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionOpenController extends AbstractController
{
    #[Route('/admin/sessions/{id}/open', name: 'admin_session_open', methods: ['POST'])]
    public function __invoke(Session $session, EntityManagerInterface $em): Response
    {
        if ($session->isPending()) {
            $session->setStatus(Session::STATUS_ACTIVE);
            $em->flush();
            $this->addFlash('success', 'Session ouverte. Les participants peuvent voter.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
    }
}
