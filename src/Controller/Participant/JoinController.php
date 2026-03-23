<?php

namespace App\Controller\Participant;

use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class JoinController extends AbstractController
{
    #[Route('/s/{token}', name: 'session_join')]
    public function __invoke(string $token, Request $request, SessionRepository $sessionRepository, EntityManagerInterface $entityManager, SessionInterface $httpSession): Response
    {
        $session = $sessionRepository->findByToken($token);
        if (!$session instanceof Session) {
            throw $this->createNotFoundException('Session introuvable.');
        }

        if ($session->isClosed()) {
            return $this->render('participant/closed.html.twig', ['session' => $session]);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $name = trim($request->request->getString('name'));
            if (\strlen($name) < 2 || \strlen($name) > 100) {
                $error = 'Le nom doit contenir entre 2 et 100 caractères.';
            } else {
                $participant = new Participant();
                $participant->setName($name);
                $participant->setSession($session);
                $entityManager->persist($participant);
                $entityManager->flush();

                $httpSession->set('participant_token_'.$session->getToken(), $participant->getToken());

                return $this->redirectToRoute('participant_vote', ['token' => $participant->getToken()]);
            }
        }

        return $this->render('participant/join.html.twig', [
            'session' => $session,
            'error' => $error,
        ]);
    }
}
