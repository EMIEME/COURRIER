<?php

namespace App\Controller;

use App\Entity\Destinataire;
use App\Form\DestinataireType;
use App\Repository\CourrierRepository;
use App\Repository\DestinataireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/destinataires')]
#[IsGranted('ROLE_SECRETARIAT')]
class DestinataireController extends AbstractController
{
    #[Route('', name: 'app_destinataire_index', methods: ['GET'])]
    public function index(Request $request, DestinataireRepository $destinataireRepository, CourrierRepository $courrierRepository): Response
    {
        $limit = 10;
        $query = trim((string) $request->query->get('q', ''));
        $total = $destinataireRepository->countSearch($query);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);
        $destinataires = $destinataireRepository->searchPaginated($query, $page, $limit);
        $courrierCounts = [];

        foreach ($destinataires as $destinataire) {
            $courrierCounts[$destinataire->getId()] = $courrierRepository->countLinkedToDestinataire($destinataire);
        }

        return $this->render('destinataire/index.html.twig', [
            'destinataires' => $destinataires,
            'courrierCounts' => $courrierCounts,
            'filters' => [
                'q' => $query,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/nouveau', name: 'app_destinataire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $destinataire = new Destinataire();
        $form = $this->createForm(DestinataireType::class, $destinataire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($destinataire);
            $entityManager->flush();

            $this->addFlash('success', 'Destinataire ajoute.');

            return $this->redirectToRoute('app_destinataire_index');
        }

        return $this->render('destinataire/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau destinataire',
            'button_label' => 'Ajouter',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_destinataire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Destinataire $destinataire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DestinataireType::class, $destinataire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Destinataire mis a jour.');

            return $this->redirectToRoute('app_destinataire_index');
        }

        return $this->render('destinataire/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier un destinataire',
            'button_label' => 'Mettre a jour',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_destinataire_delete', methods: ['POST'])]
    public function delete(Request $request, Destinataire $destinataire, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($this->isCsrfTokenValid('delete-destinataire'.$destinataire->getId(), (string) $request->request->get('_token'))) {
            foreach ($destinataire->getCourriers() as $courrier) {
                $courrier->removeDestinataire($destinataire);
            }

            $entityManager->remove($destinataire);
            $entityManager->flush();

            $this->addFlash('success', 'Destinataire supprime. Les courriers existants sont conserves.');
        }

        return $this->redirectToRoute('app_destinataire_index');
    }
}
