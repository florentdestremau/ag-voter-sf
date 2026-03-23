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
        private readonly SessionMercurePublisher $sessionMercurePublisher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
    {
        $this->participantsSessions = [];
        $this->votesSessions = [];
        $this->reloadSessions = [];

        $unitOfWork = $onFlushEventArgs->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->track($entity);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $this->track($entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->track($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $postFlushEventArgs): void
    {
        $entityManager = $postFlushEventArgs->getObjectManager();

        try {
            foreach ($this->participantsSessions as $sessionId => $_) {
                $session = $entityManager->find(Session::class, $sessionId);
                if ($session instanceof Session) {
                    $this->sessionMercurePublisher->publishParticipantsFrame($session);
                }
            }

            foreach (array_keys($this->votesSessions) as $sessionId) {
                $session = $entityManager->find(Session::class, $sessionId);
                if (!$session instanceof Session) {
                    continue;
                }

                $activeQuestion = $session->getActiveQuestion();
                if ($activeQuestion instanceof Question) {
                    $this->sessionMercurePublisher->publishVotesFrame($activeQuestion, $session->getParticipants()->count());
                }
            }

            foreach (array_keys($this->reloadSessions) as $sessionId) {
                $session = $entityManager->find(Session::class, $sessionId);
                if ($session instanceof Session) {
                    $this->sessionMercurePublisher->publishParticipantReload($session);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger?->error('Failed to publish Mercure update: '.$throwable->getMessage());
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
