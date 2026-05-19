<?php

namespace App\Entity;

use App\Repository\CourrierActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourrierActionRepository::class)]
class CourrierAction
{
    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_RESPONSE_ADDED = 'response_added';

    public const TYPES = [
        self::TYPE_CREATED => 'Création',
        self::TYPE_UPDATED => 'Modification',
        self::TYPE_ASSIGNED => 'Imputation',
        self::TYPE_STATUS_CHANGED => 'Statut',
        self::TYPE_RESPONSE_ADDED => 'Réponse',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Courrier::class, inversedBy: 'actionLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Courrier $courrier = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 40)]
    private string $actionType = self::TYPE_UPDATED;

    #[ORM\Column(length: 255)]
    private string $summary = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourrier(): ?Courrier
    {
        return $this->courrier;
    }

    public function setCourrier(Courrier $courrier): self
    {
        $this->courrier = $courrier;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->actionType] ?? $this->actionType;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = trim($summary);

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details ? trim($details) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
