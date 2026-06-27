<?php

namespace App\Repository;

use App\Entity\Courrier;
use App\Entity\Destinataire;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
        return $this->createSearchQueryBuilder($filters)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Courrier>
     */
    public function searchPaginated(array $filters, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        $query = $this->createSearchQueryBuilder($filters)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return iterator_to_array((new Paginator($query, true))->getIterator(), false);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countSearch(array $filters): int
    {
        return (int) $this->createSearchQueryBuilder($filters, false)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function createSearchQueryBuilder(array $filters, bool $forResults = true): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if ($forResults) {
            $qb
                ->leftJoin('c.assignedTo', 'assignedTo')
                ->addSelect('assignedTo')
                ->distinct();

            if (!empty($filters['prioritizeUrgent'])) {
                $qb
                    ->addSelect('CASE WHEN c.status = :urgentStatus THEN 0 ELSE 1 END AS HIDDEN urgencyPriority')
                    ->addSelect('CASE WHEN c.responseDueAt IS NULL THEN 1 ELSE 0 END AS HIDDEN dueDateMissing')
                    ->setParameter('urgentStatus', Courrier::STATUS_URGENT)
                    ->orderBy('urgencyPriority', 'ASC')
                    ->addOrderBy('dueDateMissing', 'ASC')
                    ->addOrderBy('c.responseDueAt', 'ASC')
                    ->addOrderBy('c.mailDate', 'DESC')
                    ->addOrderBy('c.id', 'DESC');
            } else {
                $qb
                    ->orderBy('c.mailDate', 'DESC')
                    ->addOrderBy('c.id', 'DESC');
            }
        }

        if (!empty($filters['pendingDeletion'])) {
            $qb->andWhere('c.deletionRequestedAt IS NOT NULL');
        } else {
            $qb->andWhere('c.deletionRequestedAt IS NULL');
        }

        if (!empty($filters['query'])) {
            $qb->andWhere('LOWER(c.reference) LIKE :query OR LOWER(c.subject) LIKE :query OR LOWER(c.content) LIKE :query OR LOWER(c.sender) LIKE :query OR LOWER(c.recipient) LIKE :query OR LOWER(c.localisation) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower((string) $filters['query']).'%');
        }

        if (!empty($filters['sender'])) {
            $qb->andWhere('LOWER(c.sender) LIKE :sender OR LOWER(c.recipient) LIKE :sender')
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

        if (!empty($filters['assignedTo']) && $filters['assignedTo'] instanceof User) {
            $qb->andWhere(':assignedTo MEMBER OF c.assignedTo')
                ->setParameter('assignedTo', $filters['assignedTo']);
        }

        if (!empty($filters['destinataire']) && $filters['destinataire'] instanceof Destinataire) {
            $qb->andWhere($qb->expr()->orX('c.senderContact = :destinataire', ':destinataire MEMBER OF c.destinataires'))
                ->setParameter('destinataire', $filters['destinataire']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('c.mailDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']), Types::DATE_IMMUTABLE);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('c.mailDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']), Types::DATE_IMMUTABLE);
        }

        return $qb;
    }

    public function countLinkedToDestinataire(Destinataire $destinataire): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->andWhere('c.senderContact = :destinataire OR :destinataire MEMBER OF c.destinataires')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->setParameter('destinataire', $destinataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Courrier>
     */
    public function findOverdueInProgress(\DateTimeInterface $today): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.responseDueAt IS NOT NULL')
            ->andWhere('c.responseDueAt < :today')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->setParameter('status', Courrier::STATUS_EN_COURS)
            ->setParameter('today', \DateTimeImmutable::createFromInterface($today), Types::DATE_IMMUTABLE)
            ->orderBy('c.responseDueAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int>
     */
    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     */
    public function countByStatus(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.status AS status, COUNT(c.id) AS total')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->groupBy('c.status');

        $this->applyPeriodFilters($qb, $filters);

        $rows = $qb->getQuery()->getArrayResult();

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
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     */
    public function countByDirection(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.direction AS direction, COUNT(c.id) AS total')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->groupBy('c.direction');

        $this->applyPeriodFilters($qb, $filters);

        $rows = $qb->getQuery()->getArrayResult();

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

    public function countPendingDeletion(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.deletionRequestedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<int>
     */
    public function findPendingDeletionIds(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id AS id')
            ->andWhere('c.deletionRequestedAt IS NOT NULL')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function countUpcomingDueForUser(User $user, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->createUpcomingDueForUserQueryBuilder($user, $from, $to)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<int>
     */
    public function findUpcomingDueIdsForUser(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = $this->createUpcomingDueForUserQueryBuilder($user, $from, $to)
            ->select('DISTINCT c.id AS id')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @return list<Courrier>
     */
    public function findUpcomingDueForUser(User $user, \DateTimeInterface $from, \DateTimeInterface $to, int $limit = 3): array
    {
        return $this->createUpcomingDueForUserQueryBuilder($user, $from, $to)
            ->orderBy('c.responseDueAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyPeriodFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('c.mailDate >= :countDateFrom')
                ->setParameter('countDateFrom', new \DateTimeImmutable((string) $filters['dateFrom']), Types::DATE_IMMUTABLE);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('c.mailDate <= :countDateTo')
                ->setParameter('countDateTo', new \DateTimeImmutable((string) $filters['dateTo']), Types::DATE_IMMUTABLE);
        }
    }

    private function createUpcomingDueForUserQueryBuilder(User $user, \DateTimeInterface $from, \DateTimeInterface $to): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere(':assignedTo MEMBER OF c.assignedTo')
            ->andWhere('c.status != :treatedStatus')
            ->andWhere('c.responseDueAt IS NOT NULL')
            ->andWhere('c.responseDueAt >= :fromDate')
            ->andWhere('c.responseDueAt <= :toDate')
            ->andWhere('c.deletionRequestedAt IS NULL')
            ->setParameter('assignedTo', $user)
            ->setParameter('treatedStatus', Courrier::STATUS_TRAITE)
            ->setParameter('fromDate', \DateTimeImmutable::createFromInterface($from), Types::DATE_IMMUTABLE)
            ->setParameter('toDate', \DateTimeImmutable::createFromInterface($to), Types::DATE_IMMUTABLE);
    }
}
