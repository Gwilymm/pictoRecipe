<?php

namespace App\Controller;

use App\Entity\Pictogram;
use App\Service\ArasaacApiService;
use App\Repository\PictogramRepository;
use App\Service\WikimediaCommonsApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * API Controller pour la recherche et l'import de pictogrammes.
 */
class PictogramApiController extends AbstractController
{
	private const MAX_IMPORT_BYTES = 5242880;
	private const USER_AGENT = 'PictoRecette/1.0';
	private const WIKIMEDIA_IMAGE_HOST = 'upload.wikimedia.org';

	public function __construct(
		private readonly ArasaacApiService $arasaacService,
		private readonly WikimediaCommonsApiService $wikimediaCommonsService,
		private readonly HttpClientInterface $httpClient,
		private readonly EntityManagerInterface $entityManager,
		#[Autowire('%pictogram_directory%')]
		private readonly string $pictogramDirectory,
		#[Autowire(service: 'monolog.logger')]
		private readonly LoggerInterface $logger,
	) {}

	/**
	 * Recherche de pictogrammes via l'API ARASAAC
	 * 
	 * @param Request $request
	 * @return JsonResponse
	 */
	#[Route('/api/pictograms/search', name: 'api_pictogram_search', methods: ['GET'])]
	public function search(Request $request, PictogramRepository $pictogramRepository): JsonResponse
	{
		$t0 = microtime(true);
		$keyword = trim((string) $request->query->get('q', ''));
		$baseContext = array_merge($this->requestLogContext($request), [
			'source' => 'local_arasaac',
			'keyword' => $keyword,
		]);

		$this->logger->info('pictogram.search.requested', $baseContext);

		if ($keyword === '') {
			$this->logger->warning('pictogram.search.failed', array_merge($baseContext, [
				'reason' => 'missing_keyword',
				'status' => Response::HTTP_BAD_REQUEST,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'success' => false,
				'message' => 'Le mot-clé de recherche est requis',
				'results' => []
			], 400);
		}

		// Prepare aggregated results: local pictograms + ARASAAC
		$aggregated = [];

		// 1) Local pictograms matching the keyword. Unvalidated Wikimedia imports stay out of this automatic flow.
		$locals = $pictogramRepository->findSearchableByKeyword($keyword, 50);
		$localCount = count($locals);

		foreach ($locals as $local) {
			if (!$local->getFilePath()) {
				continue;
			}

			$aggregated[] = [
				'id' => 'local_' . $local->getId(),
				'name' => $local->getName(),
				'imageUrl' => '/' . ltrim($local->getFilePath(), '/'),
				'source' => 'local',
				'originSource' => $local->getSource(),
				'validated' => $local->isValidated(),
			];
		}

		// 2) ARASAAC results (best-effort). If ARASAAC fails, return only locals.
		try {
			$arasaac = $this->arasaacService->search($keyword);
			$arasaacCount = count($arasaac);

			// annotate source and append
			foreach ($arasaac as $item) {
				$item['source'] = 'arasaac';
				$aggregated[] = $item;
			}
		} catch (\Exception $e) {
			$arasaacCount = null;
			$this->logger->warning('pictogram.search.failed', array_merge($baseContext, [
				'source' => 'arasaac',
				'exception_class' => $e::class,
				'exception_message' => $e->getMessage(),
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));
		}

		$event = count($aggregated) > 0 ? 'pictogram.search.completed' : 'pictogram.search.no_result';
		$this->logger->info($event, array_merge($baseContext, [
			'local_count' => $localCount,
			'arasaac_count' => $arasaacCount ?? 0,
			'result_count' => count($aggregated),
			'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			'status' => Response::HTTP_OK,
		]));

		return $this->json([
			'success' => true,
			'keyword' => $keyword,
			'count' => count($aggregated),
			'results' => $aggregated
		]);
	}

	#[Route('/api/pictograms/wikimedia/search', name: 'api_pictogram_wikimedia_search', methods: ['GET'])]
	public function searchWikimedia(Request $request): JsonResponse
	{
		$t0 = microtime(true);
		$query = trim((string) $request->query->get('q', ''));
		$limit = (int) $request->query->get('limit', 12);
		$baseContext = array_merge($this->requestLogContext($request), [
			'source' => Pictogram::SOURCE_WIKIMEDIA_COMMONS,
			'keyword' => $query,
			'limit' => $limit,
		]);

		$this->logger->info('pictogram.search.requested', $baseContext);

		if ($query === '') {
			$this->logger->warning('pictogram.search.failed', array_merge($baseContext, [
				'reason' => 'missing_keyword',
				'status' => Response::HTTP_BAD_REQUEST,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'Le paramètre q est obligatoire.',
				'results' => [],
			], Response::HTTP_BAD_REQUEST);
		}

		$results = array_map(
			static fn($result): array => $result->toArray(),
			$this->wikimediaCommonsService->search($query, $limit)
		);

		$event = count($results) > 0 ? 'pictogram.search.completed' : 'pictogram.search.no_result';
		$this->logger->info($event, array_merge($baseContext, [
			'result_count' => count($results),
			'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			'status' => Response::HTTP_OK,
		]));

		return $this->json([
			'source' => Pictogram::SOURCE_WIKIMEDIA_COMMONS,
			'query' => $query,
			'results' => $results,
		]);
	}

	#[Route('/api/pictograms/import', name: 'api_pictogram_import', methods: ['POST'])]
	public function import(Request $request, PictogramRepository $pictogramRepository): JsonResponse
	{
		$t0 = microtime(true);
		$baseContext = array_merge($this->requestLogContext($request), [
			'source' => Pictogram::SOURCE_WIKIMEDIA_COMMONS,
			'content_length' => $this->contentLength($request),
		]);
		$this->logger->info('pictogram.upload.requested', $baseContext);

		try {
			$payload = $request->toArray();
		} catch (\Throwable $e) {
			$this->logger->warning('pictogram.upload.failed', array_merge($baseContext, [
				'reason' => 'invalid_json',
				'exception_class' => $e::class,
				'exception_message' => $e->getMessage(),
				'status' => Response::HTTP_BAD_REQUEST,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'Le JSON envoyé est invalide.',
				'results' => [],
			], Response::HTTP_BAD_REQUEST);
		}

		if (($this->payloadString($payload, 'source') ?? '') !== Pictogram::SOURCE_WIKIMEDIA_COMMONS) {
			$this->logger->warning('pictogram.upload.failed', array_merge($baseContext, [
				'reason' => 'unsupported_source',
				'status' => Response::HTTP_BAD_REQUEST,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'Seules les images Wikimedia Commons peuvent être importées par cette route.',
				'results' => [],
			], Response::HTTP_BAD_REQUEST);
		}

		$sourceId = $this->payloadString($payload, 'source_id');
		$imageUrl = $this->payloadString($payload, 'image_url');
		$license = $this->payloadString($payload, 'license');
		$attribution = $this->payloadString($payload, 'attribution');
		$mime = $this->payloadString($payload, 'mime');
		$importContext = array_merge($baseContext, [
			'source_id' => $sourceId,
			'image_host' => $imageUrl ? parse_url($imageUrl, PHP_URL_HOST) : null,
			'mime' => $mime,
			'license' => $license,
		]);

		if ($sourceId === null || $imageUrl === null || $license === null || $attribution === null || $mime === null) {
			$this->logger->warning('pictogram.upload.failed', array_merge($importContext, [
				'reason' => 'missing_required_fields',
				'status' => Response::HTTP_BAD_REQUEST,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'source_id, image_url, license, attribution et mime sont obligatoires.',
				'results' => [],
			], Response::HTTP_BAD_REQUEST);
		}

		if (!$this->wikimediaCommonsService->isAllowedLicense($license)) {
			$this->logger->warning('pictogram.upload.failed', array_merge($importContext, [
				'reason' => 'unsupported_license',
				'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'Cette licence Wikimedia n’est pas prise en charge pour le moment.',
				'results' => [],
			], Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		if (!$this->wikimediaCommonsService->isAllowedMime($mime)) {
			$this->logger->warning('pictogram.upload.failed', array_merge($importContext, [
				'reason' => 'unsupported_mime',
				'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'Ce format d’image Wikimedia n’est pas pris en charge.',
				'results' => [],
			], Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		if (!$this->isAllowedWikimediaImageUrl($imageUrl)) {
			$this->logger->warning('pictogram.upload.failed', array_merge($importContext, [
				'reason' => 'disallowed_image_url',
				'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => 'URL Wikimedia non autorisée.',
				'results' => [],
			], Response::HTTP_UNPROCESSABLE_ENTITY);
		}

		$pictogram = $pictogramRepository->findOneBy([
			'source' => Pictogram::SOURCE_WIKIMEDIA_COMMONS,
			'sourceId' => $sourceId,
		]);
		$isNew = $pictogram === null;

		try {
			if ($pictogram === null) {
				$pictogram = new Pictogram();
				$storedImage = $this->storeWikimediaImage($imageUrl, $mime);
				$pictogram
					->setFilePath($storedImage['filePath'])
					->setLocalPath($storedImage['filePath'])
					->setFormat($storedImage['format'])
					->setMime($storedImage['mime'])
					->setValidated(false);

				$this->entityManager->persist($pictogram);
			} elseif (!$pictogram->getFilePath()) {
				$storedImage = $this->storeWikimediaImage($imageUrl, $mime);
				$pictogram
					->setFilePath($storedImage['filePath'])
					->setLocalPath($storedImage['filePath'])
					->setFormat($storedImage['format'])
					->setMime($storedImage['mime']);
			}
		} catch (\Throwable $e) {
			$this->logger->error('pictogram.upload.failed', array_merge($importContext, [
				'reason' => 'store_wikimedia_image_failed',
				'exception_class' => $e::class,
				'exception_message' => $e->getMessage(),
				'status' => Response::HTTP_BAD_GATEWAY,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			]));

			return $this->json([
				'error' => "Impossible d'enregistrer l'image Wikimedia.",
				'details' => $e->getMessage(),
				'results' => [],
			], Response::HTTP_BAD_GATEWAY);
		}

		$label = $this->buildPictogramLabel($payload);
		$pictogram
			->setName($label)
			->setLabel($label)
			->setSource(Pictogram::SOURCE_WIKIMEDIA_COMMONS)
			->setSourceId($sourceId)
			->setImageUrl($imageUrl)
			->setThumbnailUrl($this->payloadString($payload, 'thumbnail_url'))
			->setLicense($license)
			->setLicenseUrl($this->payloadString($payload, 'license_url'))
			->setAuthor($this->payloadString($payload, 'author'))
			->setCredit($this->payloadString($payload, 'credit'))
			->setAttribution($attribution);

		$this->entityManager->flush();

		$this->logger->info('pictogram.upload.saved', array_merge($importContext, [
			'pictogram_id' => $pictogram->getId(),
			'is_new' => $isNew,
			'file_path' => $pictogram->getFilePath(),
			'status' => $isNew ? Response::HTTP_CREATED : Response::HTTP_OK,
			'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
		]));

		return $this->json([
			'id' => $pictogram->getId(),
			'message' => 'Image Wikimedia enregistrée dans la bibliothèque.',
			'pictogram' => [
				'id' => $pictogram->getId(),
				'name' => $pictogram->getName(),
				'image_url' => '/' . ltrim((string) $pictogram->getFilePath(), '/'),
				'validated' => $pictogram->isValidated(),
				'license' => $pictogram->getLicense(),
				'attribution' => $pictogram->getAttribution(),
			],
		], $isNew ? Response::HTTP_CREATED : Response::HTTP_OK);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function requestLogContext(Request $request): array
	{
		return [
			'request_id' => $request->attributes->get('request_id'),
			'route' => $request->attributes->get('_route'),
			'method' => $request->getMethod(),
			'content_length' => $this->contentLength($request),
		];
	}

	private function payloadString(array $payload, string $key): ?string
	{
		$value = $payload[$key] ?? null;
		if (!is_scalar($value)) {
			return null;
		}

		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}

	private function contentLength(Request $request): int
	{
		return max(0, (int) $request->headers->get('content-length', 0));
	}

	private function buildPictogramLabel(array $payload): string
	{
		$label = $this->payloadString($payload, 'label')
			?? $this->payloadString($payload, 'title')
			?? 'Image Wikimedia';
		$label = preg_replace('/^File:/i', '', $label) ?? $label;
		$label = str_replace('_', ' ', $label);
		$label = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $label) ?? $label;
		$label = trim($label);

		if ($label === '') {
			return 'Image Wikimedia';
		}

		return mb_substr($label, 0, 255);
	}

	/**
	 * @return array{filePath: string, format: string, mime: string}
	 */
	private function storeWikimediaImage(string $url, string $expectedMime): array
	{
		$response = $this->httpClient->request('GET', $url, [
			'headers' => [
				'Accept' => 'image/*,*/*;q=0.8',
				'User-Agent' => self::USER_AGENT,
			],
			'timeout' => 30,
			'max_redirects' => 3,
		]);

		if ($response->getStatusCode() !== 200) {
			throw new \RuntimeException(sprintf('Wikimedia a répondu avec le statut %d.', $response->getStatusCode()));
		}

		$headers = $response->getHeaders(false);
		$contentLength = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : 0;
		if ($contentLength > self::MAX_IMPORT_BYTES) {
			throw new \RuntimeException('Image trop volumineuse.');
		}

		$mime = $this->normalizeMime($headers['content-type'][0] ?? $expectedMime);
		if (!$this->wikimediaCommonsService->isAllowedMime($mime)) {
			$mime = $this->normalizeMime($expectedMime);
		}

		if (!$this->wikimediaCommonsService->isAllowedMime($mime)) {
			throw new \RuntimeException('Type MIME non autorisé.');
		}

		$content = $response->getContent(false);
		if (strlen($content) > self::MAX_IMPORT_BYTES) {
			throw new \RuntimeException('Image trop volumineuse.');
		}

		if (!is_dir($this->pictogramDirectory) && !mkdir($this->pictogramDirectory, 0755, true) && !is_dir($this->pictogramDirectory)) {
			throw new \RuntimeException('Le dossier des pictogrammes est inaccessible.');
		}

		$format = $this->extensionForMime($mime);
		$filename = bin2hex(random_bytes(16)) . '.' . $format;
		$destination = rtrim($this->pictogramDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

		if (file_put_contents($destination, $content) === false) {
			throw new \RuntimeException("Impossible d'écrire le fichier local.");
		}

		return [
			'filePath' => 'uploads/pictograms/' . $filename,
			'format' => $format,
			'mime' => $mime,
		];
	}

	private function normalizeMime(string $mime): string
	{
		return strtolower(trim(explode(';', $mime)[0]));
	}

	private function extensionForMime(string $mime): string
	{
		return match ($mime) {
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/svg+xml' => 'svg',
			'image/webp' => 'webp',
			default => 'img',
		};
	}

	private function isAllowedWikimediaImageUrl(string $url): bool
	{
		$scheme = parse_url($url, PHP_URL_SCHEME);
		$host = parse_url($url, PHP_URL_HOST);

		return $scheme === 'https' && strtolower((string) $host) === self::WIKIMEDIA_IMAGE_HOST;
	}
}
