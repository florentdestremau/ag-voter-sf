<?php

namespace App\Controller\Participant;

use App\Entity\Question;
use App\Entity\Participant;
use App\Repository\ParticipantRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatusFrameController extends AbstractController
{
    #[Route('/p/{token}/status', name: 'participant_status_frame')]
    public function __invoke(string $token, ParticipantRepository $participantRepository, VoteRepository $voteRepository): Response
    {
        $participant = $participantRepository->findByToken($token);
        if (!$participant instanceof Participant) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();
        $alreadyVoted = $activeQuestion instanceof Question && $participant->hasVotedOn($activeQuestion);

        // Build results for closed questions
        $closedResults = [];
        foreach ($session->getQuestions() as $question) {
            if ($question->isClosed()) {
                $rawResults = $voteRepository->getResultsForQuestion($question);
                $totalVotes = array_sum(array_column($rawResults, 'count'));
                $byChoice = [];
                foreach ($rawResults as $row) {
                    $byChoice[(int) $row['choice_id']] = (int) $row['count'];
                }

                $freeTexts = [];
                foreach ($voteRepository->getFreeTextsForQuestion($question) as $row) {
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
