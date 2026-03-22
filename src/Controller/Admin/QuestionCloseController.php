<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\SessionRepository;
use App\Service\SessionMercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionCloseController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/close', name: 'admin_question_close', methods: ['POST'])]
    public function __invoke(int $sessionId, Question $question, EntityManagerInterface $em, SessionRepository $sessionRepo, SessionMercurePublisher $publisher): Response
    {
        $session = $sessionRepo->find($sessionId);
        if ($session && $question->getSession() === $session && $question->isActive()) {
            $question->setStatus(Question::STATUS_CLOSED);
            $em->flush();

            $publisher->publishParticipantReload($session);

            $this->addFlash('success', 'Question fermée. Les résultats sont affichés.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }
}
