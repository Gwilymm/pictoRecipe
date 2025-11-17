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
	// URL pour la France
	private const BASE_URL = 'https://fr.openfoodfacts.org/cgi/search.pl';

	public function __construct(
		private readonly HttpClientInterface $httpClient,
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
			'action'        => 'process',
			'json'          => '1',
			'page_size'     => $limit,
		];

		// Si une marque est spécifiée, utiliser la recherche avancée avec deux critères
		if ($brand !== null && trim($brand) !== '') {
			// Critère 1 : la marque
			$params['tagtype_0'] = 'brands';
			$params['tag_contains_0'] = 'contains';
			$params['tag_0'] = trim($brand);

			// Recherche simple pour le nom de produit (filtre les résultats de la marque)
			$params['search_terms'] = $query;
			$params['search_simple'] = '1';
		} else {
			// Recherche simple uniquement par nom
			$params['search_terms'] = $query;
			$params['search_simple'] = '1';
		}

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
				$productName = $p['product_name'] ?? $p['generic_name'] ?? '';
				$productBrand = $p['brands'] ?? '';
				$categories = $p['categories'] ?? '';

				// Filtrage intelligent : PRIORITÉ au nom du produit
				$searchLower = mb_strtolower($query);
				$nameLower = mb_strtolower($productName);
				$categoriesLower = mb_strtolower($categories);

				// Vérifier si le terme est dans le nom du produit
				$isInName = strpos($nameLower, $searchLower) !== false;

				// Pour les catégories, on est plus strict : on vérifie que c'est une catégorie principale
				// en cherchant le terme suivi d'une virgule ou en fin de chaîne (évite les sous-catégories)
				$isMainCategory = false;
				if (!$isInName) {
					// Chercher "beurre," ou "beurre" en fin de catégories, ou ",beurre," ou ",beurre"
					$patterns = [
						',' . $searchLower . ',',  // au milieu
						',' . $searchLower,        // à la fin
						$searchLower . ',',        // au début
					];
					foreach ($patterns as $pattern) {
						if (strpos($categoriesLower, $pattern) !== false) {
							$isMainCategory = true;
							break;
						}
					}
					// Si c'est le seul terme (catégorie unique)
					if (!$isMainCategory && $categoriesLower === $searchLower) {
						$isMainCategory = true;
					}
				}

				// On accepte uniquement si le terme est dans le nom OU si c'est une catégorie principale
				if (!$isInName && !$isMainCategory) {
					continue;
				}

				$results[] = [
					'id'       => $p['code'] ?? null,
					'name'     => $productName,
					'image'    => $p['image_front_url'] ?? $p['image_url'] ?? null,
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
