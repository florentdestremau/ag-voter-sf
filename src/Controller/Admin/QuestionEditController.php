<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Form\QuestionType;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionEditController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, int $sessionId, Question $question, EntityManagerInterface $entityManager, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($sessionId);
        if (!$session || $question->getSession() !== $session || !$question->isPending()) {
            return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
        }

        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($question->getChoices() as $i => $choice) {
                $choice->setQuestion($question);
                $choice->setOrderIndex($i);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Question modifiée.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        return $this->render('admin/question_form.html.twig', [
            'session' => $session,
            'form' => $form,
            'question' => $question,
        ]);
    }
}
