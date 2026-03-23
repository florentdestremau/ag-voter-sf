<?php

namespace App\Controller\Participant;

use App\Entity\Question;
use App\Entity\Participant;
use App\Repository\ParticipantRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VoteController extends AbstractController
{
    #[Route('/p/{token}', name: 'participant_vote')]
    public function __invoke(string $token, ParticipantRepository $participantRepository, VoteRepository $voteRepository): Response
    {
        $participant = $participantRepository->findByToken($token);
        if (!$participant instanceof Participant) {
            throw $this->createNotFoundException('Participant introuvable.');
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
                foreach ($rawResults as $rawResult) {
                    $byChoice[(int) $rawResult['choice_id']] = (int) $rawResult['count'];
                }

                $closedResults[$question->getId()] = [
                    'question' => $question,
                    'byChoice' => $byChoice,
                    'total' => $totalVotes,
                    'freeTexts' => $voteRepository->getFreeTextsForQuestion($question),
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
}
