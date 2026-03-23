<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionActivateController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/activate', name: 'admin_question_activate', methods: ['POST'])]
    public function __invoke(int $sessionId, Question $question, EntityManagerInterface $entityManager, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($sessionId);
        if (!$session || $question->getSession() !== $session || !$session->isActive()) {
            return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
        }

        // Close any currently active question
        foreach ($session->getQuestions() as $q) {
            if ($q->isActive()) {
                $q->setStatus(Question::STATUS_CLOSED);
            }
        }

        if ($question->isPending()) {
            $question->setStatus(Question::STATUS_ACTIVE);
            $entityManager->flush();
            $this->addFlash('success', 'Question activée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }
}
