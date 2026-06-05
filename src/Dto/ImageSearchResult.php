<?php

namespace App\Dto;

final class ImageSearchResult
{
	public function __construct(
		public readonly ?string $title,
		public readonly string $source,
		public readonly ?string $sourceId,
		public readonly ?string $fileUrl,
		public readonly ?string $thumbnailUrl,
		public readonly ?int $width,
		public readonly ?int $height,
		public readonly ?string $mime,
		public readonly ?string $license,
		public readonly ?string $licenseUrl,
		public readonly ?string $author,
		public readonly ?string $credit,
		public readonly ?string $description,
		public readonly string $attribution,
	) {
	}

	/**
	 * @return array<string, int|string|null>
	 */
	public function toArray(): array
	{
		return [
			'title' => $this->title,
			'source' => $this->source,
			'source_id' => $this->sourceId,
			'file_url' => $this->fileUrl,
			'thumbnail_url' => $this->thumbnailUrl,
			'width' => $this->width,
			'height' => $this->height,
			'mime' => $this->mime,
			'license' => $this->license,
			'license_url' => $this->licenseUrl,
			'author' => $this->author,
			'credit' => $this->credit,
			'description' => $this->description,
			'attribution' => $this->attribution,
		];
	}
}
