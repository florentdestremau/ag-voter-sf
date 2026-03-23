<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_participant_question_vote', columns: ['participant_id', 'question_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Participant::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $participant;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\ManyToOne(targetEntity: Choice::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Choice $choice;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $freeText = null;

    #[ORM\Column]
    private DateTimeImmutable $votedAt;

    public function __construct()
    {
        $this->votedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(): Participant
    {
        return $this->participant;
    }

    public function setParticipant(Participant $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getChoice(): Choice
    {
        return $this->choice;
    }

    public function setChoice(Choice $choice): static
    {
        $this->choice = $choice;

        return $this;
    }

    public function getFreeText(): ?string
    {
        return $this->freeText;
    }

    public function setFreeText(?string $freeText): static
    {
        $this->freeText = $freeText;

        return $this;
    }

    public function getVotedAt(): DateTimeImmutable
    {
        return $this->votedAt;
    }
}
