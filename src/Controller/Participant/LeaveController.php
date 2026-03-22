<?php

namespace App\Controller\Participant;

use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaveController extends AbstractController
{
    #[Route('/p/{token}/leave', name: 'participant_leave', methods: ['POST'])]
    public function __invoke(string $token, ParticipantRepository $participantRepo, EntityManagerInterface $em): Response
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
