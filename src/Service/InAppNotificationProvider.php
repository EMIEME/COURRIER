<?php

namespace App\Service;

use App\Entity\Courrier;
use App\Entity\CourrierAction;
use App\Entity\InAppNotification;
use App\Entity\User;
use App\Repository\CourrierActionRepository;
use App\Repository\CourrierRepository;
use App\Repository\InAppNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class InAppNotificationProvider
{
    private const MANAGED_TYPES = ['assignment', 'deadline', 'deletion'];

    public function __construct(
        private readonly CourrierRepository $courrierRepository,
        private readonly CourrierActionRepository $courrierActionRepository,
        private readonly InAppNotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     count: int,
     *     items: list<array{
     *         type: string,
     *         severity: string,
     *         id: int|null,
     *         read: bool,
     *         title: string,
     *         message: string,
     *         route: string,
     *         routeParams: array<string, mixed>
     *     }>
     * }
     */
    public function forUser(mixed $user): array
    {
        if (!$user instanceof User) {
            return ['count' => 0, 'items' => []];
        }

        try {
            $this->syncGeneratedNotifications($user, $this->buildGeneratedNotifications($user));

            return [
                'count' => $this->notificationRepository->countUnreadForUser($user),
                'items' => array_map(
                    fn (InAppNotification $notification): array => $this->toViewItem($notification),
                    $this->notificationRepository->findActiveForUser($user)
                ),
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('Impossible de charger les notifications internes.', [
                'user_id' => $user->getId(),
                'exception' => $exception,
            ]);

            return ['count' => 0, 'items' => []];
        }
    }

    /**
     * @return list<array{
     *     fingerprint: string,
     *     type: string,
     *     severity: string,
     *     title: string,
     *     message: string,
     *     route: string,
     *     routeParams: array<string, mixed>
     * }>
     */
    private function buildGeneratedNotifications(User $user): array
    {
        $items = [];
        $today = new \DateTimeImmutable('today');
        $recentAssignmentSince = $today->modify('-7 days');
        $upcomingDueLimit = $today->modify('+3 days');

        $recentAssignmentCount = $this->courrierActionRepository->countRecentAssignmentsForUser($user, $recentAssignmentSince);
        if ($recentAssignmentCount > 0) {
            $courrierIds = $this->courrierActionRepository->findRecentAssignmentCourrierIdsForUser($user, $recentAssignmentSince);
            $items[] = [
                'fingerprint' => $this->fingerprint($user, 'assignment', $courrierIds),
                'type' => 'assignment',
                'severity' => 'info',
                'title' => $this->pluralize($recentAssignmentCount, 'Nouvelle imputation', 'Nouvelles imputations'),
                'message' => $this->formatActionCourrierList(
                    $this->courrierActionRepository->findRecentAssignmentsForUser($user, $recentAssignmentSince),
                    'depuis 7 jours'
                ),
                'route' => 'app_courrier_mine',
                'routeParams' => [],
            ];
        }

        $upcomingDueCount = $this->courrierRepository->countUpcomingDueForUser($user, $today, $upcomingDueLimit);
        if ($upcomingDueCount > 0) {
            $courrierIds = $this->courrierRepository->findUpcomingDueIdsForUser($user, $today, $upcomingDueLimit);
            $items[] = [
                'fingerprint' => $this->fingerprint($user, 'deadline', $courrierIds),
                'type' => 'deadline',
                'severity' => 'warning',
                'title' => $this->pluralize($upcomingDueCount, 'Échéance proche', 'Échéances proches'),
                'message' => $this->formatCourrierList(
                    $this->courrierRepository->findUpcomingDueForUser($user, $today, $upcomingDueLimit),
                    'dans les 3 prochains jours'
                ),
                'route' => 'app_courrier_mine',
                'routeParams' => [],
            ];
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $pendingDeletionCount = $this->courrierRepository->countPendingDeletion();

            if ($pendingDeletionCount > 0) {
                $courrierIds = $this->courrierRepository->findPendingDeletionIds();
                $items[] = [
                    'fingerprint' => $this->fingerprint($user, 'deletion', $courrierIds),
                    'type' => 'deletion',
                    'severity' => 'danger',
                    'title' => $this->pluralize($pendingDeletionCount, 'Suppression à valider', 'Suppressions à valider'),
                    'message' => sprintf('%d demande%s en attente de décision administrateur.', $pendingDeletionCount, $pendingDeletionCount > 1 ? 's' : ''),
                    'route' => 'app_courrier_index',
                    'routeParams' => ['pendingDeletion' => 1],
                ];
            }
        }

        return $items;
    }

    /**
     * @param list<array{
     *     fingerprint: string,
     *     type: string,
     *     severity: string,
     *     title: string,
     *     message: string,
     *     route: string,
     *     routeParams: array<string, mixed>
     * }> $generatedNotifications
     */
    private function syncGeneratedNotifications(User $user, array $generatedNotifications): void
    {
        $changed = false;
        $activeFingerprints = [];

        foreach ($generatedNotifications as $generatedNotification) {
            $activeFingerprints[] = $generatedNotification['fingerprint'];
            $notification = $this->notificationRepository->findOneForUserAndFingerprint($user, $generatedNotification['fingerprint']);

            if (!$notification) {
                $notification = (new InAppNotification())
                    ->setRecipient($user)
                    ->setFingerprint($generatedNotification['fingerprint']);
                $this->entityManager->persist($notification);
                $changed = true;
            }

            $changed = $notification->syncGenerated(
                $generatedNotification['type'],
                $generatedNotification['severity'],
                $generatedNotification['title'],
                $generatedNotification['message'],
                $generatedNotification['route'],
                $generatedNotification['routeParams'],
            ) || $changed;
        }

        foreach ($this->notificationRepository->findActiveManagedForUser($user, self::MANAGED_TYPES) as $notification) {
            if (!in_array($notification->getFingerprint(), $activeFingerprints, true)) {
                $changed = $notification->deactivate() || $changed;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array{
     *     id: int|null,
     *     type: string,
     *     severity: string,
     *     read: bool,
     *     title: string,
     *     message: string,
     *     route: string,
     *     routeParams: array<string, mixed>
     * }
     */
    private function toViewItem(InAppNotification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'severity' => $notification->getSeverity(),
            'read' => $notification->isRead(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'route' => $notification->getRoute(),
            'routeParams' => $notification->getRouteParams(),
        ];
    }

    /**
     * @param list<int> $courrierIds
     */
    private function fingerprint(User $user, string $type, array $courrierIds): string
    {
        return hash('sha256', sprintf('%s:%s:%s', $user->getId() ?? 'anonymous', $type, implode(',', $courrierIds)));
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return sprintf('%d %s', $count, 1 === $count ? $singular : $plural);
    }

    /**
     * @param list<CourrierAction> $actions
     */
    private function formatActionCourrierList(array $actions, string $fallback): string
    {
        $courriers = [];

        foreach ($actions as $action) {
            $courrier = $action->getCourrier();

            if ($courrier instanceof Courrier && $courrier->getId()) {
                $courriers[$courrier->getId()] = $courrier;
            }
        }

        return $this->formatCourrierList(array_values($courriers), $fallback);
    }

    /**
     * @param list<Courrier> $courriers
     */
    private function formatCourrierList(array $courriers, string $fallback): string
    {
        if ([] === $courriers) {
            return ucfirst($fallback).'.';
        }

        $labels = [];

        foreach ($courriers as $courrier) {
            $labels[] = sprintf('%s - %s', $courrier->getReference(), $courrier->getSubject());
        }

        return implode(', ', $labels);
    }
}
