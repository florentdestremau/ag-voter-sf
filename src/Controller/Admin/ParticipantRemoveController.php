<?php

namespace App\Controller\Admin;

use App\Repository\ParticipantRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParticipantRemoveController extends AbstractController
{
    #[Route('/admin/sessions/{id}/remove-participant/{pid}', name: 'admin_remove_participant', methods: ['POST'])]
    public function __invoke(int $id, int $pid, EntityManagerInterface $entityManager, SessionRepository $sessionRepository, ParticipantRepository $participantRepository): Response
    {
        $session = $sessionRepository->find($id);
        $participant = $participantRepository->find($pid);
        if ($session && $participant && $participant->getSession() === $session) {
            $entityManager->remove($participant);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $id]);
    }
}
