<?php

namespace App\Entity;

use App\Repository\UtensilRepository;
use App\Entity\Pictogram;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtensilRepository::class)]
class Utensil
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	#[Assert\NotBlank]
	private ?string $name = null;

	#[ORM\Column(length: 500, nullable: true)]
	private ?string $pictogramUrl = null;

	#[ORM\ManyToOne(targetEntity: Pictogram::class)]
	private ?Pictogram $pictogram = null;

	/**
	 * @var Collection<int, Recipe>
	 */
	#[ORM\ManyToMany(targetEntity: Recipe::class, mappedBy: 'utensils')]
	private Collection $recipes;

	public function __construct()
	{
		$this->recipes = new ArrayCollection();
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
		$this->name = $name;
		return $this;
	}

	public function getPictogramUrl(): ?string
	{
		return $this->pictogramUrl;
	}

	public function setPictogramUrl(?string $pictogramUrl): self
	{
		$this->pictogramUrl = $pictogramUrl;
		return $this;
	}

	public function getPictogram(): ?Pictogram
	{
		return $this->pictogram;
	}

	public function setPictogram(?Pictogram $pictogram): self
	{
		$this->pictogram = $pictogram;
		return $this;
	}

	/**
	 * @return Collection<int, Recipe>
	 */
	public function getRecipes(): Collection
	{
		return $this->recipes;
	}

	public function addRecipe(Recipe $recipe): self
	{
		if (!$this->recipes->contains($recipe)) {
			$this->recipes->add($recipe);
			$recipe->addUtensil($this);
		}
		return $this;
	}

	public function removeRecipe(Recipe $recipe): self
	{
		if ($this->recipes->removeElement($recipe)) {
			$recipe->removeUtensil($this);
		}
		return $this;
	}
}
