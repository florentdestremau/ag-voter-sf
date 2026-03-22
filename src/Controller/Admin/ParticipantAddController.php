<?php

namespace App\Controller\Admin;

use App\Entity\Participant;
use App\Repository\SessionRepository;
use App\Service\SessionMercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParticipantAddController extends AbstractController
{
    #[Route('/admin/sessions/{id}/add-participant', name: 'admin_add_participant', methods: ['POST'])]
    public function __invoke(int $id, Request $request, EntityManagerInterface $em, SessionRepository $sessionRepo, SessionMercurePublisher $publisher): Response
    {
        $session = $sessionRepo->find($id);
        $name = trim((string) $request->request->get('name', ''));

        if ($session && !$session->isClosed() && mb_strlen($name) >= 2 && mb_strlen($name) <= 100) {
            $participant = new Participant();
            $participant->setName($name);
            $participant->setSession($session);
            $em->persist($participant);
            $em->flush();

            $publisher->publishParticipantsFrame($session);
        }

        return $this->redirectToRoute('admin_session_show', ['id' => $id]);
    }
}
