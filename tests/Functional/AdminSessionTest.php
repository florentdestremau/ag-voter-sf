<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Choice;
use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AdminSessionTest extends WebTestCase
{
    private function getAdminClient(): KernelBrowser
    {
        $kernelBrowser = self::createClient();
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        $this->assertInstanceOf(UserProviderInterface::class, $provider);

        $kernelBrowser->loginUser(
            $provider->loadUserByIdentifier('admin'),
            'admin'
        );

        return $kernelBrowser;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    // -------------------------------------------------------------------------
    // Authentification
    // -------------------------------------------------------------------------

    public function testAdminIndexRequiresAuth(): void
    {
        $kernelBrowser = self::createClient();
        $kernelBrowser->request(Request::METHOD_GET, '/admin');
        $this->assertResponseRedirects('/login');
    }

    public function testAdminIndexWithAuth(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $kernelBrowser->request(Request::METHOD_GET, '/admin');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sessions de vote');
    }

    // -------------------------------------------------------------------------
    // Sessions
    // -------------------------------------------------------------------------

    public function testCreateSession(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $kernelBrowser->request(Request::METHOD_GET, '/admin/sessions/new');
        $this->assertResponseIsSuccessful();

        $kernelBrowser->submitForm('Créer', ['session[name]' => 'AG de test fonctionnel']);
        $this->assertResponseRedirects();
        $kernelBrowser->followRedirect();

        $this->assertSelectorTextContains('h1', 'AG de test fonctionnel');
        $this->assertSelectorTextContains('body', 'En attente');
    }

    public function testCreateSessionWithEmptyNameShowsError(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $kernelBrowser->request(Request::METHOD_GET, '/admin/sessions/new');

        $kernelBrowser->submitForm('Créer', ['session[name]' => '']);

        // Reste sur le formulaire (pas de redirect) avec 422 Unprocessable Content
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('/admin/sessions/new', $kernelBrowser->getRequest()->getRequestUri());
    }

    public function testOpenSession(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session à ouvrir');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/open', $id));
        $this->assertResponseRedirects('/admin/sessions/' . $id);

        $fresh = $em->find(Session::class, $id);
        $this->assertInstanceOf(Session::class, $fresh);
        $this->assertSame(Session::STATUS_ACTIVE, $fresh->getStatus());
    }

    public function testCloseSession(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session à fermer');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/close', $id));
        $this->assertResponseRedirects('/admin/sessions/' . $id);

        $fresh = $em->find(Session::class, $id);
        $this->assertInstanceOf(Session::class, $fresh);
        $this->assertSame(Session::STATUS_CLOSED, $fresh->getStatus());
    }

    public function testOpenSessionTransitionPendingToActive(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Transition');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();

        $this->assertSame(Session::STATUS_PENDING, $session->getStatus());
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/open', $id));
        $fresh = $em->find(Session::class, $id);
        $this->assertInstanceOf(Session::class, $fresh);
        $this->assertSame(Session::STATUS_ACTIVE, $fresh->getStatus());
    }

    // -------------------------------------------------------------------------
    // Questions
    // -------------------------------------------------------------------------

    public function testCreateQuestion(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session questions');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, sprintf('/admin/sessions/%s/questions/new', $id));
        $this->assertResponseIsSuccessful();

        $kernelBrowser->submitForm('Enregistrer', [
            'question[text]' => 'Approuvez-vous le rapport ?',
            'question[choices][0][text]' => 'Pour',
            'question[choices][1][text]' => 'Contre',
            'question[choices][2][text]' => 'Abstention',
        ]);

        $this->assertResponseRedirects('/admin/sessions/' . $id);
        $kernelBrowser->followRedirect();
        $this->assertSelectorTextContains('body', 'Approuvez-vous le rapport ?');
    }

    public function testQuestionFormPreloadsPourContreAbstention(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session preload');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, sprintf('/admin/sessions/%s/questions/new', $id));
        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('question[choices][0][text]', 'Pour');
        $this->assertInputValueSame('question[choices][1][text]', 'Contre');
        $this->assertInputValueSame('question[choices][2][text]', 'Abstention');
    }

    public function testActivateQuestion(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session activation');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question à activer');
        $question->addChoice(new Choice()->setText('Oui')->setOrderIndex(0));
        $question->addChoice(new Choice()->setText('Non')->setOrderIndex(1));

        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/questions/%s/activate', $sid, $qid));
        $this->assertResponseRedirects('/admin/sessions/' . $sid);

        $fresh = $em->find(Question::class, $qid);
        $this->assertInstanceOf(Question::class, $fresh);
        $this->assertSame(Question::STATUS_ACTIVE, $fresh->getStatus());
    }

    public function testCloseQuestion(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session close question');
        $session->setStatus(Session::STATUS_ACTIVE);

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question active');
        $question->setStatus(Question::STATUS_ACTIVE);
        $question->addChoice(new Choice()->setText('Oui')->setOrderIndex(0));
        $question->addChoice(new Choice()->setText('Non')->setOrderIndex(1));

        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/questions/%s/close', $sid, $qid));
        $this->assertResponseRedirects('/admin/sessions/' . $sid);

        $fresh = $em->find(Question::class, $qid);
        $this->assertInstanceOf(Question::class, $fresh);
        $this->assertSame(Question::STATUS_CLOSED, $fresh->getStatus());
    }

    public function testDeletePendingQuestion(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session delete');

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('À supprimer');
        $question->addChoice(new Choice()->setText('Oui')->setOrderIndex(0));
        $question->addChoice(new Choice()->setText('Non')->setOrderIndex(1));

        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/questions/%s/delete', $sid, $qid));
        $this->assertResponseRedirects('/admin/sessions/' . $sid);

        $this->assertNotInstanceOf(Question::class, $em->find(Question::class, $qid));
    }

    public function testCannotActivateQuestionOnPendingSession(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session(); // status = pending
        $session->setName('Session pending');

        $em->persist($session);

        $question = new Question();
        $question->setSession($session);
        $question->setText('Question');
        $question->addChoice(new Choice()->setText('Oui')->setOrderIndex(0));
        $question->addChoice(new Choice()->setText('Non')->setOrderIndex(1));

        $em->persist($question);
        $em->flush();

        $sid = $session->getId();
        $qid = $question->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_POST, sprintf('/admin/sessions/%s/questions/%s/activate', $sid, $qid));

        $fresh = $em->find(Question::class, $qid);
        $this->assertInstanceOf(Question::class, $fresh);
        $this->assertSame(Question::STATUS_PENDING, $fresh->getStatus());
    }

    public function testParticipantsFrameEndpoint(): void
    {
        $kernelBrowser = $this->getAdminClient();
        $em = $this->em();

        $session = new Session();
        $session->setName('Session frame');

        $em->persist($session);
        $em->flush();

        $id = $session->getId();
        $em->clear();

        $kernelBrowser->request(Request::METHOD_GET, sprintf('/admin/sessions/%s/participants-frame', $id));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucun participant');
    }

    public function testParticipantCopyLinkButtonVisibleInFrame(): void
    {
        $kernelBrowser = $this->getAdminClient();
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

        $kernelBrowser->request(Request::METHOD_GET, sprintf('/admin/sessions/%s/participants-frame', $id));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Luc Renard');

        $crawler = $kernelBrowser->getCrawler();
        $input = $crawler->filter('input[value*="/p/'.$pToken.'"]');
        $this->assertCount(1, $input);
    }
}
