<?php

namespace App\Controller;

use App\Entity\Courrier;
use App\Repository\CourrierRepository;
use App\Service\CourrierListProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(Request $request, CourrierRepository $courrierRepository, CourrierListProvider $listProvider): Response
    {
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
        $filteredCourriers = $courrierRepository->search($tableFilters);
        $totalCourriers = count($filteredCourriers);
        $totalPages = max(1, (int) ceil($totalCourriers / $perPage));
        $page = min($page, $totalPages);

        return $this->render('dashboard/index.html.twig', [
            'filters' => $request->query->all(),
            'statusStats' => $courrierRepository->countByStatus($periodFilters),
            'directionStats' => $courrierRepository->countByDirection($periodFilters),
            'urgentCourriers' => array_slice($courrierRepository->search([...$periodFilters, 'status' => Courrier::STATUS_URGENT]), 0, 6),
            'filteredCourriers' => array_slice($filteredCourriers, ($page - 1) * $perPage, $perPage),
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
