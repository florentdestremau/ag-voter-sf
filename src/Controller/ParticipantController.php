<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Entity\Vote;
use App\Repository\ChoiceRepository;
use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ParticipantController extends AbstractController
{
    #[Route('/s/{token}', name: 'session_join')]
    public function join(string $token, Request $request, SessionRepository $sessionRepo, EntityManagerInterface $em, SessionInterface $httpSession): Response
    {
        $session = $sessionRepo->findByToken($token);
        if (!$session) {
            throw $this->createNotFoundException('Session introuvable.');
        }

        if ($session->isClosed()) {
            return $this->render('participant/closed.html.twig', ['session' => $session]);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $name = trim($request->request->getString('name'));
            if (strlen($name) < 2 || strlen($name) > 100) {
                $error = 'Le nom doit contenir entre 2 et 100 caractères.';
            } else {
                $participant = new Participant();
                $participant->setName($name);
                $participant->setSession($session);
                $em->persist($participant);
                $em->flush();

                // Store participant token in HTTP session for convenience (optional)
                $httpSession->set('participant_token_'.$session->getToken(), $participant->getToken());

                return $this->redirectToRoute('participant_vote', ['token' => $participant->getToken()]);
            }
        }

        return $this->render('participant/join.html.twig', [
            'session' => $session,
            'error' => $error,
        ]);
    }

    #[Route('/p/{token}', name: 'participant_vote')]
    public function vote(string $token, ParticipantRepository $participantRepo, VoteRepository $voteRepo): Response
    {
        $participant = $participantRepo->findByToken($token);
        if (!$participant) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();
        $alreadyVoted = $activeQuestion ? $participant->hasVotedOn($activeQuestion) : false;

        // Build results for closed questions
        $closedResults = [];
        foreach ($session->getQuestions() as $question) {
            if ($question->isClosed()) {
                $rawResults = $voteRepo->getResultsForQuestion($question);
                $totalVotes = array_sum(array_column($rawResults, 'count'));
                $byChoice = [];
                foreach ($rawResults as $row) {
                    $byChoice[(int) $row['choice_id']] = (int) $row['count'];
                }
                $closedResults[$question->getId()] = [
                    'question' => $question,
                    'byChoice' => $byChoice,
                    'total' => $totalVotes,
                    'freeTexts' => $voteRepo->getFreeTextsForQuestion($question),
                ];
            }
        }

        return $this->render('participant/vote.html.twig', [
            'participant' => $participant,
            'session' => $session,
            'activeQuestion' => $activeQuestion,
            'alreadyVoted' => $alreadyVoted,
            'closedResults' => $closedResults,
        ]);
    }

    #[Route('/p/{token}/status', name: 'participant_status_frame')]
    public function statusFrame(string $token, ParticipantRepository $participantRepo, VoteRepository $voteRepo): Response
    {
        $participant = $participantRepo->findByToken($token);
        if (!$participant) {
            return new Response('', 404);
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();
        $alreadyVoted = $activeQuestion ? $participant->hasVotedOn($activeQuestion) : false;

        // Build results for closed questions
        $closedResults = [];
        foreach ($session->getQuestions() as $question) {
            if ($question->isClosed()) {
                $rawResults = $voteRepo->getResultsForQuestion($question);
                $totalVotes = array_sum(array_column($rawResults, 'count'));
                $byChoice = [];
                foreach ($rawResults as $row) {
                    $byChoice[(int) $row['choice_id']] = (int) $row['count'];
                }
                $closedResults[$question->getId()] = [
                    'question' => $question,
                    'byChoice' => $byChoice,
                    'total' => $totalVotes,
                ];
            }
        }

        return $this->render('participant/_status_frame.html.twig', [
            'participant' => $participant,
            'session' => $session,
            'activeQuestion' => $activeQuestion,
            'alreadyVoted' => $alreadyVoted,
            'closedResults' => $closedResults,
        ]);
    }

    #[Route('/p/{token}/submit-vote', name: 'participant_submit_vote', methods: ['POST'])]
    public function submitVote(string $token, Request $request, ParticipantRepository $participantRepo, ChoiceRepository $choiceRepo, VoteRepository $voteRepo, EntityManagerInterface $em): Response
    {
        $participant = $participantRepo->findByToken($token);
        if (!$participant) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();

        if (!$activeQuestion) {
            $this->addFlash('error', 'Aucune question active.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        if ($participant->hasVotedOn($activeQuestion)) {
            $this->addFlash('info', 'Vous avez déjà voté sur cette question.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        $choiceId = (int) $request->request->get('choice_id');
        $choice = $choiceRepo->find($choiceId);

        if (!$choice || $choice->getQuestion() !== $activeQuestion) {
            $this->addFlash('error', 'Choix invalide.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        $freeText = null;
        if ($choice->isAllowFreeText()) {
            $freeText = trim($request->request->getString('free_text'));
            if ('' === $freeText) {
                $freeText = null;
            }
        }

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($activeQuestion);
        $vote->setChoice($choice);
        $vote->setFreeText($freeText);
        $em->persist($vote);
        $em->flush();

        $this->addFlash('success', 'Vote enregistré !');

        return $this->redirectToRoute('participant_vote', ['token' => $token]);
    }

    #[Route('/p/{token}/leave', name: 'participant_leave', methods: ['POST'])]
    public function leave(string $token, ParticipantRepository $participantRepo, EntityManagerInterface $em): Response
    {
        $participant = $participantRepo->findByToken($token);
        if ($participant) {
            $sessionToken = $participant->getSession()->getToken();
            $em->remove($participant);
            $em->flush();

            return $this->redirectToRoute('session_join', ['token' => $sessionToken]);
        }

        return $this->redirectToRoute('admin_index');
    }
}
