<?php

namespace App\Entity;

use App\Repository\CourrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourrierRepository::class)]
class Courrier
{
    public const DIRECTION_ENTRANT = 'entrant';
    public const DIRECTION_SORTANT = 'sortant';
    public const DIRECTION_INTERNE = 'note_interne';

    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_TRAITE = 'traite';
    public const STATUS_URGENT = 'urgent';

    public const DIRECTIONS = [
        'Courrier recu' => self::DIRECTION_ENTRANT,
        'Courrier envoye' => self::DIRECTION_SORTANT,
        'Note interne' => self::DIRECTION_INTERNE,
    ];

    public const TYPES = [
        'Administratif' => 'Administratif',
        'Facture' => 'Facture',
        'Ressources humaines (RH)' => 'RH',
        'Juridique' => 'Juridique',
        'Technique' => 'Technique',
        'Invitation' => 'Invitation',
        'Rapport' => 'Rapport',
        'Autre' => 'Autre',
    ];

    public const STATUSES = [
        'En cours' => self::STATUS_EN_COURS,
        'Traite' => self::STATUS_TRAITE,
        'Urgent' => self::STATUS_URGENT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $mailDate = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::DIRECTION_ENTRANT, self::DIRECTION_SORTANT, self::DIRECTION_INTERNE])]
    private string $direction = self::DIRECTION_ENTRANT;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $sender = null;

    #[ORM\ManyToOne(targetEntity: Destinataire::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Destinataire $senderContact = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $recipient = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_EN_COURS, self::STATUS_TRAITE, self::STATUS_URGENT])]
    private string $status = self::STATUS_EN_COURS;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentFilename = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'assignedCourriers')]
    #[ORM\JoinTable(name: 'courrier_imputation')]
    private Collection $assignedTo;

    /**
     * @var Collection<int, Destinataire>
     */
    #[ORM\ManyToMany(targetEntity: Destinataire::class, inversedBy: 'courriers')]
    #[ORM\JoinTable(name: 'courrier_destinataire')]
    private Collection $destinataires;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $responseDueAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->mailDate = new \DateTimeImmutable();
        $this->assignedTo = new ArrayCollection();
        $this->destinataires = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMailDate(): ?\DateTimeInterface
    {
        return $this->mailDate;
    }

    public function setMailDate(\DateTimeInterface $mailDate): self
    {
        $this->mailDate = $mailDate;

        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(?string $sender): self
    {
        $this->sender = trim($sender) ?: null;

        return $this;
    }

    public function getSenderContact(): ?Destinataire
    {
        return $this->senderContact;
    }

    public function setSenderContact(?Destinataire $senderContact): self
    {
        $this->senderContact = $senderContact;

        return $this;
    }

    public function syncSenderSnapshot(): void
    {
        if ($this->senderContact) {
            $this->sender = (string) $this->senderContact;
        }
    }

    public function clearSender(): void
    {
        $this->senderContact = null;
        $this->sender = null;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): self
    {
        $this->recipient = $recipient ? trim($recipient) : null;

        return $this;
    }

    /**
     * @return Collection<int, Destinataire>
     */
    public function getDestinataires(): Collection
    {
        return $this->destinataires;
    }

    public function addDestinataire(Destinataire $destinataire): self
    {
        if (!$this->destinataires->contains($destinataire)) {
            $this->destinataires->add($destinataire);
        }

        return $this;
    }

    public function removeDestinataire(Destinataire $destinataire): self
    {
        $this->destinataires->removeElement($destinataire);

        return $this;
    }

    /**
     * @param iterable<Destinataire> $destinataires
     */
    public function setDestinataires(iterable $destinataires): self
    {
        $this->destinataires->clear();

        foreach ($destinataires as $destinataire) {
            $this->addDestinataire($destinataire);
        }

        return $this;
    }

    public function clearDestinataires(): self
    {
        $this->destinataires->clear();
        $this->recipient = null;

        return $this;
    }

    public function syncRecipientSnapshot(): void
    {
        if (!$this->destinataires->isEmpty()) {
            $this->recipient = implode(', ', $this->destinataires->map(fn (Destinataire $destinataire) => (string) $destinataire)->toArray());
        }
    }

    public function getRecipientLabel(): string
    {
        return $this->recipient ?: 'Non renseigne';
    }

    public function getSenderLabel(): string
    {
        return $this->sender ?: 'Non renseigne';
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = trim($type);

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content ?: null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAttachmentFilename(): ?string
    {
        return $this->attachmentFilename;
    }

    public function setAttachmentFilename(?string $attachmentFilename): self
    {
        $this->attachmentFilename = $attachmentFilename;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedTo(): Collection
    {
        return $this->assignedTo;
    }

    public function addAssignedTo(User $assignedTo): self
    {
        if (!$this->assignedTo->contains($assignedTo)) {
            $this->assignedTo->add($assignedTo);
        }

        return $this;
    }

    public function removeAssignedTo(User $assignedTo): self
    {
        $this->assignedTo->removeElement($assignedTo);

        return $this;
    }

    /**
     * @param iterable<User> $assignedUsers
     */
    public function setAssignedTo(iterable $assignedUsers): self
    {
        $this->assignedTo->clear();

        foreach ($assignedUsers as $assignedUser) {
            $this->addAssignedTo($assignedUser);
        }

        return $this;
    }

    public function clearAssignedTo(): self
    {
        $this->assignedTo->clear();

        return $this;
    }

    public function getAssignedToLabel(): string
    {
        if ($this->assignedTo->isEmpty()) {
            return 'Non impute';
        }

        return implode(', ', $this->assignedTo->map(fn (User $user) => (string) $user)->toArray());
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getResponseDueAt(): ?\DateTimeInterface
    {
        return $this->responseDueAt;
    }

    public function setResponseDueAt(?\DateTimeInterface $responseDueAt): self
    {
        $this->responseDueAt = $responseDueAt;

        return $this;
    }

    public function getResponseNotes(): ?string
    {
        return $this->responseNotes;
    }

    public function setResponseNotes(?string $responseNotes): self
    {
        $this->responseNotes = $responseNotes ?: null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStatusLabel(): string
    {
        return array_flip(self::STATUSES)[$this->status] ?? $this->status;
    }

    public function getDirectionLabel(): string
    {
        return array_flip(self::DIRECTIONS)[$this->direction] ?? $this->direction;
    }
}
