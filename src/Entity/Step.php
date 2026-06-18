<?php

namespace App\Entity;

use App\Repository\StepRepository;
use App\Entity\Pictogram;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StepRepository::class)]
class Step
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $position = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $content = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pictogramUrl = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pictogramUrls = null;

    #[ORM\ManyToOne(targetEntity: Pictogram::class)]
    private ?Pictogram $pictogram = null;

    #[ORM\ManyToOne(inversedBy: 'steps')]
    private ?Recipe $recipe = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content ?? '';

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

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

    public function getPictogramUrls(): ?array
    {
        return $this->pictogramUrls;
    }

    public function setPictogramUrls(?array $pictogramUrls): static
    {
        $this->pictogramUrls = $pictogramUrls;

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

    public function addPictogramUrl(string $url): static
    {
        if ($this->pictogramUrls === null) {
            $this->pictogramUrls = [];
        }

        if (!in_array($url, $this->pictogramUrls, true)) {
            $this->pictogramUrls[] = $url;
        }

        return $this;
    }

    public function removePictogramUrl(string $url): static
    {
        if ($this->pictogramUrls !== null) {
            $this->pictogramUrls = array_values(array_filter(
                $this->pictogramUrls,
                fn($item) => $item !== $url
            ));
        }

        return $this;
    }
}
