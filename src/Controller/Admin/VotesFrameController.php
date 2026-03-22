<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VotesFrameController extends AbstractController
{
    #[Route('/admin/sessions/{sessionId}/questions/{id}/votes-frame', name: 'admin_votes_frame')]
    public function __invoke(int $sessionId, Question $question, SessionRepository $sessionRepo): Response
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
}
