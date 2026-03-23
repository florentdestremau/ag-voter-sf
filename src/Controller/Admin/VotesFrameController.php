<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\SessionRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VotesFrameController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/votes-frame', name: 'admin_votes_frame')]
    public function __invoke(int $sessionId, Question $question, SessionRepository $sessionRepository, VoteRepository $voteRepository): Response
    {
        $session = $sessionRepository->find($sessionId);
        if (!$session || $question->getSession() !== $session) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $rawResults = $voteRepository->getResultsForQuestion($question);
        $byChoice = [];
        foreach ($rawResults as $rawResult) {
            $byChoice[(int) $rawResult['choice_id']] = (int) $rawResult['count'];
        }

        return $this->render('admin/_votes_frame.html.twig', [
            'question' => $question,
            'totalParticipants' => $session->getParticipants()->count(),
            'byChoice' => $byChoice,
        ]);
    }
}
