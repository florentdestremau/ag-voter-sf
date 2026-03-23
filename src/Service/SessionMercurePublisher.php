<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Session;
use App\Repository\VoteRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

class SessionMercurePublisher
{
    public function __construct(
        private HubInterface $hub,
        private Environment $twig,
        private VoteRepository $voteRepo,
    ) {
    }

    public function publishParticipantsFrame(Session $session): void
    {
        $html = $this->twig->render('admin/_participants_frame.html.twig', ['session' => $session]);
        $this->publishStream("session/{$session->getId()}/participants", 'participants-frame', $html);
    }

    public function publishVotesFrame(Question $question, int $totalParticipants): void
    {
        $rawResults = $this->voteRepo->getResultsForQuestion($question);
        $byChoice = [];
        foreach ($rawResults as $row) {
            $byChoice[(int) $row['choice_id']] = (int) $row['count'];
        }

        $html = $this->twig->render('admin/_votes_frame.html.twig', [
            'question' => $question,
            'totalParticipants' => $totalParticipants,
            'byChoice' => $byChoice,
        ]);
        $this->publishStream(
            "session/{$question->getSession()->getId()}/votes",
            "votes-frame-{$question->getId()}",
            $html,
        );
    }

    public function publishParticipantReload(Session $session): void
    {
        $this->hub->publish(new Update(
            "session/{$session->getId()}/participant-events",
            json_encode(['type' => 'reload']),
        ));
    }

    private function publishStream(string $topic, string $target, string $html): void
    {
        $stream = "<turbo-stream action=\"replace\" target=\"{$target}\"><template>{$html}</template></turbo-stream>";
        $this->hub->publish(new Update($topic, $stream));
    }
}
