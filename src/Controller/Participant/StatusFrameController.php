<?php

namespace App\Controller\Participant;

use App\Repository\ParticipantRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatusFrameController extends AbstractController
{
    #[Route('/p/{token}/status', name: 'participant_status_frame')]
    public function __invoke(string $token, ParticipantRepository $participantRepo, VoteRepository $voteRepo): Response
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
                $freeTexts = [];
                foreach ($voteRepo->getFreeTextsForQuestion($question) as $row) {
                    $freeTexts[(int) $row['choice_id']][] = $row['free_text'];
                }
                $closedResults[$question->getId()] = [
                    'question' => $question,
                    'byChoice' => $byChoice,
                    'freeTexts' => $freeTexts,
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
}
