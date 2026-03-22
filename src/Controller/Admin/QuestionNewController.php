<?php

namespace App\Controller\Admin;

use App\Entity\Choice;
use App\Entity\Question;
use App\Entity\Session;
use App\Form\QuestionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionNewController extends AbstractController
{
    #[Route('/admin/sessions/{id}/questions/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, Session $session, EntityManagerInterface $em): Response
    {
        if ($session->isClosed()) {
            $this->addFlash('error', 'Impossible d\'ajouter une question à une session fermée.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        $question = new Question();
        $question->setSession($session);
        $question->setOrderIndex($session->getQuestions()->count());

        // Pré-remplir avec Pour/Contre/Abstention
        foreach (['Pour', 'Contre', 'Abstention'] as $i => $label) {
            $question->addChoice((new Choice())->setText($label)->setOrderIndex($i));
        }

        $form = $this->createForm(QuestionType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set orderIndex on choices
            foreach ($question->getChoices() as $i => $choice) {
                $choice->setQuestion($question);
                $choice->setOrderIndex($i);
            }
            $em->persist($question);
            $em->flush();
            $this->addFlash('success', 'Question ajoutée.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        return $this->render('admin/question_form.html.twig', [
            'session' => $session,
            'form' => $form,
            'question' => $question,
        ]);
    }
}
