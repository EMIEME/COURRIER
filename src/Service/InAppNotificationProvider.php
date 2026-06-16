<?php

namespace App\Service;

use App\Entity\Courrier;
use App\Entity\CourrierAction;
use App\Entity\User;
use App\Repository\CourrierActionRepository;
use App\Repository\CourrierRepository;
use Symfony\Bundle\SecurityBundle\Security;

class InAppNotificationProvider
{
    public function __construct(
        private readonly CourrierRepository $courrierRepository,
        private readonly CourrierActionRepository $courrierActionRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return array{
     *     count: int,
     *     items: list<array{
     *         type: string,
     *         severity: string,
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

        $count = 0;
        $items = [];
        $today = new \DateTimeImmutable('today');
        $recentAssignmentSince = $today->modify('-7 days');
        $upcomingDueLimit = $today->modify('+3 days');

        $recentAssignmentCount = $this->courrierActionRepository->countRecentAssignmentsForUser($user, $recentAssignmentSince);
        if ($recentAssignmentCount > 0) {
            $count += $recentAssignmentCount;
            $items[] = [
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
            $count += $upcomingDueCount;
            $items[] = [
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
                $count += $pendingDeletionCount;
                $items[] = [
                    'type' => 'deletion',
                    'severity' => 'danger',
                    'title' => $this->pluralize($pendingDeletionCount, 'Suppression à valider', 'Suppressions à valider'),
                    'message' => sprintf('%d demande%s en attente de décision administrateur.', $pendingDeletionCount, $pendingDeletionCount > 1 ? 's' : ''),
                    'route' => 'app_courrier_index',
                    'routeParams' => ['pendingDeletion' => 1],
                ];
            }
        }

        return [
            'count' => $count,
            'items' => $items,
        ];
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
