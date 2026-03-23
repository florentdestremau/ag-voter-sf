<?php

namespace App\Controller\Participant;

use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Vote;
use App\Repository\ChoiceRepository;
use App\Repository\ParticipantRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubmitVoteController extends AbstractController
{
    #[Route('/p/{token}/submit-vote', name: 'participant_submit_vote', methods: ['POST'])]
    public function __invoke(string $token, Request $request, ParticipantRepository $participantRepository, ChoiceRepository $choiceRepository, VoteRepository $voteRepository, EntityManagerInterface $entityManager): Response
    {
        $participant = $participantRepository->findByToken($token);
        if (!$participant instanceof Participant) {
            throw $this->createNotFoundException('Participant introuvable.');
        }

        $session = $participant->getSession();
        $activeQuestion = $session->getActiveQuestion();

        if (!$activeQuestion instanceof Question) {
            $this->addFlash('error', 'Aucune question active.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        if ($participant->hasVotedOn($activeQuestion)) {
            $this->addFlash('info', 'Vous avez déjà voté sur cette question.');

            return $this->redirectToRoute('participant_vote', ['token' => $token]);
        }

        $choiceId = (int) $request->request->get('choice_id');
        $choice = $choiceRepository->find($choiceId);

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

        $entityManager->persist($vote);
        $entityManager->flush();

        $this->addFlash('success', 'Vote enregistré !');

        return $this->redirectToRoute('participant_vote', ['token' => $token]);
    }
}
