<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use OpenFoodFacts\Api as OpenFoodFactsApi;

/**
 * Service pour interroger OpenFoodFacts via le SDK officiel,
 * avec fallback HTTP si le SDK échoue.
 */
final class OpenFoodFactsService
{
	private const BASE_URL = 'https://world.openfoodfacts.org/cgi/search.pl';

	public function __construct(
		private readonly HttpClientInterface $httpClient,
		private readonly LoggerInterface $logger
	) {}

	/**
	 * Recherche par nom. Retourne un tableau propre pour la couche front.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function searchByName(string $query, int $limit = 10): array
	{
		$query = trim($query);
		if ($query === '') {
			return [];
		}

		// --- 1) Essai via SDK OFF ---
		if (class_exists(OpenFoodFactsApi::class)) {
			try {
				// SDK correct : doc officielle
				$api = new OpenFoodFactsApi('food', 'fr');

				// Renvoie une Collection
				$collection = $api->search($query);

				$results = [];
				$count = 0;

				foreach ($collection as $doc) {
					if ($count >= $limit) {
						break;
					}

					$arr = method_exists($doc, 'getData')
						? $doc->getData()
						: (is_array($doc) ? $doc : []);

					if (!$arr) continue;

					$results[] = [
						'id'       => $arr['code'] ?? null,
						'name'     => $arr['product_name'] ?? $arr['generic_name'] ?? null,
						'image'    => $arr['image_front_url'] ?? $arr['image_url'] ?? null,
						'brand'    => $arr['brands'] ?? null,
						'category' => $arr['categories'] ?? null,
					];

					$count++;
				}

				if ($results) {
					return $results;
				}

				// si rien : fallback HTTP
			} catch (\Throwable $e) {
				$this->logger->warning('OpenFoodFacts SDK failed → fallback HTTP', [
					'exception' => $e
				]);
			}
		}

		// --- 2) Fallback HTTP ---
		$params = [
			'search_terms'  => $query,
			'search_simple' => '1',
			'action'        => 'process',
			'json'          => '1',
			'page_size'     => $limit,
		];

		try {
			$response = $this->httpClient->request('GET', self::BASE_URL, [
				'query'   => $params,
				'timeout' => 5,
			]);

			$data = $response->toArray(false);

			if (!isset($data['products']) || !is_array($data['products'])) {
				return [];
			}

			$results = [];

			foreach ($data['products'] as $p) {
				$results[] = [
					'id'       => $p['code'] ?? null,
					'name'     => $p['product_name'] ?? $p['generic_name'] ?? null,
					'image'    => $p['image_front_url'] ?? $p['image_url'] ?? null,
					'brand'    => $p['brands'] ?? null,
					'category' => $p['categories'] ?? null,
				];
			}

			return $results;
		} catch (\Throwable $e) {
			$this->logger->error('OpenFoodFacts HTTP request failed', [
				'exception' => $e
			]);
			return [];
		}
	}
}
