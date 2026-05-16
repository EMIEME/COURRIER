<?php

namespace App\Entity;

use App\Repository\DestinataireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DestinataireRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Ce destinataire existe deja.')]
class Destinataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\ManyToMany(targetEntity: Courrier::class, mappedBy: 'destinataires')]
    private Collection $courriers;

    public function __construct()
    {
        $this->courriers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): self
    {
        $this->service = $service ? trim($service) : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email ? strtolower(trim($email)) : null;

        return $this;
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function removeCourrier(Courrier $courrier): self
    {
        $this->courriers->removeElement($courrier);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?: '';
    }
}
