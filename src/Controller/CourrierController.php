<?php

namespace App\Controller;

use App\Entity\Courrier;
use App\Form\CourrierType;
use App\Repository\CourrierRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/courriers')]
class CourrierController extends AbstractController
{
    #[Route('', name: 'app_courrier_index', methods: ['GET'])]
    public function index(Request $request, CourrierRepository $courrierRepository, UserRepository $userRepository): Response
    {
        $assignedTo = null;
        if ($request->query->get('assignedTo')) {
            $assignedTo = $userRepository->find((int) $request->query->get('assignedTo'));
        }

        $filters = [
            'query' => $request->query->get('q'),
            'sender' => $request->query->get('sender'),
            'status' => $request->query->get('status'),
            'direction' => $request->query->get('direction'),
            'type' => $request->query->get('type'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
            'assignedTo' => $assignedTo,
        ];

        return $this->render('courrier/index.html.twig', [
            'courriers' => $courrierRepository->search($filters),
            'filters' => $request->query->all(),
            'statuses' => Courrier::STATUSES,
            'directions' => Courrier::DIRECTIONS,
            'types' => Courrier::TYPES,
            'users' => $userRepository->findAssignableUsers(),
        ]);
    }

    #[Route('/nouveau', name: 'app_courrier_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SECRETARIAT')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $courrier = new Courrier();
        $form = $this->createForm(CourrierType::class, $courrier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUpload($form->get('attachment')->getData(), $courrier);
            $courrier->setCreatedBy($this->getUser());
            $this->normalizeContactsByDirection($courrier);

            $entityManager->persist($courrier);
            $entityManager->flush();

            $this->addFlash('success', 'Le courrier a ete enregistre.');

            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        return $this->render('courrier/form.html.twig', [
            'courrier' => $courrier,
            'form' => $form,
            'title' => 'Nouveau courrier',
            'button_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}', name: 'app_courrier_show', methods: ['GET'])]
    public function show(Courrier $courrier, UserRepository $userRepository): Response
    {
        return $this->render('courrier/show.html.twig', [
            'courrier' => $courrier,
            'users' => $userRepository->findAssignableUsers(),
            'statuses' => Courrier::STATUSES,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_courrier_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SECRETARIAT')]
    public function edit(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourrierType::class, $courrier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleUpload($form->get('attachment')->getData(), $courrier);
            $this->normalizeContactsByDirection($courrier);
            $courrier->touch();
            $entityManager->flush();

            $this->addFlash('success', 'Le courrier a ete mis a jour.');

            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        return $this->render('courrier/form.html.twig', [
            'courrier' => $courrier,
            'form' => $form,
            'title' => 'Modifier le courrier',
            'button_label' => 'Mettre a jour',
        ]);
    }

    #[Route('/{id}/imputer', name: 'app_courrier_assign', methods: ['POST'])]
    public function assign(Request $request, Courrier $courrier, EntityManagerInterface $entityManager, UserRepository $userRepository): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('assign'.$courrier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $courrier->clearAssignedTo();
        foreach ($request->request->all('assignedTo') as $userId) {
            $assignedTo = $userRepository->find((int) $userId);

            if ($assignedTo) {
                $courrier->addAssignedTo($assignedTo);
            }
        }

        if ($request->request->get('status')) {
            $courrier->setStatus((string) $request->request->get('status'));
        }

        $courrier->setResponseNotes($request->request->get('responseNotes'));
        $courrier->touch();
        $entityManager->flush();

        $this->addFlash('success', 'Le suivi du courrier a ete mis a jour.');

        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'app_courrier_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($this->isCsrfTokenValid('delete'.$courrier->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($courrier);
            $entityManager->flush();
            $this->addFlash('success', 'Le courrier a ete supprime.');
        }

        return $this->redirectToRoute('app_courrier_index');
    }

    private function handleUpload(?UploadedFile $file, Courrier $courrier): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $slugger = new AsciiSlugger();
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = strtolower($slugger->slug($originalFilename)->toString());
        $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$file->guessExtension();

        $file->move($this->getParameter('uploads_directory'), $newFilename);
        $courrier->setAttachmentFilename($newFilename);
    }

    private function normalizeContactsByDirection(Courrier $courrier): void
    {
        if (Courrier::DIRECTION_ENTRANT === $courrier->getDirection()) {
            $courrier->syncSenderSnapshot();
            $courrier->clearDestinataires();

            return;
        }

        $courrier->clearSender();
        $courrier->syncRecipientSnapshot();
    }
}
