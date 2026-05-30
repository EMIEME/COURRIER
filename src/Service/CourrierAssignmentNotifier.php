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
                $this->logger->error('Impossible d envoyer la notification d imputation du courrier.', [
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

        return $email;
    }

    private function buildTextBody(Courrier $courrier, User $recipient, string $url): string
    {
        $lines = [
            sprintf('Bonjour %s,', $recipient->getFullName() ?: $recipient->getEmail()),
            '',
            'Un courrier vous a été imputé.',
            '',
            sprintf('Référence: %s', $courrier->getReference()),
            sprintf('Objet: %s', $courrier->getSubject()),
            sprintf('Nature: %s', $courrier->getDirectionLabel()),
            sprintf('Date du courrier: %s', $this->formatDate($courrier->getMailDate())),
            sprintf('Interlocuteur: %s', $courrier->getInterlocuteurLabel()),
            sprintf('Échéance de réponse: %s', $this->formatDate($courrier->getResponseDueAt())),
            '',
            sprintf('Consulter le courrier auprès de votre gestionnaire de courriers:'),
        ];

        return implode("\n", $lines);
    }

    private function buildHtmlBody(Courrier $courrier, User $recipient, string $url): string
    {
        $escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<p>Bonjour %s,</p><p>Un courrier vous a été imputé avec le statut <strong>En cours</strong>.</p><ul><li><strong>Référence:</strong> %s</li><li><strong>Objet:</strong> %s</li><li><strong>Nature:</strong> %s</li><li><strong>Date du courrier:</strong> %s</li><li><strong>Interlocuteur:</strong> %s</li><li><strong>Échéance de réponse:</strong> %s</li></ul><p><a href="%s">Consulter le courrier</a></p>',
            $escape($recipient->getFullName() ?: $recipient->getEmail()),
            $escape($courrier->getReference()),
            $escape($courrier->getSubject()),
            $escape($courrier->getDirectionLabel()),
            $escape($this->formatDate($courrier->getMailDate())),
            $escape($courrier->getInterlocuteurLabel()),
            $escape($this->formatDate($courrier->getResponseDueAt())),
            $escape($url),
        );
    }

    private function formatDate(?\DateTimeInterface $date): string
    {
        return $date?->format('d/m/Y') ?? 'Non renseignée';
    }
}
