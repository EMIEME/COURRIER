<?php

namespace App\Entity;

use App\Repository\ListOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ListOptionRepository::class)]
#[ORM\Table(name: 'list_option')]
#[ORM\UniqueConstraint(name: 'UNIQ_LIST_OPTION_CATEGORY_VALUE', columns: ['category', 'value'])]
#[UniqueEntity(fields: ['category', 'value'], message: 'Cette valeur existe deja dans cette liste.')]
class ListOption
{
    public const CATEGORY_TYPE = 'type';
    public const CATEGORY_NATURE = 'nature';
    public const CATEGORY_STATUS = 'status';
    public const CATEGORY_LOCALISATION = 'localisation';

    public const CATEGORIES = [
        self::CATEGORY_TYPE => 'Types',
        self::CATEGORY_NATURE => 'Natures',
        self::CATEGORY_STATUS => 'Statuts',
        self::CATEGORY_LOCALISATION => 'Localisations',
    ];

    public const OPEN_CATEGORIES = [
        self::CATEGORY_TYPE,
        self::CATEGORY_LOCALISATION,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [self::CATEGORY_TYPE, self::CATEGORY_NATURE, self::CATEGORY_STATUS, self::CATEGORY_LOCALISATION])]
    private string $category = self::CATEGORY_LOCALISATION;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $value = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $locked = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = trim($value);

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }
}
