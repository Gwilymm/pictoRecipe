<?php

namespace App\Entity;

use App\Repository\PictogramRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PictogramRepository::class)]
class Pictogram
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	#[Assert\NotBlank]
	private ?string $name = null;

	#[ORM\Column(length: 255)]
	private ?string $filePath = null;

	#[ORM\Column(length: 10, nullable: true)]
	private ?string $format = null;

	#[ORM\Column]
	private ?\DateTimeImmutable $createdAt = null;

	public function __construct()
	{
		$this->createdAt = new \DateTimeImmutable();
	}

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

	public function getFilePath(): ?string
	{
		return $this->filePath;
	}

	public function setFilePath(string $filePath): static
	{
		$this->filePath = $filePath;

		return $this;
	}

	public function getFormat(): ?string
	{
		return $this->format;
	}

	public function setFormat(?string $format): static
	{
		$this->format = $format;

		return $this;
	}

	public function getCreatedAt(): ?\DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(\DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;

		return $this;
	}
}
