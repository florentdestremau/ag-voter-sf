<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Entity\Session;
use App\Service\SessionMercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionCloseController extends AbstractController
{
    #[Route('/admin/sessions/{id}/close', name: 'admin_session_close', methods: ['POST'])]
    public function __invoke(Session $session, EntityManagerInterface $em, SessionMercurePublisher $publisher): Response
    {
        if ($session->isActive()) {
            foreach ($session->getQuestions() as $question) {
                if ($question->isActive()) {
                    $question->setStatus(Question::STATUS_CLOSED);
                }
            }
            $session->setStatus(Session::STATUS_CLOSED);
            $em->flush();

            $publisher->publishParticipantReload($session);

            $this->addFlash('success', 'Session fermée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
    }
}
