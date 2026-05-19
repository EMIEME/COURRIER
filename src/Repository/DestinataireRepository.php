<?php

namespace App\Repository;

use App\Entity\Destinataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destinataire>
 */
class DestinataireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destinataire::class);
    }

    /**
     * @return list<Destinataire>
     */
    public function searchPaginated(?string $query, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($query) {
            $qb->andWhere('LOWER(d.name) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countSearch(?string $query): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)');

        if ($query) {
            $qb->andWhere('LOWER(d.name) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Destinataire>
     */
    public function findForAutocomplete(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
