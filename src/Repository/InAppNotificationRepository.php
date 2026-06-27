<?php

namespace App\Repository;

use App\Entity\InAppNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InAppNotification>
 */
class InAppNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InAppNotification::class);
    }

    public function findOneForUserAndFingerprint(User $user, string $fingerprint): ?InAppNotification
    {
        return $this->findOneBy([
            'recipient' => $user,
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * @param list<string> $types
     *
     * @return list<InAppNotification>
     */
    public function findActiveManagedForUser(User $user, array $types): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.recipient = :recipient')
            ->andWhere('notification.active = true')
            ->andWhere('notification.type IN (:types)')
            ->setParameter('recipient', $user)
            ->setParameter('types', $types)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<InAppNotification>
     */
    public function findActiveForUser(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('notification')
            ->addSelect('CASE WHEN notification.readAt IS NULL THEN 0 ELSE 1 END AS HIDDEN readPriority')
            ->andWhere('notification.recipient = :recipient')
            ->andWhere('notification.active = true')
            ->setParameter('recipient', $user)
            ->orderBy('readPriority', 'ASC')
            ->addOrderBy('notification.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('notification')
            ->select('COUNT(notification.id)')
            ->andWhere('notification.recipient = :recipient')
            ->andWhere('notification.active = true')
            ->andWhere('notification.readAt IS NULL')
            ->setParameter('recipient', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
