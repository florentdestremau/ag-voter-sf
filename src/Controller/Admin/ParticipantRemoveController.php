<?php

namespace App\Controller\Admin;

use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use App\Service\SessionMercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParticipantRemoveController extends AbstractController
{
    #[Route('/admin/sessions/{id}/remove-participant/{pid}', name: 'admin_remove_participant', methods: ['POST'])]
    public function __invoke(int $id, int $pid, EntityManagerInterface $em, SessionRepository $sessionRepo, ParticipantRepository $participantRepo, SessionMercurePublisher $publisher): Response
    {
        $session = $sessionRepo->find($id);
        $participant = $participantRepo->find($pid);
        if ($session && $participant && $participant->getSession() === $session) {
            $em->remove($participant);
            $em->flush();

            $publisher->publishParticipantsFrame($session);
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $id]);
    }
}
