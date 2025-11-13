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
	 * Search for recipes on Marmiton
	 */
	#[Route('/search', name: 'search', methods: ['POST', 'GET'])]
	public function search(Request $request): JsonResponse
	{
		try {
			// Support both GET with query params and POST with JSON body
			if ($request->getMethod() === 'POST') {
				$data = json_decode($request->getContent(), true);
				$query = $data['q'] ?? $data['query'] ?? '';
				$limit = $data['limit'] ?? 20;
				$filters = $data['filters'] ?? [];
			} else {
				$query = $request->query->get('q') ?? $request->query->get('query') ?? '';
				$limit = (int) ($request->query->get('limit') ?? 20);
				$filters = [];

				// Extract filters from query params
				if ($request->query->get('withPhoto')) {
					$filters['withPhoto'] = true;
				}
			}

			if (empty($query)) {
				return new JsonResponse([
					'success' => false,
					'error' => 'Missing search term "q" or "query"',
				], Response::HTTP_BAD_REQUEST);
			}

			$this->logger->info('Marmiton search request', [
				'query' => $query,
				'limit' => $limit,
				'filters' => $filters,
			]);

			$results = $this->scraperService->searchRecipes($query, $limit, $filters);

			return new JsonResponse([
				'success' => true,
				'results' => $results,
				'count' => count($results),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error in Marmiton search', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return new JsonResponse([
				'success' => false,
				'error' => 'Failed to fetch recipes: ' . $e->getMessage(),
			], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Fetch a single recipe page
	 */
	#[Route('/recipe', name: 'recipe', methods: ['POST'])]
	public function recipe(Request $request): JsonResponse
	{
		try {
			$data = json_decode($request->getContent(), true);
			$link = $data['link'] ?? $data['url'] ?? null;
			$id = $data['id'] ?? null;

			if (!$link && !$id) {
				return new JsonResponse([
					'ok' => false,
					'error' => 'Missing link or id parameter',
				], Response::HTTP_BAD_REQUEST);
			}

			// Prefer link over id
			if (!$link && $id) {
				return new JsonResponse([
					'ok' => false,
					'error' => 'Please provide "link" (full recipe URL). Cannot reliably construct URL from id alone.',
				], Response::HTTP_BAD_REQUEST);
			}

			// Ensure absolute URL
			if (str_starts_with($link, '/')) {
				$link = 'https://www.marmiton.org' . $link;
			}

			$this->logger->info('Fetching Marmiton recipe', ['url' => $link]);

			$recipeData = $this->scraperService->fetchRecipe($link);

			return new JsonResponse($recipeData);
		} catch (\Exception $e) {
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
	 * Health check endpoint
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
