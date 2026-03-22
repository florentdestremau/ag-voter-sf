<?php

namespace App\Tests\Functional;

use App\Entity\Choice;
use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminSessionTest extends WebTestCase
{
    private function getAdminClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'admin123',
        ]);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    // -------------------------------------------------------------------------
    // Authentification
    // -------------------------------------------------------------------------

    public function testAdminIndexRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAdminIndexWithAuth(): void
    {
        $client = $this->getAdminClient();
        $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sessions de vote');
    }

    // -------------------------------------------------------------------------
    // Sessions
    // -------------------------------------------------------------------------

    public function testCreateSession(): void
    {
        $client = $this->getAdminClient();
        $client->request('GET', '/admin/sessions/new');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Créer', ['session[name]' => 'AG de test fonctionnel']);
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'AG de test fonctionnel');
        $this->assertSelectorTextContains('body', 'En attente');
    }

    public function testCreateSessionWithEmptyNameShowsError(): void
    {
        $client = $this->getAdminClient();
        $client->request('GET', '/admin/sessions/new');

        $client->submitForm('Créer', ['session[name]' => '']);

        // Reste sur le formulaire (pas de redirect) avec 422 Unprocessable Content
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('/admin/sessions/new', $client->getRequest()->getRequestUri());
    }

    public function testOpenSession(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session à ouvrir');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$id}/open");
        $this->assertResponseRedirects("/admin/sessions/{$id}");

        $fresh = $em->find(Session::class, $id);
        $this->assertSame(Session::STATUS_ACTIVE, $fresh->getStatus());
    }

    public function testCloseSession(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session à fermer');
        $session->setStatus(Session::STATUS_ACTIVE);
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$id}/close");
        $this->assertResponseRedirects("/admin/sessions/{$id}");

        $fresh = $em->find(Session::class, $id);
        $this->assertSame(Session::STATUS_CLOSED, $fresh->getStatus());
    }

    public function testOpenSessionTransitionPendingToActive(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Transition');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();

        $this->assertSame(Session::STATUS_PENDING, $session->getStatus());
        $em->clear();

        $client->request('POST', "/admin/sessions/{$id}/open");
        $fresh = $em->find(Session::class, $id);
        $this->assertSame(Session::STATUS_ACTIVE, $fresh->getStatus());
    }

    // -------------------------------------------------------------------------
    // Questions
    // -------------------------------------------------------------------------

    public function testCreateQuestion(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session questions');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $em->clear();

        $client->request('GET', "/admin/sessions/{$id}/questions/new");
        $this->assertResponseIsSuccessful();

        $client->submitForm('Enregistrer', [
            'question[text]' => 'Approuvez-vous le rapport ?',
            'question[choices][0][text]' => 'Pour',
            'question[choices][1][text]' => 'Contre',
            'question[choices][2][text]' => 'Abstention',
        ]);

        $this->assertResponseRedirects("/admin/sessions/{$id}");
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Approuvez-vous le rapport ?');
    }

    public function testQuestionFormPreloadsPourContreAbstention(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session preload');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $em->clear();

        $client->request('GET', "/admin/sessions/{$id}/questions/new");
        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('question[choices][0][text]', 'Pour');
        $this->assertInputValueSame('question[choices][1][text]', 'Contre');
        $this->assertInputValueSame('question[choices][2][text]', 'Abstention');
    }

    public function testActivateQuestion(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session activation');
        $session->setStatus(Session::STATUS_ACTIVE);
        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question à activer');
        $question->addChoice((new Choice())->setText('Oui')->setOrderIndex(0));
        $question->addChoice((new Choice())->setText('Non')->setOrderIndex(1));
        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$sid}/questions/{$qid}/activate");
        $this->assertResponseRedirects("/admin/sessions/{$sid}");

        $fresh = $em->find(Question::class, $qid);
        $this->assertSame(Question::STATUS_ACTIVE, $fresh->getStatus());
    }

    public function testCloseQuestion(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session close question');
        $session->setStatus(Session::STATUS_ACTIVE);
        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question active');
        $question->setStatus(Question::STATUS_ACTIVE);
        $question->addChoice((new Choice())->setText('Oui')->setOrderIndex(0));
        $question->addChoice((new Choice())->setText('Non')->setOrderIndex(1));
        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$sid}/questions/{$qid}/close");
        $this->assertResponseRedirects("/admin/sessions/{$sid}");

        $fresh = $em->find(Question::class, $qid);
        $this->assertSame(Question::STATUS_CLOSED, $fresh->getStatus());
    }

    public function testDeletePendingQuestion(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session delete');
        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('À supprimer');
        $question->addChoice((new Choice())->setText('Oui')->setOrderIndex(0));
        $question->addChoice((new Choice())->setText('Non')->setOrderIndex(1));
        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$sid}/questions/{$qid}/delete");
        $this->assertResponseRedirects("/admin/sessions/{$sid}");

        $this->assertNull($em->find(Question::class, $qid));
    }

    public function testCannotActivateQuestionOnPendingSession(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session(); // status = pending
        $session->setName('Session pending');
        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question');
        $question->addChoice((new Choice())->setText('Oui')->setOrderIndex(0));
        $question->addChoice((new Choice())->setText('Non')->setOrderIndex(1));
        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $client->request('POST', "/admin/sessions/{$sid}/questions/{$qid}/activate");

        $fresh = $em->find(Question::class, $qid);
        $this->assertSame(Question::STATUS_PENDING, $fresh->getStatus());
    }

    public function testParticipantsFrameEndpoint(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session frame');
        $em->persist($session);
        $em->flush();
        $id = $session->getId();
        $em->clear();

        $client->request('GET', "/admin/sessions/{$id}/participants-frame");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucun participant');
    }

    public function testParticipantCopyLinkButtonVisibleInFrame(): void
    {
        $client = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session lien participant');
        $em->persist($session);

        $participant = new Participant();
        $participant->setName('Luc Renard');
        $participant->setSession($session);
        $em->persist($participant);
        $em->flush();

        $id = $session->getId();
        $pToken = $participant->getToken();
        $em->clear();

        $client->request('GET', "/admin/sessions/{$id}/participants-frame");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Luc Renard');

        $crawler = $client->getCrawler();
        $input = $crawler->filter('input[value*="/p/'.$pToken.'"]');
        $this->assertCount(1, $input);
    }
}
