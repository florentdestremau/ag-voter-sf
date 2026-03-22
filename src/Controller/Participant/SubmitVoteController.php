<?php

namespace App\Controller\Participant;

use App\Entity\Vote;
use App\Repository\ChoiceRepository;
use App\Repository\ParticipantRepository;
use App\Repository\VoteRepository;
use App\Service\SessionMercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubmitVoteController extends AbstractController
{
    #[Route('/p/{token}/submit-vote', name: 'participant_submit_vote', methods: ['POST'])]
    public function __invoke(string $token, Request $request, ParticipantRepository $participantRepo, ChoiceRepository $choiceRepo, VoteRepository $voteRepo, EntityManagerInterface $em, SessionMercurePublisher $publisher): Response
    {
        $participant = $participantRepo->findByToken($token);
        if (!$participant) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();

        if (!$activeQuestion) {
            $this->addFlash('error', 'Aucune question active.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        if ($participant->hasVotedOn($activeQuestion)) {
            $this->addFlash('info', 'Vous avez déjà voté sur cette question.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        $choiceId = (int) $request->request->get('choice_id');
        $choice = $choiceRepo->find($choiceId);

        if (!$choice || $choice->getQuestion() !== $activeQuestion) {
            $this->addFlash('error', 'Choix invalide.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        $freeText = null;
        if ($choice->isAllowFreeText()) {
            $freeText = trim($request->request->getString('free_text'));
            if ('' === $freeText) {
                $freeText = null;
            }
        }

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($activeQuestion);
        $vote->setChoice($choice);
        $vote->setFreeText($freeText);
        $em->persist($vote);
        $em->flush();

        $publisher->publishVotesFrame($activeQuestion, $session->getParticipants()->count());

        $this->addFlash('success', 'Vote enregistré !');

        return $this->redirectToRoute('participant_vote', ['token' => $token]);
    }
}
