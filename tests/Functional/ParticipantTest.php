<?php

namespace App\Tests\Functional;

use App\Entity\Choice;
use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ParticipantTest extends WebTestCase
{
    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

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
        $choicePour = (new Choice())->setText('Pour')->setOrderIndex(0);
        $choiceContre = (new Choice())->setText('Contre')->setOrderIndex(1);
        $question->addChoice($choicePour);
        $question->addChoice($choiceContre);
        $em->persist($question);

        $participant = new Participant();
        $participant->setName('Votant Test');
        $participant->setSession($session);
        $em->persist($participant);

        $em->flush();
        $em->clear();
        self::assertNotNull($choicePour->getId());
        self::assertNotNull($choiceContre->getId());
        self::assertNotNull($question->getId());

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
        $client = static::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session join');
        $em->persist($session);
        $em->flush();
        $token = $session->getToken();
        $em->clear();

        $client->request('GET', '/s/'.$token);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Session join');
    }

    public function testJoinWithValidName(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session join valid');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $token = $session->getToken();
        $em->clear();

        $client->request('POST', '/s/'.$token, ['name' => 'Jean Dupont']);
        $this->assertResponseRedirects();
        $client->followRedirect();

        $fresh = $em->find(Session::class, $id);
        self::assertInstanceOf(Session::class, $fresh);
        $this->assertCount(1, $fresh->getParticipants());
        $firstParticipant = $fresh->getParticipants()->first();
        self::assertInstanceOf(Participant::class, $firstParticipant);
        $this->assertSame('Jean Dupont', $firstParticipant->getName());
    }

    public function testJoinWithTooShortName(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session short name');
        $em->persist($session);
        $em->flush();
        $token = $session->getToken();
        $em->clear();

        $client->request('POST', '/s/'.$token, ['name' => 'A']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'entre 2 et 100');
    }

    public function testJoinClosedSessionShowsClosedPage(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session fermée');
        $session->setStatus(Session::STATUS_CLOSED);
        $em->persist($session);
        $em->flush();
        $token = $session->getToken();
        $em->clear();

        $client->request('GET', '/s/'.$token);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'terminée');
    }

    public function testJoinInvalidTokenReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/s/tokeninexistant000000000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // Page de vote
    // -------------------------------------------------------------------------

    public function testVotePageShowsWaitingRoomForPendingSession(): void
    {
        $client = static::createClient();
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

        $client->request('GET', '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'attente');
    }

    public function testVotePageShowsActiveQuestion(): void
    {
        $client = static::createClient();
        [, $pToken] = $this->setupActiveVotingSession();

        $client->request('GET', '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Approuvez-vous ?');
        $this->assertSelectorTextContains('body', 'Pour');
        $this->assertSelectorTextContains('body', 'Contre');
    }

    public function testVotePageShowsAlreadyVotedMessage(): void
    {
        $client = static::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, , $questionId] = $this->setupActiveVotingSession();

        // Ajouter le vote directement en BDD
        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $question = $em->find(Question::class, $questionId);
        $choice = $em->find(Choice::class, $choicePourId);
        self::assertInstanceOf(Participant::class, $participant);
        self::assertInstanceOf(Question::class, $question);
        self::assertInstanceOf(Choice::class, $choice);

        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($question);
        $vote->setChoice($choice);
        $em->persist($vote);
        $em->flush();
        $em->clear();

        $client->request('GET', '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'déjà voté');
    }

    public function testVotePageShowsResultsForClosedQuestion(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session résultats');
        $session->setStatus(Session::STATUS_ACTIVE);
        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question avec résultats');
        $question->setStatus(Question::STATUS_CLOSED);
        $choicePour = (new Choice())->setText('Pour')->setOrderIndex(0);
        $question->addChoice($choicePour);
        $question->addChoice((new Choice())->setText('Contre')->setOrderIndex(1));
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

        $client->request('GET', '/p/'.$pToken);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Question avec résultats');
        $this->assertSelectorTextContains('body', 'Résultats');
    }

    // -------------------------------------------------------------------------
    // Vote
    // -------------------------------------------------------------------------

    public function testSubmitVote(): void
    {
        $client = static::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, , $questionId] = $this->setupActiveVotingSession();

        $client->request('POST', '/p/'.$pToken.'/submit-vote', [
            'choice_id' => $choicePourId,
        ]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        self::assertInstanceOf(Participant::class, $participant);
        $vote = $em->getRepository(Vote::class)->findOneBy([
            'participant' => $participant,
            'question' => $em->find(Question::class, $questionId),
        ]);
        $this->assertNotNull($vote);
        $this->assertSame($choicePourId, $vote->getChoice()->getId());
    }

    public function testCannotVoteTwiceOnSameQuestion(): void
    {
        $client = static::createClient();
        $em = $this->em();

        [, $pToken, $choicePourId, $choiceContreId, $questionId] = $this->setupActiveVotingSession();

        // Premier vote (direct en BDD)
        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $question = $em->find(Question::class, $questionId);
        self::assertInstanceOf(Participant::class, $participant);
        self::assertInstanceOf(Question::class, $question);
        $vote = new Vote();
        $vote->setParticipant($participant);
        $vote->setQuestion($question);
        $choice = $em->find(Choice::class, $choicePourId);
        self::assertInstanceOf(Choice::class, $choice);
        $vote->setChoice($choice);
        $em->persist($vote);
        $em->flush();
        $em->clear();

        // Tentative de second vote via HTTP
        $client->request('POST', '/p/'.$pToken.'/submit-vote', [
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
        $client = static::createClient();
        $em = $this->em();

        [, $pToken] = $this->setupActiveVotingSession();

        $client->request('POST', '/p/'.$pToken.'/submit-vote', ['choice_id' => 99999]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $this->assertCount(0, $em->getRepository(Vote::class)->findBy(['participant' => $participant]));
    }

    public function testCannotVoteWhenNoActiveQuestion(): void
    {
        $client = static::createClient();
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

        $client->request('POST', '/p/'.$pToken.'/submit-vote', ['choice_id' => 1]);
        $this->assertResponseRedirects('/p/'.$pToken);

        $participant = $em->getRepository(Participant::class)->findOneBy(['token' => $pToken]);
        $this->assertCount(0, $em->getRepository(Vote::class)->findBy(['participant' => $participant]));
    }

    // -------------------------------------------------------------------------
    // Quitter
    // -------------------------------------------------------------------------

    public function testLeaveSession(): void
    {
        $client = static::createClient();
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

        $client->request('POST', '/p/'.$pToken.'/leave');
        $this->assertResponseRedirects('/s/'.$sessionToken);

        $this->assertNull($em->find(Participant::class, $pid));
    }

    // -------------------------------------------------------------------------
    // Frame de statut
    // -------------------------------------------------------------------------

    public function testStatusFrameReturns200(): void
    {
        $client = static::createClient();
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

        $client->request('GET', '/p/'.$pToken.'/status');
        $this->assertResponseIsSuccessful();
    }
}
