<?php

namespace App\Controller\Admin;

use App\Entity\Session;
use App\Form\SessionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionNewController extends AbstractController
{
    #[Route('/admin/sessions/new', name: 'admin_session_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = new Session();
        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($session);
            $entityManager->flush();
            $this->addFlash('success', 'Session créée avec succès.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        return $this->render('admin/session_new.html.twig', ['form' => $form]);
    }
}
