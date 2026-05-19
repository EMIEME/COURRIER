<?php

namespace App\Repository;

use App\Entity\ListOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListOption>
 */
class ListOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListOption::class);
    }

    /**
     * @return list<ListOption>
     */
    public function findForCategory(string $category, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('option')
            ->andWhere('option.category = :category')
            ->setParameter('category', $category)
            ->orderBy('option.position', 'ASC')
            ->addOrderBy('option.label', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('option.active = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, list<ListOption>>
     */
    public function findGrouped(): array
    {
        $grouped = array_fill_keys(array_keys(ListOption::CATEGORIES), []);

        foreach ($this->findBy([], ['category' => 'ASC', 'position' => 'ASC', 'label' => 'ASC']) as $option) {
            $grouped[$option->getCategory()][] = $option;
        }

        return $grouped;
    }
}
