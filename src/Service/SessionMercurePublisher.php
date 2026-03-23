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
        private readonly HubInterface $hub,
        private readonly Environment $twigEnvironment,
        private readonly VoteRepository $voteRepository,
    ) {
    }

    public function publishParticipantsFrame(Session $session): void
    {
        $html = $this->twigEnvironment->render('admin/_participants_frame.html.twig', ['session' => $session]);
        $this->publishStream(\sprintf('session/%s/participants', $session->getId()), 'participants-frame', $html);
    }

    public function publishVotesFrame(Question $question, int $totalParticipants): void
    {
        $rawResults = $this->voteRepository->getResultsForQuestion($question);
        $byChoice = [];
        foreach ($rawResults as $rawResult) {
            $byChoice[(int) $rawResult['choice_id']] = (int) $rawResult['count'];
        }

        $html = $this->twigEnvironment->render('admin/_votes_frame.html.twig', [
            'question' => $question,
            'totalParticipants' => $totalParticipants,
            'byChoice' => $byChoice,
        ]);
        $this->publishStream(
            \sprintf('session/%s/votes', $question->getSession()->getId()),
            'votes-frame-'.$question->getId(),
            $html,
        );
    }

    public function publishParticipantReload(Session $session): void
    {
        $payload = json_encode(['type' => 'reload']);
        if (false === $payload) {
            return;
        }

        $this->hub->publish(new Update(
            \sprintf('session/%s/participant-events', $session->getId()),
            $payload,
        ));
    }

    private function publishStream(string $topic, string $target, string $html): void
    {
        $stream = \sprintf('<turbo-stream action="replace" target="%s"><template>%s</template></turbo-stream>', $target, $html);
        $this->hub->publish(new Update($topic, $stream));
    }
}
