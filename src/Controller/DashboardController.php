<?php

namespace App\Controller;

use App\Entity\Courrier;
use App\Repository\CourrierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(CourrierRepository $courrierRepository): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'statusStats' => $courrierRepository->countByStatus(),
            'directionStats' => $courrierRepository->countByDirection(),
            'urgentCourriers' => $courrierRepository->search(['status' => Courrier::STATUS_URGENT]),
            'recentCourriers' => array_slice($courrierRepository->search([]), 0, 8),
        ]);
    }
}
