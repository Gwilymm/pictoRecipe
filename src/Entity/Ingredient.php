<?php

namespace App\Entity;

use App\Repository\IngredientRepository;
use App\Entity\Pictogram;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
class Ingredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    #[Assert\NotBlank]
    private ?string $amount = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    private ?int $position = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pictogramUrl = null;

    #[ORM\ManyToOne(targetEntity: Pictogram::class)]
    private ?Pictogram $pictogram = null;

    #[ORM\ManyToOne(inversedBy: 'ingredients')]
    private ?Recipe $recipe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }


    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getPictogramUrl(): ?string
    {
        return $this->pictogramUrl;
    }

    public function setPictogramUrl(?string $pictogramUrl): static
    {
        $this->pictogramUrl = $pictogramUrl;

        return $this;
    }

    public function getPictogram(): ?Pictogram
    {
        return $this->pictogram;
    }

    public function setPictogram(?Pictogram $pictogram): static
    {
        $this->pictogram = $pictogram;

        return $this;
    }
}
