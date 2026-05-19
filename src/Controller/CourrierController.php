<?php

namespace App\Controller;

use App\Entity\Courrier;
use App\Entity\CourrierAction;
use App\Entity\User;
use App\Form\CourrierType;
use App\Repository\CourrierRepository;
use App\Repository\DestinataireRepository;
use App\Repository\UserRepository;
use App\Service\CourrierExportService;
use App\Service\CourrierListProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
    public function index(Request $request, CourrierRepository $courrierRepository, UserRepository $userRepository, DestinataireRepository $destinataireRepository, CourrierListProvider $listProvider): Response
    {
        $filters = $this->buildSearchFilters($request, $userRepository, $destinataireRepository);

        return $this->render('courrier/index.html.twig', [
            'courriers' => $courrierRepository->search($filters),
            'filters' => $request->query->all(),
            'selectedDestinataire' => $filters['destinataire'] ?? null,
            'statuses' => $listProvider->statusChoices(),
            'directions' => $listProvider->natureChoices(),
            'statusLabels' => $listProvider->statusLabels(),
            'directionLabels' => $listProvider->natureLabels(),
            'users' => $userRepository->findAssignableUsers(),
        ]);
    }

    #[Route('/export/{format}', name: 'app_courrier_export', requirements: ['format' => 'excel|pdf'], methods: ['GET'])]
    public function export(
        string $format,
        Request $request,
        CourrierRepository $courrierRepository,
        UserRepository $userRepository,
        DestinataireRepository $destinataireRepository,
        CourrierListProvider $listProvider,
        CourrierExportService $exportService,
    ): Response {
        $courriers = $courrierRepository->search($this->buildSearchFilters($request, $userRepository, $destinataireRepository));
        $directionLabels = $listProvider->natureLabels();
        $statusLabels = $listProvider->statusLabels();
        $timestamp = (new \DateTimeImmutable())->format('Ymd-His');

        if ('pdf' === $format) {
            $content = $exportService->buildPdf($courriers, $directionLabels, $statusLabels);
            $filename = sprintf('registre-courriers-%s.pdf', $timestamp);
            $contentType = 'application/pdf';
        } else {
            $content = $exportService->buildExcel($courriers, $directionLabels, $statusLabels);
            $filename = sprintf('registre-courriers-%s.xls', $timestamp);
            $contentType = 'application/vnd.ms-excel; charset=UTF-8';
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    #[Route('/nouveau', name: 'app_courrier_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SECRETARIAT')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $courrier = new Courrier();
        $form = $this->createForm(CourrierType::class, $courrier, ['current_courrier' => $courrier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $courrier->setCreatedBy($this->getUser());
            $this->normalizeContactsByDirection($courrier);
            $this->handleUpload($form->get('attachment')->getData(), $courrier);

            $entityManager->persist($courrier);
            $this->recordAction($entityManager, $courrier, CourrierAction::TYPE_CREATED, 'Courrier créé', sprintf('Référence: %s', $courrier->getReference()));
            if (!$courrier->getAssignedTo()->isEmpty()) {
                $this->recordAction($entityManager, $courrier, CourrierAction::TYPE_ASSIGNED, 'Imputation initiale', $courrier->getAssignedToLabel());
            }
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
    public function show(Courrier $courrier, UserRepository $userRepository, CourrierListProvider $listProvider): Response
    {
        return $this->render('courrier/show.html.twig', [
            'courrier' => $courrier,
            'users' => $userRepository->findAssignableUsers(),
            'statuses' => $listProvider->statusChoices($courrier->getStatus()),
            'statusLabels' => $listProvider->statusLabels(),
            'directionLabels' => $listProvider->natureLabels(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_courrier_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SECRETARIAT')]
    public function edit(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): Response
    {
        $before = $this->snapshotCourrier($courrier);
        $form = $this->createForm(CourrierType::class, $courrier, ['current_courrier' => $courrier]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeContactsByDirection($courrier);
            $this->handleUpload($form->get('attachment')->getData(), $courrier);
            $courrier->touch();
            $this->recordEditActions($entityManager, $courrier, $before);
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

        $previousAssignedTo = $courrier->getAssignedToLabel();
        $previousStatus = $courrier->getStatus();
        $previousResponseNotes = $courrier->getResponseNotes();

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

        if ($previousAssignedTo !== $courrier->getAssignedToLabel()) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_ASSIGNED,
                'Imputation mise à jour',
                sprintf("Avant: %s\nAprès: %s", $previousAssignedTo, $courrier->getAssignedToLabel())
            );
        }

        if ($previousStatus !== $courrier->getStatus()) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_STATUS_CHANGED,
                'Statut modifié',
                sprintf("Avant: %s\nAprès: %s", $this->labelForStatus($previousStatus), $courrier->getStatusLabel())
            );
        }

        if ($previousResponseNotes !== $courrier->getResponseNotes() && $courrier->getResponseNotes()) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_RESPONSE_ADDED,
                $previousResponseNotes ? 'Réponse mise à jour' : 'Réponse ajoutée',
                $courrier->getResponseNotes()
            );
        }

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

        $newFilename = $this->buildAttachmentFilename($file, $courrier);

        $file->move($this->getParameter('uploads_directory'), $newFilename);
        $courrier->setAttachmentFilename($newFilename);
    }

    private function buildAttachmentFilename(UploadedFile $file, Courrier $courrier): string
    {
        $slugger = new AsciiSlugger();
        $interlocuteur = $courrier->getInterlocuteurLabel();
        $safeInterlocuteur = strtolower($slugger->slug($interlocuteur)->toString());
        $safeInterlocuteur = trim($safeInterlocuteur, '-');
        $safeInterlocuteur = $safeInterlocuteur ? mb_substr($safeInterlocuteur, 0, 80) : 'piece-jointe';
        $hash = bin2hex(random_bytes(6));
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $extension = strtolower($slugger->slug($extension)->toString());

        return sprintf('%s-%s.%s', $safeInterlocuteur, $hash, $extension ?: 'bin');
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

    /**
     * @return array<string, mixed>
     */
    private function buildSearchFilters(Request $request, UserRepository $userRepository, DestinataireRepository $destinataireRepository): array
    {
        $assignedTo = null;
        if ($request->query->get('assignedTo')) {
            $assignedTo = $userRepository->find((int) $request->query->get('assignedTo'));
        }

        $destinataire = null;
        if ($request->query->get('destinataire')) {
            $destinataire = $destinataireRepository->find((int) $request->query->get('destinataire'));
        }

        return [
            'query' => $request->query->get('q'),
            'sender' => $request->query->get('sender'),
            'status' => $request->query->get('status'),
            'direction' => $request->query->get('direction'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
            'assignedTo' => $assignedTo,
            'destinataire' => $destinataire,
        ];
    }

    private function recordAction(EntityManagerInterface $entityManager, Courrier $courrier, string $type, string $summary, ?string $details = null): void
    {
        $action = (new CourrierAction())
            ->setCourrier($courrier)
            ->setActor($this->getCurrentUser())
            ->setActionType($type)
            ->setSummary($summary)
            ->setDetails($details);

        $entityManager->persist($action);
    }

    /**
     * @return array<string, string>
     */
    private function snapshotCourrier(Courrier $courrier): array
    {
        return [
            'Référence' => (string) $courrier->getReference(),
            'Date' => $courrier->getMailDate()?->format('d/m/Y') ?? '',
            'Nature' => $courrier->getDirectionLabel(),
            'Statut' => $courrier->getStatus(),
            'Émetteur' => $courrier->getSenderLabel(),
            'Destinataire' => $courrier->getRecipientLabel(),
            'Réponse au courrier' => $courrier->getReplyTo()?->getReference() ?? '',
            'Objet' => (string) $courrier->getSubject(),
            'Contenu' => (string) $courrier->getContent(),
            'Localisation' => (string) $courrier->getLocalisation(),
            'Fichier' => (string) $courrier->getAttachmentFilename(),
            'Imputation' => $courrier->getAssignedToLabel(),
            'Échéance' => $courrier->getResponseDueAt()?->format('d/m/Y') ?? '',
            'Réponse' => (string) $courrier->getResponseNotes(),
        ];
    }

    /**
     * @param array<string, string> $before
     */
    private function recordEditActions(EntityManagerInterface $entityManager, Courrier $courrier, array $before): void
    {
        $after = $this->snapshotCourrier($courrier);
        $changedLines = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? '') !== $value) {
                $changedLines[] = sprintf('%s: %s → %s', $field, $this->formatHistoryValue($before[$field] ?? ''), $this->formatHistoryValue($value));
            }
        }

        if ($changedLines) {
            $this->recordAction($entityManager, $courrier, CourrierAction::TYPE_UPDATED, 'Courrier modifié', implode("\n", $changedLines));
        }

        if (($before['Imputation'] ?? '') !== $after['Imputation']) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_ASSIGNED,
                'Imputation modifiée',
                sprintf("Avant: %s\nAprès: %s", $before['Imputation'] ?? '', $after['Imputation'])
            );
        }

        if (($before['Statut'] ?? '') !== $after['Statut']) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_STATUS_CHANGED,
                'Statut modifié',
                sprintf("Avant: %s\nAprès: %s", $this->labelForStatus($before['Statut'] ?? ''), $courrier->getStatusLabel())
            );
        }

        if (($before['Réponse'] ?? '') !== $after['Réponse'] && $after['Réponse']) {
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_RESPONSE_ADDED,
                ($before['Réponse'] ?? '') ? 'Réponse mise à jour' : 'Réponse ajoutée',
                $after['Réponse']
            );
        }
    }

    private function labelForStatus(string $status): string
    {
        return array_flip(Courrier::STATUSES)[$status] ?? $status;
    }

    private function formatHistoryValue(?string $value): string
    {
        $value = trim((string) $value);

        if ('' === $value) {
            return 'vide';
        }

        return mb_strlen($value) > 90 ? mb_substr($value, 0, 87).'...' : $value;
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
