<?php

namespace App\Controller;

use App\Entity\Courrier;
use App\Repository\CourrierRepository;
use App\Service\CourrierListProvider;
use App\Service\CourrierUrgencyUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    #[IsGranted('ROLE_COURRIER_VIEW')]
    public function index(Request $request, CourrierRepository $courrierRepository, CourrierListProvider $listProvider, CourrierUrgencyUpdater $urgencyUpdater): Response
    {
        $urgencyUpdater->updateOverdueCourriers();

        $perPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $periodFilters = [
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
        ];
        $tableFilters = [
            ...$periodFilters,
            'status' => $request->query->get('status'),
            'direction' => $request->query->get('direction'),
        ];
        $totalCourriers = $courrierRepository->countSearch($tableFilters);
        $totalPages = max(1, (int) ceil($totalCourriers / $perPage));
        $page = min($page, $totalPages);
        $filteredCourriers = $courrierRepository->searchPaginated($tableFilters, $page, $perPage);

        return $this->render('dashboard/index.html.twig', [
            'filters' => $request->query->all(),
            'statusStats' => $courrierRepository->countByStatus($periodFilters),
            'directionStats' => $courrierRepository->countByDirection($periodFilters),
            'urgentCourriers' => $courrierRepository->searchPaginated([...$periodFilters, 'status' => Courrier::STATUS_URGENT, 'prioritizeUrgent' => true], 1, 6),
            'filteredCourriers' => $filteredCourriers,
            'statusLabels' => $listProvider->statusLabels(),
            'directionLabels' => $listProvider->natureLabels(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalCourriers,
                'totalPages' => $totalPages,
            ],
        ]);
    }
}
