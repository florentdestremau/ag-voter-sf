<?php

namespace App\Controller;

use App\Entity\Choice;
use App\Entity\Question;
use App\Entity\Session;
use App\Form\QuestionType;
use App\Form\SessionType;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_index')]
    public function index(SessionRepository $repo): Response
    {
        return $this->render('admin/index.html.twig', [
            'sessions' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/sessions/new', name: 'admin_session_new', methods: ['GET', 'POST'])]
    public function newSession(Request $request, EntityManagerInterface $em): Response
    {
        $session = new Session();
        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($session);
            $em->flush();
            $this->addFlash('success', 'Session créée avec succès.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        return $this->render('admin/session_new.html.twig', ['form' => $form]);
    }

    #[Route('/sessions/{id}', name: 'admin_session_show')]
    public function showSession(Session $session): Response
    {
        return $this->render('admin/session_show.html.twig', ['session' => $session]);
    }

    #[Route('/sessions/{id}/open', name: 'admin_session_open', methods: ['POST'])]
    public function openSession(Session $session, EntityManagerInterface $em): Response
    {
        if ($session->isPending()) {
            $session->setStatus(Session::STATUS_ACTIVE);
            $em->flush();
            $this->addFlash('success', 'Session ouverte. Les participants peuvent voter.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
    }

    #[Route('/sessions/{id}/close', name: 'admin_session_close', methods: ['POST'])]
    public function closeSession(Session $session, EntityManagerInterface $em): Response
    {
        if ($session->isActive()) {
            // Close any active question first
            foreach ($session->getQuestions() as $question) {
                if ($question->isActive()) {
                    $question->setStatus(Question::STATUS_CLOSED);
                }
            }
            $session->setStatus(Session::STATUS_CLOSED);
            $em->flush();
            $this->addFlash('success', 'Session fermée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
    }

    #[Route('/sessions/{id}/questions/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function newQuestion(Request $request, Session $session, EntityManagerInterface $em): Response
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

    #[Route('/sessions/{sessionId}/questions/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function editQuestion(Request $request, int $sessionId, Question $question, EntityManagerInterface $em, SessionRepository $sessionRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
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
            $em->flush();
            $this->addFlash('success', 'Question modifiée.');

            return $this->redirectToRoute('admin_session_show', ['id' => $session->getId()]);
        }

        return $this->render('admin/question_form.html.twig', [
            'session' => $session,
            'form' => $form,
            'question' => $question,
        ]);
    }

    #[Route('/sessions/{sessionId}/questions/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function deleteQuestion(int $sessionId, Question $question, EntityManagerInterface $em, SessionRepository $sessionRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
        if ($session && $question->getSession() === $session && $question->isPending()) {
            $em->remove($question);
            $em->flush();
            $this->addFlash('success', 'Question supprimée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }

    #[Route('/sessions/{sessionId}/questions/{id}/activate', name: 'admin_question_activate', methods: ['POST'])]
    public function activateQuestion(int $sessionId, Question $question, EntityManagerInterface $em, SessionRepository $sessionRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
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
            $em->flush();
            $this->addFlash('success', 'Question activée.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }

    #[Route('/sessions/{sessionId}/questions/{id}/close', name: 'admin_question_close', methods: ['POST'])]
    public function closeQuestion(int $sessionId, Question $question, EntityManagerInterface $em, SessionRepository $sessionRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
        if ($session && $question->getSession() === $session && $question->isActive()) {
            $question->setStatus(Question::STATUS_CLOSED);
            $em->flush();
            $this->addFlash('success', 'Question fermée. Les résultats sont affichés.');
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $sessionId]);
    }

    #[Route('/sessions/{id}/participants-frame', name: 'admin_participants_frame')]
    public function participantsFrame(Session $session): Response
    {
        return $this->render('admin/_participants_frame.html.twig', ['session' => $session]);
    }

    #[Route('/sessions/{sessionId}/questions/{id}/votes-frame', name: 'admin_votes_frame')]
    public function votesFrame(int $sessionId, Question $question, SessionRepository $sessionRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
        if (!$session || $question->getSession() !== $session) {
            return new Response('', 404);
        }
        $totalParticipants = $session->getParticipants()->count();

        return $this->render('admin/_votes_frame.html.twig', [
            'question' => $question,
            'totalParticipants' => $totalParticipants,
        ]);
    }

    #[Route('/sessions/{id}/remove-participant/{pid}', name: 'admin_remove_participant', methods: ['POST'])]
    public function removeParticipant(int $id, int $pid, EntityManagerInterface $em, SessionRepository $sessionRepo, ParticipantRepository $participantRepo): Response
    {
        $session = $sessionRepo->find($id);
        $participant = $participantRepo->find($pid);
        if ($session && $participant && $participant->getSession() === $session) {
            $em->remove($participant);
            $em->flush();
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $id]);
    }
}
