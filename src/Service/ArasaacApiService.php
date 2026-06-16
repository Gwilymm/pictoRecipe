<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service de communication avec l'API ARASAAC
 * 
 * Permet de rechercher des pictogrammes via l'API publique ARASAAC
 * Documentation : https://beta.arasaac.org/developers/api
 */
class ArasaacApiService
{
	private const API_BASE_URL = 'https://api.arasaac.org/api';
	private const PICTOGRAM_IMAGE_BASE_URL = 'https://static.arasaac.org/pictograms';

	public function __construct(
		private readonly HttpClientInterface $client,
		#[Autowire(service: 'monolog.logger')]
		private readonly LoggerInterface $logger
	) {}

	/**
	 * Recherche des pictogrammes par mot-clé en français
	 * 
	 * @param string $keyword Mot-clé de recherche
	 * @return array Tableau de pictogrammes avec leur ID, nom et URL d'image
	 * @throws \Exception En cas d'erreur réseau ou API
	 */
	public function search(string $keyword): array
	{
		if (empty(trim($keyword))) {
			return [];
		}

		try {
			$response = $this->client->request(
				'GET',
				sprintf('%s/pictograms/fr/search/%s', self::API_BASE_URL, urlencode($keyword)),
				['timeout' => 5]
			);

			$statusCode = $response->getStatusCode();

			// Si aucun résultat trouvé (204 ou 404)
			if ($statusCode === 204 || $statusCode === 404) {
				return [];
			}

			// Si erreur HTTP (sauf 404 qui signifie "pas de résultat")
			if ($statusCode !== 200) {
				$this->logger->error('Erreur API ARASAAC', [
					'status_code' => $statusCode,
					'keyword' => $keyword
				]);
				throw new \Exception(sprintf('Erreur API : code %d', $statusCode));
			}

			$data = $response->toArray();

			// Transformer les données pour simplifier l'utilisation dans le template
			return array_map(function ($pictogram) {
				$imageUrl = sprintf(
					'%s/%s/%s_500.png',
					self::PICTOGRAM_IMAGE_BASE_URL,
					$pictogram['_id'],
					$pictogram['_id']
				);

				return [
					'id' => $pictogram['_id'] ?? null,
					'keywords' => $pictogram['keywords'] ?? [],
					'name' => $pictogram['keywords'][0]['keyword'] ?? 'Sans nom',
					'imageUrl' => $imageUrl,
					'detailUrl' => sprintf('https://arasaac.org/pictograms/fr/%s', $pictogram['_id'] ?? ''),
					'notFound' => false,
				];
			}, $data);
		} catch (TransportExceptionInterface $e) {
			$this->logger->error('Erreur de transport HTTP', [
				'message' => $e->getMessage(),
				'keyword' => $keyword
			]);
			throw new \Exception('Impossible de contacter l\'API ARASAAC. Vérifiez votre connexion internet.');
		} catch (\Exception $e) {
			$this->logger->error('Erreur lors de la recherche de pictogrammes', [
				'message' => $e->getMessage(),
				'keyword' => $keyword
			]);
			throw $e;
		}
	}

	private function imageExists(string $url): bool
	{
		try {
			$res = $this->client->request('HEAD', $url, [
				'max_redirects' => 0,
				'timeout' => 2,
			]);
			return $res->getStatusCode() === 200;
		} catch (\Throwable $e) {
			return false;
		}
	}
}
