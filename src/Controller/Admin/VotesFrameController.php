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
    public function __invoke(int $sessionId, Question $question, SessionRepository $sessionRepo, VoteRepository $voteRepo): Response
    {
        $session = $sessionRepo->find($sessionId);
        if (!$session || $question->getSession() !== $session) {
            return new Response('', 404);
        }

        $rawResults = $voteRepo->getResultsForQuestion($question);
        $byChoice = [];
        foreach ($rawResults as $row) {
            $byChoice[(int) $row['choice_id']] = (int) $row['count'];
        }

        return $this->render('admin/_votes_frame.html.twig', [
            'question' => $question,
            'totalParticipants' => $session->getParticipants()->count(),
            'byChoice' => $byChoice,
        ]);
    }
}
