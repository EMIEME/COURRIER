<?php

namespace App\Service;

use App\Entity\Courrier;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CourrierAssignmentNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $replyToAddress,
        private readonly string $uploadsDirectory,
    ) {
    }

    /**
     * @param iterable<User> $users
     *
     * @return array{sent: int, failed: int}
     */
    public function notifyInProgressAssignment(Courrier $courrier, iterable $users): array
    {
        if (Courrier::STATUS_EN_COURS !== $courrier->getStatus()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;
        $recipients = $this->uniqueRecipients($users);

        foreach ($recipients as $recipient) {
            try {
                $this->mailer->send($this->buildEmail($courrier, $recipient));
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $this->logger->error('Impossible d\'envoyer la notification d\'imputation du courrier.', [
                    'courrier_id' => $courrier->getId(),
                    'courrier_reference' => $courrier->getReference(),
                    'recipient' => $recipient->getEmail(),
                    'exception' => $exception,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param iterable<User> $users
     *
     * @return array{sent: int, failed: int}
     */
    public function notifyUrgentStatus(Courrier $courrier, iterable $users): array
    {
        if (Courrier::STATUS_URGENT !== $courrier->getStatus()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;
        $recipients = $this->uniqueRecipients($users);

        foreach ($recipients as $recipient) {
            try {
                $this->mailer->send($this->buildUrgentEmail($courrier, $recipient));
                ++$sent;
            } catch (\Throwable $exception) {
                ++$failed;
                $this->logger->error('Impossible d\'envoyer la notification de passage en urgent du courrier.', [
                    'courrier_id' => $courrier->getId(),
                    'courrier_reference' => $courrier->getReference(),
                    'recipient' => $recipient->getEmail(),
                    'exception' => $exception,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param iterable<User> $users
     *
     * @return list<User>
     */
    private function uniqueRecipients(iterable $users): array
    {
        $recipients = [];

        foreach ($users as $user) {
            $email = strtolower(trim((string) $user->getEmail()));

            if ('' === $email) {
                continue;
            }

            $recipients[$email] = $user;
        }

        return array_values($recipients);
    }

    private function buildEmail(Courrier $courrier, User $recipient): Email
    {
        $url = $this->urlGenerator->generate('app_courrier_show', ['id' => $courrier->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address((string) $recipient->getEmail(), $recipient->getFullName() ?: (string) $recipient->getEmail()))
            ->subject(sprintf('Courrier imputé en cours - %s', $courrier->getReference()))
            ->text($this->buildTextBody($courrier, $recipient, $url))
            ->html($this->buildHtmlBody($courrier, $recipient, $url));

        if ('' !== trim($this->replyToAddress)) {
            $email->replyTo(new Address($this->replyToAddress, $this->fromName));
        }

        $this->attachCourrierFile($email, $courrier);

        return $email;
    }

    private function buildUrgentEmail(Courrier $courrier, User $recipient): Email
    {
        $url = $this->urlGenerator->generate('app_courrier_show', ['id' => $courrier->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address((string) $recipient->getEmail(), $recipient->getFullName() ?: (string) $recipient->getEmail()))
            ->subject(sprintf('Courrier urgent - %s', $courrier->getReference()))
            ->text($this->buildUrgentTextBody($courrier, $recipient, $url))
            ->html($this->buildUrgentHtmlBody($courrier, $recipient, $url));

        if ('' !== trim($this->replyToAddress)) {
            $email->replyTo(new Address($this->replyToAddress, $this->fromName));
        }

        return $email;
    }

    private function attachCourrierFile(Email $email, Courrier $courrier): void
    {
        $attachmentPath = $this->attachmentFullPath($courrier->getAttachmentFilename());
        if (null === $attachmentPath) {
            return;
        }

        $contentType = mime_content_type($attachmentPath) ?: 'application/octet-stream';
        $email->attachFromPath($attachmentPath, basename($attachmentPath), $contentType);
    }

    private function attachmentFullPath(?string $attachmentPath): ?string
    {
        $attachmentPath = trim((string) $attachmentPath);

        if ('' === $attachmentPath || str_starts_with($attachmentPath, DIRECTORY_SEPARATOR) || str_contains($attachmentPath, '..')) {
            return null;
        }

        $uploadsDirectory = rtrim($this->uploadsDirectory, DIRECTORY_SEPARATOR);
        $uploadsRealPath = realpath($uploadsDirectory);
        $attachmentRealPath = realpath($uploadsDirectory.DIRECTORY_SEPARATOR.$attachmentPath);

        if (!$uploadsRealPath || !$attachmentRealPath || !str_starts_with($attachmentRealPath, $uploadsRealPath.DIRECTORY_SEPARATOR)) {
            $this->logger->warning('La pièce jointe du courrier n\'a pas été ajoutée à la notification email.', [
                'attachment' => $attachmentPath,
            ]);

            return null;
        }

        return is_file($attachmentRealPath) ? $attachmentRealPath : null;
    }

    private function buildTextBody(Courrier $courrier, User $recipient, string $url): string
    {
        $lines = [
            sprintf('Bonjour %s,', $recipient->getFullName() ?: $recipient->getEmail()),
            '',
            'Un courrier nécessitant une réponse vous a été imputé.',
            '',
            sprintf('Référence: %s', $courrier->getReference()),
            sprintf('Objet: %s', $courrier->getSubject()),
            sprintf('Nature: %s', $courrier->getDirectionLabel()),
            sprintf('Date du courrier: %s', $this->formatDate($courrier->getMailDate())),
            sprintf('Interlocuteur: %s', $courrier->getInterlocuteurLabel()),
            sprintf('Échéance de réponse: %s', $this->formatDate($courrier->getResponseDueAt())),
            sprintf('Suivi / réponse: %s', $this->formatResponseNotes($courrier)),
            '',
            'Voir le service Courrier pour toute information complémentaire.',
        ];

        return implode("\n", $lines);
    }

    private function buildHtmlBody(Courrier $courrier, User $recipient, string $url): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<p>Bonjour %s,</p><p>Un courrier nécessitant une réponse vous a été imputé.</p><ul><li><strong>Référence:</strong> %s</li><li><strong>Objet:</strong> %s</li><li><strong>Nature:</strong> %s</li><li><strong>Date du courrier:</strong> %s</li><li><strong>Interlocuteur:</strong> %s</li><li><strong>Échéance de réponse:</strong> %s</li><li><strong>Suivi / réponse:</strong> %s</li></ul><p>Voir le service Courrier pour toute information complémentaire.</p>',
            $escape($recipient->getFullName() ?: $recipient->getEmail()),
            $escape($courrier->getReference()),
            $escape($courrier->getSubject()),
            $escape($courrier->getDirectionLabel()),
            $escape($this->formatDate($courrier->getMailDate())),
            $escape($courrier->getInterlocuteurLabel()),
            $escape($this->formatDate($courrier->getResponseDueAt())),
            nl2br($escape($this->formatResponseNotes($courrier))),
        );
    }

    private function buildUrgentTextBody(Courrier $courrier, User $recipient, string $url): string
    {
        $lines = [
            sprintf('Bonjour %s,', $recipient->getFullName() ?: $recipient->getEmail()),
            '',
            'Un courrier qui vous est imputé est passé automatiquement en urgent, car son échéance de réponse est dépassée.',
            '',
            sprintf('Référence: %s', $courrier->getReference()),
            sprintf('Objet: %s', $courrier->getSubject()),
            sprintf('Nature: %s', $courrier->getDirectionLabel()),
            sprintf('Date du courrier: %s', $this->formatDate($courrier->getMailDate())),
            sprintf('Interlocuteur: %s', $courrier->getInterlocuteurLabel()),
            sprintf('Échéance de réponse: %s', $this->formatDate($courrier->getResponseDueAt())),
            sprintf('Suivi / réponse: %s', $this->formatResponseNotes($courrier)),
            sprintf('Lien: %s', $url),
            '',
            'Voir le service Courrier pour toute information complémentaire.',
        ];

        return implode("\n", $lines);
    }

    private function buildUrgentHtmlBody(Courrier $courrier, User $recipient, string $url): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<p>Bonjour %s,</p><p>Un courrier qui vous est imputé est passé automatiquement en urgent, car son échéance de réponse est dépassée.</p><ul><li><strong>Référence:</strong> %s</li><li><strong>Objet:</strong> %s</li><li><strong>Nature:</strong> %s</li><li><strong>Date du courrier:</strong> %s</li><li><strong>Interlocuteur:</strong> %s</li><li><strong>Échéance de réponse:</strong> %s</li><li><strong>Suivi / réponse:</strong> %s</li></ul><p><a href="%s">Ouvrir le courrier</a></p><p>Voir le service Courrier pour toute information complémentaire.</p>',
            $escape($recipient->getFullName() ?: $recipient->getEmail()),
            $escape($courrier->getReference()),
            $escape($courrier->getSubject()),
            $escape($courrier->getDirectionLabel()),
            $escape($this->formatDate($courrier->getMailDate())),
            $escape($courrier->getInterlocuteurLabel()),
            $escape($this->formatDate($courrier->getResponseDueAt())),
            nl2br($escape($this->formatResponseNotes($courrier))),
            $escape($url),
        );
    }

    private function formatDate(?\DateTimeInterface $date): string
    {
        return $date?->format('d/m/Y') ?? 'Non renseignée';
    }

    private function formatResponseNotes(Courrier $courrier): string
    {
        $responseNotes = trim((string) $courrier->getResponseNotes());

        return '' !== $responseNotes ? $responseNotes : 'Non renseigné';
    }
}
