<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImageSearchResult;
use App\Entity\Pictogram;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WikimediaCommonsApiService
{
	private const API_URL = 'https://commons.wikimedia.org/w/api.php';
	private const MAX_LIMIT = 20;
	private const SOURCE = Pictogram::SOURCE_WIKIMEDIA_COMMONS;
	private const USER_AGENT = 'PictoRecette/1.0'; // TODO: add project contact information when available.

	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/svg+xml',
		'image/webp',
	];

	private const EXT_METADATA_KEYS = [
		'LicenseShortName',
		'LicenseUrl',
		'UsageTerms',
		'Artist',
		'Credit',
		'Attribution',
		'AttributionRequired',
		'ObjectName',
		'ImageDescription',
		'Restrictions',
	];

	private const SEARCH_TRANSLATIONS = [
		'fraise' => 'strawberry',
		'farine' => 'flour',
		'sucre' => 'sugar',
		'oeuf' => 'egg',
		'oeufs' => 'eggs',
		'œuf' => 'egg',
		'œufs' => 'eggs',
		'beurre' => 'butter',
		'lait' => 'milk',
		'chocolat' => 'chocolate',
		'pomme' => 'apple',
		'banane' => 'banana',
		'citron' => 'lemon',
		'tomate' => 'tomato',
		'carotte' => 'carrot',
	];

	private const THUMB_WIDTH = 400;

	public function __construct(
		private readonly HttpClientInterface $client,
		private readonly CacheInterface $cache,
		#[Autowire(service: 'monolog.logger')]
		private readonly LoggerInterface $logger,
	) {}

	/**
	 * @return ImageSearchResult[]
	 */
	public function search(string $query, int $limit = 12): array
	{
		$query = trim($query);
		if ($query === '') {
			return [];
		}

		$limit = max(1, min(self::MAX_LIMIT, $limit));
		$searchQuery = $this->translateSearchQuery($query);
		$cacheKey = 'wikimedia_commons_search_' . md5($searchQuery . ':' . $limit);

		try {
			return $this->cache->get($cacheKey, function (ItemInterface $item) use ($searchQuery, $limit): array {
				$item->expiresAfter(3600);

				return $this->fetchResults($searchQuery, $limit);
			});
		} catch (\Throwable $e) {
			$this->logger->warning('Cache Wikimedia Commons indisponible', [
				'query' => $query,
				'error' => $e->getMessage(),
			]);

			return $this->fetchResults($searchQuery, $limit);
		}
	}

	public function isAllowedLicense(?string $license): bool
	{
		if ($license === null) {
			return false;
		}

		$license = strtolower($license);

		return str_contains($license, 'cc0')
			|| str_contains($license, 'public domain')
			|| str_contains($license, 'cc by')
			|| str_contains($license, 'cc-by');
	}

	public function isAllowedMime(?string $mime): bool
	{
		if ($mime === null) {
			return false;
		}

		return in_array(strtolower($mime), self::ALLOWED_MIME_TYPES, true);
	}

	/**
	 * @return ImageSearchResult[]
	 */
	private function fetchResults(string $query, int $limit): array
	{
		try {
			$response = $this->client->request('GET', self::API_URL, [
				'headers' => [
					'Accept' => 'application/json',
					'User-Agent' => self::USER_AGENT,
				],
				'query' => [
					'action' => 'query',
					'format' => 'json',
					'formatversion' => '2',
					'generator' => 'search',
					'gsrsearch' => $query,
					'gsrnamespace' => '6',
					'gsrlimit' => (string) $limit,
					'prop' => 'imageinfo',
					'iiprop' => 'url|mime|size|extmetadata',
					'iiurlwidth' => (string) self::THUMB_WIDTH,
					'iiextmetadatalanguage' => 'fr',
					'iiextmetadatafilter' => implode('|', self::EXT_METADATA_KEYS),
				],
				'timeout' => 10,
				'max_redirects' => 3,
			]);

			if ($response->getStatusCode() !== 200) {
				$this->logger->warning('Erreur API Wikimedia Commons', [
					'status_code' => $response->getStatusCode(),
					'query' => $query,
				]);

				return [];
			}

			$data = $response->toArray(false);
		} catch (\Throwable $e) {
			$this->logger->warning('Recherche Wikimedia Commons indisponible', [
				'query' => $query,
				'error' => $e->getMessage(),
			]);

			return [];
		}

		$pages = $data['query']['pages'] ?? [];
		if (!is_array($pages)) {
			return [];
		}

		$results = [];
		foreach ($pages as $page) {
			if (!is_array($page)) {
				continue;
			}

			$result = $this->normalizePage($page);
			if ($result === null) {
				continue;
			}

			$results[] = $result;
		}

		return $results;
	}

	private function normalizePage(array $page): ?ImageSearchResult
	{
		$imageInfo = $page['imageinfo'][0] ?? null;
		if (!is_array($imageInfo)) {
			return null;
		}

		$mime = isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;
		if (!$this->isAllowedMime($mime)) {
			return null;
		}

		$originalUrl = isset($imageInfo['url']) ? (string) $imageInfo['url'] : null;
		$thumbnailUrl = isset($imageInfo['thumburl']) ? (string) $imageInfo['thumburl'] : $originalUrl;

		if ($thumbnailUrl === null || $thumbnailUrl === '') {
			return null;
		}

		$metadata = is_array($imageInfo['extmetadata'] ?? null) ? $imageInfo['extmetadata'] : [];
		$title = isset($page['title']) ? (string) $page['title'] : null;
		$license = $this->metadataValue($metadata, 'LicenseShortName')
			?? $this->metadataValue($metadata, 'UsageTerms');
		$author = $this->metadataValue($metadata, 'Artist');
		$credit = $this->metadataValue($metadata, 'Credit');
		$description = $this->metadataValue($metadata, 'ImageDescription')
			?? $this->metadataValue($metadata, 'ObjectName');
		$attribution = $this->metadataValue($metadata, 'Attribution')
			?? $this->buildAttribution($author, $license);

		return new ImageSearchResult(
			title: $title,
			source: self::SOURCE,
			sourceId: isset($page['pageid']) ? (string) $page['pageid'] : $title,
			fileUrl: $thumbnailUrl,
			thumbnailUrl: $thumbnailUrl,
			width: isset($imageInfo['thumbwidth']) ? (int) $imageInfo['thumbwidth'] : null,
			height: isset($imageInfo['thumbheight']) ? (int) $imageInfo['thumbheight'] : null,
			mime: $mime,
			license: $license,
			licenseUrl: $this->metadataValue($metadata, 'LicenseUrl'),
			author: $author,
			credit: $credit,
			description: $description,
			attribution: $attribution,
		);
	}

	private function metadataValue(array $metadata, string $key): ?string
	{
		$value = $metadata[$key]['value'] ?? null;
		if (!is_string($value)) {
			return null;
		}

		$value = $this->cleanHtml($value);
		if ($value === '') {
			return null;
		}

		return $value;
	}

	private function cleanHtml(string $value): string
	{
		$value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;

		return trim($value);
	}

	private function buildAttribution(?string $author, ?string $license): string
	{
		$parts = array_filter([
			$author ?: 'Auteur Wikimedia Commons',
			$license,
			'Wikimedia Commons',
		]);

		return implode(' - ', $parts);
	}

	private function translateSearchQuery(string $query): string
	{
		$normalized = mb_strtolower(trim($query));
		$ascii = strtr($normalized, [
			'à' => 'a',
			'â' => 'a',
			'ä' => 'a',
			'ç' => 'c',
			'é' => 'e',
			'è' => 'e',
			'ê' => 'e',
			'ë' => 'e',
			'î' => 'i',
			'ï' => 'i',
			'ô' => 'o',
			'ö' => 'o',
			'ù' => 'u',
			'û' => 'u',
			'ü' => 'u',
			'ÿ' => 'y',
			'œ' => 'oe',
		]);

		return self::SEARCH_TRANSLATIONS[$normalized]
			?? self::SEARCH_TRANSLATIONS[$ascii]
			?? $query;
	}
}
