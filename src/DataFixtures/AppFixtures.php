<?php

namespace App\DataFixtures;

use App\Entity\Choice;
use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\Vote;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Session 1 : en attente (pour tester la salle d'attente)
        $sessionPending = new Session();
        $sessionPending->setName('AG Test - En attente');

        $manager->persist($sessionPending);

        $q1 = new Question();
        $q1->setText('Approuvez-vous le rapport moral ?');
        $q1->setSession($sessionPending);
        $q1->setOrderIndex(0);
        foreach (['Pour', 'Contre', 'Abstention'] as $i => $label) {
            $q1->addChoice(new Choice()->setText($label)->setOrderIndex($i));
        }

        $manager->persist($q1);

        // Session 2 : active avec une question active et des participants
        $sessionActive = new Session();
        $sessionActive->setName('AG Test - En cours');
        $sessionActive->setStatus(Session::STATUS_ACTIVE);

        $manager->persist($sessionActive);

        $q2 = new Question();
        $q2->setText('Approuvez-vous le budget prévisionnel ?');
        $q2->setSession($sessionActive);
        $q2->setStatus(Question::STATUS_ACTIVE);
        $q2->setOrderIndex(0);

        $choicePour = new Choice()->setText('Pour')->setOrderIndex(0);
        $choiceContre = new Choice()->setText('Contre')->setOrderIndex(1);
        $choiceAbs = new Choice()->setText('Abstention')->setOrderIndex(2);
        $q2->addChoice($choicePour);
        $q2->addChoice($choiceContre);
        $q2->addChoice($choiceAbs);

        $manager->persist($q2);

        $q3 = new Question();
        $q3->setText("Validez-vous les comptes de l'exercice ?");
        $q3->setSession($sessionActive);
        $q3->setStatus(Question::STATUS_PENDING);
        $q3->setOrderIndex(1);
        $q3->addChoice(new Choice()->setText('Pour')->setOrderIndex(0));
        $q3->addChoice(new Choice()->setText('Contre')->setOrderIndex(1));
        $q3->addChoice(new Choice()->setText('Abstention')->setOrderIndex(2));

        $manager->persist($q3);

        $p1 = new Participant();
        $p1->setName('Alice Dupont');
        $p1->setSession($sessionActive);

        $manager->persist($p1);

        $p2 = new Participant();
        $p2->setName('Bob Martin');
        $p2->setSession($sessionActive);

        $manager->persist($p2);

        // Alice a voté
        $vote1 = new Vote();
        $vote1->setParticipant($p1);
        $vote1->setQuestion($q2);
        $vote1->setChoice($choicePour);

        $manager->persist($vote1);

        // Session 3 : fermée avec résultats complets
        $sessionClosed = new Session();
        $sessionClosed->setName('AG 2025 - Terminée');
        $sessionClosed->setStatus(Session::STATUS_CLOSED);

        $manager->persist($sessionClosed);

        $q4 = new Question();
        $q4->setText('Renouvellement du bureau ?');
        $q4->setSession($sessionClosed);
        $q4->setStatus(Question::STATUS_CLOSED);
        $q4->setOrderIndex(0);

        $choicePour4 = new Choice()->setText('Pour')->setOrderIndex(0);
        $choiceContre4 = new Choice()->setText('Contre')->setOrderIndex(1);
        $q4->addChoice($choicePour4);
        $q4->addChoice($choiceContre4);

        $manager->persist($q4);

        $pClosed = new Participant();
        $pClosed->setName('Ancien participant');
        $pClosed->setSession($sessionClosed);

        $manager->persist($pClosed);

        $voteClosed = new Vote();
        $voteClosed->setParticipant($pClosed);
        $voteClosed->setQuestion($q4);
        $voteClosed->setChoice($choicePour4);

        $manager->persist($voteClosed);

        $manager->flush();

        $this->addReference('session-pending', $sessionPending);
        $this->addReference('session-active', $sessionActive);
        $this->addReference('session-closed', $sessionClosed);
        $this->addReference('question-active', $q2);
        $this->addReference('choice-pour', $choicePour);
        $this->addReference('participant-alice', $p1);
        $this->addReference('participant-bob', $p2);
    }
}
