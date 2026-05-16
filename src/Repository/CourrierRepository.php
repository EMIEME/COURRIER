<?php

namespace App\Repository;

use App\Entity\Courrier;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Courrier>
 */
class CourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Courrier::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Courrier>
     */
    public function search(array $filters): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.assignedTo', 'assignedTo')
            ->addSelect('assignedTo')
            ->distinct()
            ->orderBy('c.mailDate', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if (!empty($filters['query'])) {
            $qb->andWhere('LOWER(c.subject) LIKE :query OR LOWER(c.content) LIKE :query OR LOWER(c.sender) LIKE :query OR LOWER(c.recipient) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower((string) $filters['query']).'%');
        }

        if (!empty($filters['sender'])) {
            $qb->andWhere('LOWER(c.sender) LIKE :sender')
                ->setParameter('sender', '%'.mb_strtolower((string) $filters['sender']).'%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['direction'])) {
            $qb->andWhere('c.direction = :direction')
                ->setParameter('direction', $filters['direction']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['assignedTo']) && $filters['assignedTo'] instanceof User) {
            $qb->andWhere(':assignedTo MEMBER OF c.assignedTo')
                ->setParameter('assignedTo', $filters['assignedTo']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('c.mailDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('c.mailDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.status AS status, COUNT(c.id) AS total')
            ->groupBy('c.status')
            ->getQuery()
            ->getArrayResult();

        $stats = [
            Courrier::STATUS_EN_COURS => 0,
            Courrier::STATUS_TRAITE => 0,
            Courrier::STATUS_URGENT => 0,
        ];

        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['total'];
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function countByDirection(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.direction AS direction, COUNT(c.id) AS total')
            ->groupBy('c.direction')
            ->getQuery()
            ->getArrayResult();

        $stats = [
            Courrier::DIRECTION_ENTRANT => 0,
            Courrier::DIRECTION_SORTANT => 0,
            Courrier::DIRECTION_INTERNE => 0,
        ];

        foreach ($rows as $row) {
            $stats[$row['direction']] = (int) $row['total'];
        }

        return $stats;
    }
}
