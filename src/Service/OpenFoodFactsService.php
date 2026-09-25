<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use OpenFoodFacts\Api as OpenFoodFactsApi;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service pour interroger OpenFoodFacts via le SDK officiel,
 * avec fallback HTTP si le SDK échoue.
 */
final class OpenFoodFactsService
{
	// URL pour la France
	private const BASE_URL = 'https://world.openfoodfacts.org/cgi/search.pl';

	public function __construct(
		private readonly HttpClientInterface $httpClient,
		#[Autowire(service: 'monolog.logger')]
		private readonly LoggerInterface $logger
	) {}

	/**
	 * Recherche par nom (avec filtre optionnel par marque).
	 * Retourne un tableau propre pour la couche front.
	 *
	 * @param string $query Terme de recherche (nom d'ingrédient)
	 * @param int $limit Nombre maximum de résultats
	 * @param string|null $brand Filtre optionnel par marque
	 * @return array<int,array<string,mixed>>
	 */
	public function searchByName(string $query, int $limit = 10, ?string $brand = null): array
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

					$image = $arr['image_front_url'] ?? null;
					if (!$image || !str_contains($image, 'front_fr')) continue;

					$results[] = [
						'id'       => $arr['code'] ?? null,
						'name'     => $arr['product_name'] ?? $arr['generic_name'] ?? null,
						'image'    => $image,
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
		// L’ancienne combinaison action=process/search_simple=1 renvoie 503
		// sur l’infrastructure Open Food Facts. La requête CGI minimale est stable.
		$params = [
			'json'      => '1',
			'page_size' => $limit,
			'lc'        => 'fr',
		];

		// Si une marque est spécifiée, utiliser la recherche avancée avec deux critères
		if ($brand !== null && trim($brand) !== '') {
			// Critère 1 : la marque
			$params['tagtype_0'] = 'brands';
			$params['tag_contains_0'] = 'contains';
			$params['tag_0'] = trim($brand);

			// Recherche simple pour le nom de produit (filtre les résultats de la marque)
			$params['search_terms'] = $query;
		} else {
			// Recherche simple uniquement par nom
			$params['search_terms'] = $query;
		}

		try {
			$response = $this->httpClient->request('GET', self::BASE_URL, [
				'query'   => $params,
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (compatible; PictoRecette/1.0; +https://pictorecette.doc2sail.com)',
				],
				'timeout' => 5,
			]);
			$statusCode = $response->getStatusCode();
			if ($statusCode !== 200) {
				$this->logger->warning('pictogram.search.failed', [
					'source' => 'openfoodfacts',
					'keyword' => $query,
					'status_code' => $statusCode,
				]);

				return [];
			}

			$data = $response->toArray(false);

			if (!isset($data['products']) || !is_array($data['products'])) {
				return [];
			}

			$results = [];

			foreach ($data['products'] as $p) {
				$productName = $p['product_name'] ?? $p['generic_name'] ?? '';
				$productBrand = $p['brands'] ?? '';
				$categories = $p['categories'] ?? '';
				$image = $p['image_front_url'] ?? null;
				if (!$image || !str_contains($image, 'front_fr')) continue;

				$results[] = [
					'id'       => $p['code'] ?? null,
					'name'     => $productName,
					'image'    => $image,
					'brand'    => $productBrand,
					'category' => $categories,
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
