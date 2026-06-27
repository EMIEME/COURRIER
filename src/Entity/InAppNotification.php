<?php

namespace App\Entity;

use App\Repository\InAppNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InAppNotificationRepository::class)]
#[ORM\Table(name: 'in_app_notification')]
#[ORM\UniqueConstraint(name: 'UNIQ_IN_APP_NOTIFICATION_RECIPIENT_FINGERPRINT', columns: ['recipient_id', 'fingerprint'])]
#[ORM\Index(name: 'IDX_IN_APP_NOTIFICATION_RECIPIENT_ACTIVE_READ', columns: ['recipient_id', 'active', 'read_at'])]
class InAppNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(length: 40)]
    private string $type = '';

    #[ORM\Column(length: 20)]
    private string $severity = 'info';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(length: 120)]
    private string $route = '';

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $routeParams = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(User $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    public function syncGenerated(string $type, string $severity, string $title, string $message, string $route, array $routeParams): bool
    {
        $changed = false;

        foreach ([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'route' => $route,
        ] as $property => $value) {
            if ($this->{$property} !== $value) {
                $this->{$property} = $value;
                $changed = true;
            }
        }

        if ($this->routeParams !== $routeParams) {
            $this->routeParams = $routeParams;
            $changed = true;
        }

        if (!$this->active) {
            $this->active = true;
            $changed = true;
        }

        if ($changed) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $changed;
    }

    public function markRead(): bool
    {
        if ($this->isRead()) {
            return false;
        }

        $this->readAt = new \DateTimeImmutable();
        $this->updatedAt = $this->readAt;

        return true;
    }

    public function markUnread(): bool
    {
        if (!$this->isRead()) {
            return false;
        }

        $this->readAt = null;
        $this->updatedAt = new \DateTimeImmutable();

        return true;
    }

    public function deactivate(): bool
    {
        if (!$this->active) {
            return false;
        }

        $this->active = false;
        $this->updatedAt = new \DateTimeImmutable();

        return true;
    }
}
