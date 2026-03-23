<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionDeleteController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function __invoke(int $sessionId, Question $question, EntityManagerInterface $entityManager, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($sessionId);
        if ($session && $question->getSession() === $session && $question->isPending()) {
            $entityManager->remove($question);
            $entityManager->flush();
            $this->addFlash('success', 'Question supprimée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }
}
