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
use App\Service\CourrierUrgencyUpdater;
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
    #[IsGranted('ROLE_COURRIER_VIEW')]
    public function index(Request $request, CourrierRepository $courrierRepository, UserRepository $userRepository, DestinataireRepository $destinataireRepository, CourrierListProvider $listProvider, CourrierUrgencyUpdater $urgencyUpdater): Response
    {
        $urgencyUpdater->updateOverdueCourriers();

        $filters = $this->buildSearchFilters($request, $userRepository, $destinataireRepository);

        return $this->render('courrier/index.html.twig', [
            'courriers' => $courrierRepository->search($filters),
            'filters' => $request->query->all(),
            'isPendingDeletionView' => !empty($filters['pendingDeletion']),
            'pendingDeletionCount' => $this->isGranted('ROLE_ADMIN') ? $courrierRepository->countPendingDeletion() : 0,
            'selectedDestinataire' => $filters['destinataire'] ?? null,
            'statuses' => $listProvider->statusChoices(),
            'directions' => $listProvider->natureChoices(),
            'statusLabels' => $listProvider->statusLabels(),
            'directionLabels' => $listProvider->natureLabels(),
            'users' => $userRepository->findAssignableUsers(),
        ]);
    }

    #[Route('/export/{format}', name: 'app_courrier_export', requirements: ['format' => 'excel|pdf'], methods: ['GET'])]
    #[IsGranted('ROLE_COURRIER_VIEW')]
    public function export(
        string $format,
        Request $request,
        CourrierRepository $courrierRepository,
        UserRepository $userRepository,
        DestinataireRepository $destinataireRepository,
        CourrierListProvider $listProvider,
        CourrierExportService $exportService,
        CourrierUrgencyUpdater $urgencyUpdater,
    ): Response {
        $urgencyUpdater->updateOverdueCourriers();

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
    #[IsGranted('ROLE_COURRIER_EDIT')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $courrier = new Courrier();
        $form = $this->createForm(CourrierType::class, $courrier, [
            'current_courrier' => $courrier,
            'can_validate' => $this->isGranted('ROLE_COURRIER_VALIDATE'),
        ]);
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
    #[IsGranted('ROLE_COURRIER_VIEW')]
    public function show(Courrier $courrier, UserRepository $userRepository, CourrierListProvider $listProvider, CourrierUrgencyUpdater $urgencyUpdater): Response
    {
        $urgencyUpdater->updateOverdueCourriers();
        $this->denyAccessToPendingDeletion($courrier);

        return $this->render('courrier/show.html.twig', [
            'courrier' => $courrier,
            'users' => $userRepository->findAssignableUsers(),
            'statuses' => $listProvider->statusChoices($courrier->getStatus()),
            'statusLabels' => $listProvider->statusLabels(),
            'directionLabels' => $listProvider->natureLabels(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_courrier_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_COURRIER_EDIT')]
    public function edit(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessToPendingDeletion($courrier, true);

        $before = $this->snapshotCourrier($courrier);
        $form = $this->createForm(CourrierType::class, $courrier, [
            'current_courrier' => $courrier,
            'can_validate' => $this->isGranted('ROLE_COURRIER_VALIDATE'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeContactsByDirection($courrier);
            $uploadedNewAttachment = $this->handleUpload($form->get('attachment')->getData(), $courrier);
            if (!$uploadedNewAttachment) {
                $attachmentMoveWarning = $this->relocateAttachmentAfterMetadataChange($courrier, $before);
                if ($attachmentMoveWarning) {
                    $this->addFlash('error', $attachmentMoveWarning);
                }
            }
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
        $this->denyAccessToPendingDeletion($courrier, true);

        $canEdit = $this->isGranted('ROLE_COURRIER_EDIT');
        $canValidate = $this->isGranted('ROLE_COURRIER_VALIDATE');

        if (!$canEdit && !$canValidate) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('assign'.$courrier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $previousAssignedTo = $courrier->getAssignedToLabel();
        $previousStatus = $courrier->getStatus();
        $previousResponseNotes = $courrier->getResponseNotes();

        if ($canEdit) {
            $courrier->clearAssignedTo();
            foreach ($request->request->all('assignedTo') as $userId) {
                $assignedTo = $userRepository->find((int) $userId);

                if ($assignedTo) {
                    $courrier->addAssignedTo($assignedTo);
                }
            }

            $courrier->setResponseNotes($request->request->get('responseNotes'));
        }

        $requestedStatus = (string) $request->request->get('status', $previousStatus);
        if ($requestedStatus !== $previousStatus) {
            if (!$canValidate) {
                throw $this->createAccessDeniedException();
            }

            $courrier->setStatus($requestedStatus);
        }

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
    #[IsGranted('ROLE_COURRIER_DELETE')]
    public function delete(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($this->isCsrfTokenValid('delete'.$courrier->getId(), (string) $request->request->get('_token'))) {
            if ($courrier->isDeletionPending()) {
                $this->addFlash('success', 'Une demande de suppression est deja en attente pour ce courrier.');

                if ($this->isGranted('ROLE_ADMIN')) {
                    return $this->redirectToRoute('app_courrier_index', ['pendingDeletion' => 1]);
                }

                return $this->redirectToRoute('app_courrier_index');
            }

            $courrier->requestDeletion($this->getCurrentUser());
            $this->recordAction(
                $entityManager,
                $courrier,
                CourrierAction::TYPE_DELETE_REQUESTED,
                'Suppression demandée',
                sprintf('Demandeur: %s', $this->getCurrentUser()?->getFullName() ?? 'Utilisateur inconnu')
            );
            $entityManager->flush();
            $this->addFlash('success', 'La demande de suppression a ete transmise a l administrateur.');
        }

        return $this->redirectToRoute('app_courrier_index');
    }

    #[Route('/{id}/suppression/approuver', name: 'app_courrier_delete_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approveDeletion(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('approve-delete'.$courrier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$courrier->isDeletionPending()) {
            $this->addFlash('error', 'Aucune demande de suppression n est en attente pour ce courrier.');

            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        $entityManager->remove($courrier);
        $entityManager->flush();
        $this->addFlash('success', 'La suppression du courrier a ete approuvee et executee.');

        return $this->redirectToRoute('app_courrier_index', ['pendingDeletion' => 1]);
    }

    #[Route('/{id}/suppression/refuser', name: 'app_courrier_delete_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectDeletion(Request $request, Courrier $courrier, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('reject-delete'.$courrier->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$courrier->isDeletionPending()) {
            $this->addFlash('error', 'Aucune demande de suppression n est en attente pour ce courrier.');

            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        $requestedBy = $courrier->getDeletionRequestedBy()?->getFullName() ?? 'Utilisateur inconnu';
        $requestedAt = $courrier->getDeletionRequestedAt()?->format('d/m/Y H:i') ?? 'date inconnue';

        $courrier->cancelDeletionRequest();
        $this->recordAction(
            $entityManager,
            $courrier,
            CourrierAction::TYPE_DELETE_REJECTED,
            'Suppression refusée',
            sprintf("Demandeur: %s\nDate de demande: %s", $requestedBy, $requestedAt)
        );
        $entityManager->flush();
        $this->addFlash('success', 'La demande de suppression a ete refusee. Le courrier est de nouveau accessible.');

        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    private function handleUpload(?UploadedFile $file, Courrier $courrier): bool
    {
        if (!$file instanceof UploadedFile) {
            return false;
        }

        $attachmentPath = $this->buildAttachmentPath($file, $courrier);
        $targetDirectory = $this->uploadsBaseDirectory().DIRECTORY_SEPARATOR.dirname($attachmentPath);

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $file->move($targetDirectory, basename($attachmentPath));
        $courrier->setAttachmentFilename($attachmentPath);

        return true;
    }

    private function buildAttachmentPath(UploadedFile $file, Courrier $courrier): string
    {
        $hash = bin2hex(random_bytes(6));
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';

        return $this->buildAttachmentPathWithHash($courrier, $hash, $extension);
    }

    private function buildAttachmentPathWithHash(Courrier $courrier, string $hash, string $extension): string
    {
        $slugger = new AsciiSlugger();
        $year = $courrier->getMailDate()?->format('Y') ?? (new \DateTimeImmutable())->format('Y');
        $natureDirectory = $this->attachmentNatureDirectory($courrier);
        $safeReference = strtolower($slugger->slug((string) $courrier->getReference())->toString());
        $safeReference = trim($safeReference, '-');
        $safeReference = $safeReference ? mb_substr($safeReference, 0, 90) : 'courrier';
        $extension = strtolower($slugger->slug(ltrim($extension, '.'))->toString());

        return sprintf('%s/%s/%s-%s.%s', $year, $natureDirectory, $safeReference, $hash, $extension ?: 'bin');
    }

    private function attachmentNatureDirectory(Courrier $courrier): string
    {
        return match ($courrier->getDirection()) {
            Courrier::DIRECTION_ENTRANT => 'arrive',
            Courrier::DIRECTION_SORTANT => 'depart',
            Courrier::DIRECTION_INTERNE => 'note-interne',
            default => 'autre',
        };
    }

    /**
     * @param array<string, string> $before
     */
    private function relocateAttachmentAfterMetadataChange(Courrier $courrier, array $before): ?string
    {
        $oldAttachment = trim((string) ($before['Fichier'] ?? ''));
        if ('' === $oldAttachment || !$this->attachmentMetadataChanged($courrier, $before)) {
            return null;
        }

        $currentAttachment = $courrier->getAttachmentFilename();
        if (!$currentAttachment || $currentAttachment !== $oldAttachment) {
            return null;
        }

        $attachmentIdentity = $this->extractAttachmentIdentity($oldAttachment);
        if (!$attachmentIdentity) {
            return 'La piece jointe est restee dans son emplacement initial car son hash n a pas pu etre reconnu.';
        }

        $targetAttachment = $this->buildAttachmentPathWithHash($courrier, $attachmentIdentity['hash'], $attachmentIdentity['extension']);
        if ($targetAttachment === $oldAttachment) {
            return null;
        }

        $uploadsDirectory = $this->uploadsBaseDirectory();
        $oldPath = $uploadsDirectory.DIRECTORY_SEPARATOR.$oldAttachment;
        $targetPath = $uploadsDirectory.DIRECTORY_SEPARATOR.$targetAttachment;

        if (!is_file($oldPath)) {
            return 'La piece jointe est restee dans son emplacement initial car le fichier source est introuvable.';
        }

        if (file_exists($targetPath)) {
            return 'La piece jointe est restee dans son emplacement initial car un fichier existe deja dans le nouvel emplacement.';
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if (!@rename($oldPath, $targetPath)) {
            return 'La piece jointe est restee dans son emplacement initial car le deplacement du fichier a echoue.';
        }

        $courrier->setAttachmentFilename($targetAttachment);

        return null;
    }

    /**
     * @param array<string, string> $before
     */
    private function attachmentMetadataChanged(Courrier $courrier, array $before): bool
    {
        return ($before['Référence'] ?? '') !== (string) $courrier->getReference()
            || ($before['Date'] ?? '') !== ($courrier->getMailDate()?->format('d/m/Y') ?? '')
            || ($before['Nature'] ?? '') !== $courrier->getDirectionLabel();
    }

    /**
     * @return array{hash: string, extension: string}|null
     */
    private function extractAttachmentIdentity(string $attachmentPath): ?array
    {
        if (!preg_match('/-([a-f0-9]{12})\.([a-z0-9]+)$/i', basename($attachmentPath), $matches)) {
            return null;
        }

        return [
            'hash' => strtolower($matches[1]),
            'extension' => strtolower($matches[2]),
        ];
    }

    private function uploadsBaseDirectory(): string
    {
        return rtrim((string) $this->getParameter('uploads_directory'), DIRECTORY_SEPARATOR);
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
            'pendingDeletion' => $this->isGranted('ROLE_ADMIN') && $request->query->getBoolean('pendingDeletion'),
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

    private function denyAccessToPendingDeletion(Courrier $courrier, bool $denyForAdmin = false): void
    {
        if (!$courrier->isDeletionPending()) {
            return;
        }

        if ($denyForAdmin || !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }
    }
}
