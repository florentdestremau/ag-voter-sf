<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\Choice;
use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ParticipantTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /**
     * Crée une session active avec une question active.
     *
     * @return array{0: string, 1: string, 2: int, 3: int, 4: int}
     */
    private function setupActiveVotingSession(): array
    {
        $em = $this->em();

        $session = new Session();
        $session->setName('Session active');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Approuvez-vous ?');
        $question->setStatus(Question::STATUS_ACTIVE);

        $choicePour = new Choice()->setText('Pour')->setOrderIndex(0);
        $choiceContre = new Choice()->setText('Contre')->setOrderIndex(1);
        $question->addChoice($choicePour);
        $question->addChoice($choiceContre);

        $em->persist($question);

        $participant = new Participant();
        $participant->setName('Votant Test');
        $participant->setSession($session);

        $em->persist($participant);

        $em->flush();
        $em->clear();
        $this->assertNotNull($choicePour->getId());
        $this->assertNotNull($choiceContre->getId());
        $this->assertNotNull($question->getId());

        return [
            $session->getToken(),
            $participant->getToken(),
            $choicePour->getId(),
            $choiceContre->getId(),
            $question->getId(),
        ];
    }

    // -------------------------------------------------------------------------
    // Rejoindre
    // -------------------------------------------------------------------------

    public function testJoinPageLoads(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session join');

        $em->persist($session);
        $em->flush();

        $token = $session->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/s/'.$token);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Session join');
    }

    public function testJoinWithValidName(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session join valid');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $token = $session->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, '/s/'.$token, ['name' => 'Jean Dupont']);
        $this->assertResponseRedirects();
        $kernelBrowser->followRedirect();

        $fresh = $em->find(Session::class, $id);
        $this->assertInstanceOf(Session::class, $fresh);
        $this->assertCount(1, $fresh->getParticipants());
        $firstParticipant = $fresh->getParticipants()->first();
        $this->assertInstanceOf(Participant::class, $firstParticipant);
        $this->assertSame('Jean Dupont', $firstParticipant->getName());
    }

    public function testJoinWithTooShortName(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session short name');

        $em->persist($session);
        $em->flush();

        $token = $session->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, '/s/'.$token, ['name' => 'A']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'entre 2 et 100');
    }

    public function testJoinClosedSessionShowsClosedPage(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session fermée');
        $session->setStatus(Session::STATUS_CLOSED);

        $em->persist($session);
        $em->flush();

        $token = $session->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/s/'.$token);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'terminée');
    }

    public function testJoinInvalidTokenReturns404(): void
    {
        $kernelBrowser = self::createClient();
        $kernelBrowser->request(Request::METHOD_GET, '/s/tokeninexistant000000000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // Page de vote
    // -------------------------------------------------------------------------

    public function testVotePageShowsWaitingRoomForPendingSession(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session attente');

        $em->persist($session);

        $participant = new Participant();
        $participant->setName('Test User');
        $participant->setSession($session);

        $em->persist($participant);
        $em->flush();

        $pToken = $participant->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'attente');
    }

    public function testVotePageShowsActiveQuestion(): void
    {
        $kernelBrowser = self::createClient();
        [, $pToken] = $this->setupActiveVotingSession();

        $kernelBrowser->request(Request::METHOD_GET, '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Approuvez-vous ?');
        $this->assertSelectorTextContains('body', 'Pour');
        $this->assertSelectorTextContains('body', 'Contre');
    }

    public function testVotePageShowsAlreadyVotedMessage(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, , $questionId] = $this->setupActiveVotingSession();

        // Ajouter le vote directement en BDD
        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $question = $em->find(Question::class, $questionId);
        $choice = $em->find(Choice::class, $choicePourId);
        $this->assertInstanceOf(Participant::class, $participant);
        $this->assertInstanceOf(Question::class, $question);
        $this->assertInstanceOf(Choice::class, $choice);

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($question);
        $vote->setChoice($choice);

        $em->persist($vote);
        $em->flush();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'déjà voté');
    }

    public function testVotePageShowsResultsForClosedQuestion(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session résultats');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question avec résultats');
        $question->setStatus(Question::STATUS_CLOSED);

        $choicePour = new Choice()->setText('Pour')->setOrderIndex(0);
        $question->addChoice($choicePour);
        $question->addChoice(new Choice()->setText('Contre')->setOrderIndex(1));

        $em->persist($question);

        $participant = new Participant();
        $participant->setName('Lecteur');
        $participant->setSession($session);

        $em->persist($participant);

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($question);
        $vote->setChoice($choicePour);

        $em->persist($vote);

        $em->flush();

        $pToken = $participant->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Question avec résultats');
        $this->assertSelectorTextContains('body', 'Résultats');
    }

    // -------------------------------------------------------------------------
    // Vote
    // -------------------------------------------------------------------------

    public function testSubmitVote(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, , $questionId] = $this->setupActiveVotingSession();

        $kernelBrowser->request(Request::METHOD_POST, '/p/'.$pToken.'/submit-vote', [
            'choice_id' => $choicePourId,
        ]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $this->assertInstanceOf(Participant::class, $participant);
        $vote = $em->getRepository(Vote::class)->findOneBy([
            'participant' => $participant,
            'question' => $em->find(Question::class, $questionId),
        ]);
        $this->assertInstanceOf(Vote::class, $vote);
        $this->assertSame($choicePourId, $vote->getChoice()->getId());
    }

    public function testCannotVoteTwiceOnSameQuestion(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, $choiceContreId, $questionId] = $this->setupActiveVotingSession();

        // Premier vote (direct en BDD)
        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $question = $em->find(Question::class, $questionId);
        $this->assertInstanceOf(Participant::class, $participant);
        $this->assertInstanceOf(Question::class, $question);
        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($question);

        $choice = $em->find(Choice::class, $choicePourId);
        $this->assertInstanceOf(Choice::class, $choice);
        $vote->setChoice($choice);
        $em->persist($vote);
        $em->flush();
        $em->clear();

        // Tentative de second vote via HTTP
        $kernelBrowser->request(Request::METHOD_POST, '/p/'.$pToken.'/submit-vote', [
            'choice_id' => $choiceContreId,
        ]);
        $this->assertResponseRedirects('/p/'.$pToken);

        // Toujours un seul vote, toujours "Pour"
        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $votes = $em->getRepository(Vote::class)->findBy(['participant' => $participant]);
        $this->assertCount(1, $votes);
        $this->assertSame($choicePourId, $votes[0]->getChoice()->getId());
    }

    public function testCannotVoteOnInvalidChoice(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        [, $pToken] = $this->setupActiveVotingSession();

        $kernelBrowser->request(Request::METHOD_POST, '/p/'.$pToken.'/submit-vote', ['choice_id' => 99999]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $this->assertCount(0, $em->getRepository(Vote::class)->findBy(['participant' => $participant]));
    }

    public function testCannotVoteWhenNoActiveQuestion(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session sans question active');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $participant = new Participant();
        $participant->setName('Votant sans question');
        $participant->setSession($session);

        $em->persist($participant);
        $em->flush();

        $pToken = $participant->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, '/p/'.$pToken.'/submit-vote', ['choice_id' => 1]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $this->assertCount(0, $em->getRepository(Vote::class)->findBy(['participant' => $participant]));
    }

    // -------------------------------------------------------------------------
    // Quitter
    // -------------------------------------------------------------------------

    public function testLeaveSession(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session leave');

        $em->persist($session);

        $participant = new Participant();
        $participant->setName('Partant');
        $participant->setSession($session);

        $em->persist($participant);
        $em->flush();

        $pToken = $participant->getToken();
        $pid = $participant->getId();
        $sessionToken = $session->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, '/p/'.$pToken.'/leave');
        $this->assertResponseRedirects('/s/'.$sessionToken);

        $this->assertNotInstanceOf(Participant::class, $em->find(Participant::class, $pid));
    }

    // -------------------------------------------------------------------------
    // Frame de statut
    // -------------------------------------------------------------------------

    public function testStatusFrameReturns200(): void
    {
        $kernelBrowser = self::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session frame');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $participant = new Participant();
        $participant->setName('Frameur');
        $participant->setSession($session);

        $em->persist($participant);
        $em->flush();

        $pToken = $participant->getToken();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, '/p/'.$pToken.'/status');
        $this->assertResponseIsSuccessful();
    }
}
