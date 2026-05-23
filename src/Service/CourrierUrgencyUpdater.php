<?php

namespace App\Service;

use App\Entity\Courrier;
use App\Entity\CourrierAction;
use App\Repository\CourrierRepository;
use Doctrine\ORM\EntityManagerInterface;

class CourrierUrgencyUpdater
{
    public function __construct(
        private readonly CourrierRepository $courrierRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function updateOverdueCourriers(?\DateTimeInterface $today = null): int
    {
        $today = $today ? \DateTimeImmutable::createFromInterface($today) : new \DateTimeImmutable('today');
        $updated = 0;

        foreach ($this->courrierRepository->findOverdueInProgress($today) as $courrier) {
            $this->markAsUrgent($courrier);
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    private function markAsUrgent(Courrier $courrier): void
    {
        $dueDate = $courrier->getResponseDueAt()?->format('d/m/Y') ?? 'non renseignee';

        $courrier->setStatus(Courrier::STATUS_URGENT);
        $courrier->touch();

        $action = (new CourrierAction())
            ->setCourrier($courrier)
            ->setActor(null)
            ->setActionType(CourrierAction::TYPE_STATUS_CHANGED)
            ->setSummary('Statut passe automatiquement en urgent')
            ->setDetails(sprintf("Avant: En cours\nApres: Urgent\nEcheance depassee: %s", $dueDate));

        $this->entityManager->persist($action);
    }
}
