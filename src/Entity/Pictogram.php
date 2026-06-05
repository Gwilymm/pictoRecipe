<?php

namespace App\Entity;

use App\Repository\PictogramRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PictogramRepository::class)]
#[UniqueEntity(
	fields: ['name'],
	message: 'Un pictogramme avec ce nom existe déjà.'
)]
class Pictogram
{
	public const SOURCE_ARASAAC = 'arasaac';
	public const SOURCE_OPEN_FOOD_FACTS = 'open_food_facts';
	public const SOURCE_USER_UPLOAD = 'user_upload';
	public const SOURCE_WIKIMEDIA_COMMONS = 'wikimedia_commons';

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

	#[ORM\Column(length: 80)]
	private string $source = self::SOURCE_USER_UPLOAD;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $sourceId = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $label = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $imageUrl = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $localPath = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $thumbnailUrl = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $license = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $licenseUrl = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $author = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $credit = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $attribution = null;

	#[ORM\Column(length: 120, nullable: true)]
	private ?string $mime = null;

	#[ORM\Column]
	private bool $validated = true;

	#[ORM\Column]
	private ?\DateTimeImmutable $createdAt = null;

	#[ORM\Column(nullable: true)]
	private ?\DateTimeImmutable $updatedAt = null;

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
		$this->touch();

		return $this;
	}

	public function getFilePath(): ?string
	{
		return $this->filePath;
	}

	public function setFilePath(string $filePath): static
	{
		$this->filePath = $filePath;
		$this->localPath = $filePath;
		$this->touch();

		return $this;
	}

	public function getFormat(): ?string
	{
		return $this->format;
	}

	public function setFormat(?string $format): static
	{
		$this->format = $format;
		$this->touch();

		return $this;
	}

	public function getSource(): string
	{
		return $this->source;
	}

	public function setSource(string $source): static
	{
		$this->source = $source;
		$this->touch();

		return $this;
	}

	public function getSourceId(): ?string
	{
		return $this->sourceId;
	}

	public function setSourceId(?string $sourceId): static
	{
		$this->sourceId = $sourceId;
		$this->touch();

		return $this;
	}

	public function getLabel(): ?string
	{
		return $this->label;
	}

	public function setLabel(?string $label): static
	{
		$this->label = $label;
		$this->touch();

		return $this;
	}

	public function getImageUrl(): ?string
	{
		return $this->imageUrl;
	}

	public function setImageUrl(?string $imageUrl): static
	{
		$this->imageUrl = $imageUrl;
		$this->touch();

		return $this;
	}

	public function getLocalPath(): ?string
	{
		return $this->localPath;
	}

	public function setLocalPath(?string $localPath): static
	{
		$this->localPath = $localPath;
		$this->touch();

		return $this;
	}

	public function getThumbnailUrl(): ?string
	{
		return $this->thumbnailUrl;
	}

	public function setThumbnailUrl(?string $thumbnailUrl): static
	{
		$this->thumbnailUrl = $thumbnailUrl;
		$this->touch();

		return $this;
	}

	public function getLicense(): ?string
	{
		return $this->license;
	}

	public function setLicense(?string $license): static
	{
		$this->license = $license;
		$this->touch();

		return $this;
	}

	public function getLicenseUrl(): ?string
	{
		return $this->licenseUrl;
	}

	public function setLicenseUrl(?string $licenseUrl): static
	{
		$this->licenseUrl = $licenseUrl;
		$this->touch();

		return $this;
	}

	public function getAuthor(): ?string
	{
		return $this->author;
	}

	public function setAuthor(?string $author): static
	{
		$this->author = $author;
		$this->touch();

		return $this;
	}

	public function getCredit(): ?string
	{
		return $this->credit;
	}

	public function setCredit(?string $credit): static
	{
		$this->credit = $credit;
		$this->touch();

		return $this;
	}

	public function getAttribution(): ?string
	{
		return $this->attribution;
	}

	public function setAttribution(?string $attribution): static
	{
		$this->attribution = $attribution;
		$this->touch();

		return $this;
	}

	public function getMime(): ?string
	{
		return $this->mime;
	}

	public function setMime(?string $mime): static
	{
		$this->mime = $mime;
		$this->touch();

		return $this;
	}

	public function isValidated(): bool
	{
		return $this->validated;
	}

	public function setValidated(bool $validated): static
	{
		$this->validated = $validated;
		$this->touch();

		return $this;
	}

	public function isWikimediaCommons(): bool
	{
		return $this->source === self::SOURCE_WIKIMEDIA_COMMONS;
	}

	public function getSourceDisplayName(): string
	{
		return match ($this->source) {
			self::SOURCE_ARASAAC => 'ARASAAC',
			self::SOURCE_OPEN_FOOD_FACTS => 'OpenFoodFacts',
			self::SOURCE_WIKIMEDIA_COMMONS => 'Wikimedia Commons',
			default => 'Bibliothèque locale',
		};
	}

	public function getCreatedAt(): ?\DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(\DateTimeImmutable $createdAt): static
	{
		$this->createdAt = $createdAt;
		$this->touch();

		return $this;
	}

	public function getUpdatedAt(): ?\DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

	private function touch(): void
	{
		$this->updatedAt = new \DateTimeImmutable();
	}
}
