<?php

namespace App\Repository;

use App\Entity\Vote;
use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    /**
     * @return array<int, array{choice_id: int, count: int}>
     */
    public function getResultsForQuestion(Question $question): array
    {
        return $this->createQueryBuilder('v')
            ->select('IDENTITY(v.choice) as choice_id, COUNT(v.id) as count')
            ->where('v.question = :question')
            ->setParameter('question', $question)
            ->groupBy('v.choice')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array<int, array{choice_id: int, free_text: string}>
     */
    public function getFreeTextsForQuestion(Question $question): array
    {
        return $this->createQueryBuilder('v')
            ->select('IDENTITY(v.choice) as choice_id, v.freeText as free_text')
            ->where('v.question = :question')
            ->andWhere('v.freeText IS NOT NULL')
            ->setParameter('question', $question)
            ->getQuery()
            ->getArrayResult();
    }
}
