<?php

namespace App\EventListener;

use App\Entity\Participant;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\Vote;
use App\Service\SessionMercurePublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class MercureDoctrineListener
{
    /** @var array<int, true> */
    private array $participantsSessions = [];

    /** @var array<int, true> */
    private array $votesSessions = [];

    /** @var array<int, true> */
    private array $reloadSessions = [];

    public function __construct(
        private SessionMercurePublisher $publisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->participantsSessions = [];
        $this->votesSessions = [];
        $this->reloadSessions = [];

        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->track($entity);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->track($entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->track($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();

        try {
            foreach ($this->participantsSessions as $sessionId => $_) {
                $session = $em->find(Session::class, $sessionId);
                if ($session) {
                    $this->publisher->publishParticipantsFrame($session);
                }
            }

            foreach ($this->votesSessions as $sessionId => $_) {
                $session = $em->find(Session::class, $sessionId);
                if (!$session) {
                    continue;
                }
                $activeQuestion = $session->getActiveQuestion();
                if ($activeQuestion) {
                    $this->publisher->publishVotesFrame($activeQuestion, $session->getParticipants()->count());
                }
            }

            foreach ($this->reloadSessions as $sessionId => $_) {
                $session = $em->find(Session::class, $sessionId);
                if ($session) {
                    $this->publisher->publishParticipantReload($session);
                }
            }
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to publish Mercure update: '.$e->getMessage());
        } finally {
            $this->participantsSessions = [];
            $this->votesSessions = [];
            $this->reloadSessions = [];
        }
    }

    private function track(object $entity): void
    {
        if ($entity instanceof Participant) {
            $sessionId = $entity->getSession()->getId();
            if (null !== $sessionId) {
                $this->participantsSessions[$sessionId] = true;
            }
        }

        if ($entity instanceof Vote) {
            $sessionId = $entity->getQuestion()->getSession()->getId();
            if (null !== $sessionId) {
                $this->votesSessions[$sessionId] = true;
            }
        }

        if ($entity instanceof Question) {
            $sessionId = $entity->getSession()->getId();
            if (null !== $sessionId) {
                $this->reloadSessions[$sessionId] = true;
            }
        }

        if ($entity instanceof Session) {
            $sessionId = $entity->getId();
            if (null !== $sessionId) {
                $this->reloadSessions[$sessionId] = true;
            }
        }
    }
}
