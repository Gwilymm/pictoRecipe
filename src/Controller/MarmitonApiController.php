<?php

namespace App\Controller;

use App\Service\MarmitonScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/marmiton', name: 'api_marmiton_')]
class MarmitonApiController extends AbstractController
{
	public function __construct(
		private MarmitonScraperService $scraperService,
		private LoggerInterface $logger
	) {}

	/**
	 * SEARCH — Recherche Marmiton
	 */
	#[Route('/search', name: 'search', methods: ['POST', 'GET'])]
	public function search(Request $request): JsonResponse
	{
		try {
			// --- Support GET et POST ----
			if ($request->getMethod() === 'POST') {
				$body = json_decode($request->getContent(), true);
				$query   = $body['q'] ?? $body['query'] ?? '';
				$limit   = (int)($body['limit'] ?? 20);
				$filters = $body['filters'] ?? [];
			} else {
				$query = $request->query->get('q')
					?? $request->query->get('query')
					?? '';

				$limit = (int)($request->query->get('limit') ?? 20);
				$filters = [];

				if ($request->query->get('withPhoto')) {
					$filters['withPhoto'] = true;
				}
			}

			if ($query === '') {
				return new JsonResponse([
					'success' => false,
					'error' => 'Missing search term (q or query)',
				], Response::HTTP_BAD_REQUEST);
			}

			$this->logger->info('Marmiton search request', [
				'query' => $query,
				'limit' => $limit,
				'filters' => $filters,
			]);

			// --- APPEL EXACT À LA BONNE MÉTHODE ---
			$results = $this->scraperService->searchRecipes(
				$query,
				$limit,
				$filters
			);

			return new JsonResponse([
				'success' => true,
				'results' => $results,
				'count' => count($results),
			]);
		} catch (\Throwable $e) {

			$this->logger->error('Error in Marmiton search', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return new JsonResponse([
				'success' => false,
				'error' => 'Search failed: ' . $e->getMessage(),
			], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * RECIPE — Récupérer une recette Marmiton complète
	 */
	#[Route('/recipe', name: 'recipe', methods: ['POST'])]
	public function recipe(Request $request): JsonResponse
	{
		try {
			$data = json_decode($request->getContent(), true);
			$link = $data['link'] ?? $data['url'] ?? null;

			if (!$link) {
				return new JsonResponse([
					'ok' => false,
					'error' => 'Missing "link" parameter',
				], Response::HTTP_BAD_REQUEST);
			}

			// Normalize Marmiton URL
			if (str_starts_with($link, '/')) {
				$link = 'https://www.marmiton.org' . $link;
			}

			$this->logger->info('Fetching Marmiton recipe', [
				'url' => $link
			]);

			// --- APPEL EXACT À LA BONNE MÉTHODE ---
			$recipe = $this->scraperService->fetchRecipe($link);

			// Toujours renvoyer un JSON propre unifié
			return new JsonResponse([
				'ok'     => true,
				'recipe' => $recipe,
				'source' => $link
			]);
		} catch (\Throwable $e) {

			$this->logger->error('Error fetching Marmiton recipe', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return new JsonResponse([
				'ok' => false,
				'error' => 'Failed to fetch recipe: ' . $e->getMessage(),
			], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * HEALTH CHECK
	 */
	#[Route('/health', name: 'health', methods: ['GET'])]
	public function health(): JsonResponse
	{
		return new JsonResponse([
			'ok' => true,
			'service' => 'marmiton-scraper',
			'php_version' => PHP_VERSION,
			'timestamp' => time(),
		]);
	}
}
