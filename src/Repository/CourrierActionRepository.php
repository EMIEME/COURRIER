<?php

namespace App\Repository;

use App\Entity\CourrierAction;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourrierAction>
 */
class CourrierActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourrierAction::class);
    }

    public function countRecentAssignmentsForUser(User $user, \DateTimeInterface $since): int
    {
        return (int) $this->createRecentAssignmentsForUserQueryBuilder($user, $since)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<CourrierAction>
     */
    public function findRecentAssignmentsForUser(User $user, \DateTimeInterface $since, int $limit = 3): array
    {
        return $this->createRecentAssignmentsForUserQueryBuilder($user, $since)
            ->addSelect('c')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function createRecentAssignmentsForUserQueryBuilder(User $user, \DateTimeInterface $since): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.courrier', 'c')
            ->andWhere('a.actionType = :actionType')
            ->andWhere('a.createdAt >= :since')
            ->andWhere(':assignedTo MEMBER OF c.assignedTo')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->setParameter('actionType', CourrierAction::TYPE_ASSIGNED)
            ->setParameter('since', \DateTimeImmutable::createFromInterface($since), Types::DATETIME_IMMUTABLE)
            ->setParameter('assignedTo', $user);
    }
}
