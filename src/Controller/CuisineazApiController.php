<?php

namespace App\Controller;

use App\Service\CuisineazScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cuisineaz', name: 'api_cuisineaz_')]
class CuisineazApiController extends AbstractController
{
	public function __construct(
		private CuisineazScraperService $scraperService,
		private LoggerInterface $logger
	) {}

	#[Route('/search', name: 'search', methods: ['POST', 'GET'])]
	public function search(Request $request): JsonResponse
	{
		try {
			if ($request->getMethod() === 'POST') {
				$body = json_decode($request->getContent(), true);
				$query = $body['q'] ?? $body['query'] ?? '';
				$limit = (int)($body['limit'] ?? 20);
				$filters = $body['filters'] ?? [];
			} else {
				$query = $request->query->get('q') ?? $request->query->get('query') ?? '';
				$limit = (int)($request->query->get('limit') ?? 20);
				$filters = [];
			}

			if ($query === '') {
				return new JsonResponse(['success' => false, 'error' => 'Missing search term (q or query)'], Response::HTTP_BAD_REQUEST);
			}

			$this->logger->info('CuisineAZ search request', ['query' => $query, 'limit' => $limit]);

			$results = $this->scraperService->searchRecipes($query, $limit, $filters);

			return new JsonResponse(['success' => true, 'results' => $results, 'count' => count($results)]);
		} catch (\Throwable $e) {
			$this->logger->error('Error in CuisineAZ search', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
			return new JsonResponse(['success' => false, 'error' => 'Search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	#[Route('/recipe', name: 'recipe', methods: ['POST'])]
	public function recipe(Request $request): JsonResponse
	{
		try {
			$data = json_decode($request->getContent(), true);
			$link = $data['link'] ?? $data['url'] ?? null;
			if (!$link) {
				return new JsonResponse(['ok' => false, 'error' => 'Missing "link" parameter'], Response::HTTP_BAD_REQUEST);
			}

			if (str_starts_with($link, '/')) {
				$link = 'https://www.cuisineaz.com' . $link;
			}

			$this->logger->info('Fetching CuisineAZ recipe', ['url' => $link]);

			$recipe = $this->scraperService->fetchRecipe($link);

			return new JsonResponse(['ok' => true, 'recipe' => $recipe, 'source' => $link]);
		} catch (\Throwable $e) {
			$this->logger->error('Error fetching CuisineAZ recipe', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
			return new JsonResponse(['ok' => false, 'error' => 'Failed to fetch recipe: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
}
