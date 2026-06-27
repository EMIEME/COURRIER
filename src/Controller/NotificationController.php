<?php

namespace App\Controller;

use App\Entity\InAppNotification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/{id}/lire', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markRead(Request $request, InAppNotification $notification, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessOwned($notification);
        $this->validateCsrf($request, 'notification-read'.$notification->getId());

        if ($notification->markRead()) {
            $entityManager->flush();
        }

        return $this->redirectBack($request);
    }

    #[Route('/{id}/non-lue', name: 'app_notification_mark_unread', methods: ['POST'])]
    public function markUnread(Request $request, InAppNotification $notification, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessOwned($notification);
        $this->validateCsrf($request, 'notification-unread'.$notification->getId());

        if ($notification->markUnread()) {
            $entityManager->flush();
        }

        return $this->redirectBack($request);
    }

    private function denyAccessUnlessOwned(InAppNotification $notification): void
    {
        $user = $this->getUser();
        $recipient = $notification->getRecipient();

        if (!$user instanceof User || !$recipient || $recipient->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function validateCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function redirectBack(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');

        if ($referer) {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
