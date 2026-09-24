<?php

namespace App\Controller;

use App\Service\PapillesEtPupillesScraperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/papillesetpupilles', name: 'api_papillesetpupilles_')]
final class PapillesEtPupillesApiController extends AbstractController
{
	public function __construct(private PapillesEtPupillesScraperService $scraper) {}

	#[Route('/search', name: 'search', methods: ['GET', 'POST'])]
	public function search(Request $request): JsonResponse
	{
		$body = $request->getMethod() === 'POST' ? (json_decode($request->getContent(), true) ?: []) : $request->query->all();
		$query = trim((string) ($body['q'] ?? $body['query'] ?? ''));
		if ($query === '') return new JsonResponse(['success' => false, 'error' => 'Missing search term (q or query)'], Response::HTTP_BAD_REQUEST);
		$results = $this->scraper->searchRecipes($query, max(1, (int) ($body['limit'] ?? 20)));
		return new JsonResponse(['success' => true, 'results' => $results, 'count' => count($results)]);
	}

	#[Route('/recipe', name: 'recipe', methods: ['POST'])]
	public function recipe(Request $request): JsonResponse
	{
		$body = json_decode($request->getContent(), true) ?: [];
		$url = $body['url'] ?? $body['link'] ?? null;
		if (!$url) return new JsonResponse(['ok' => false, 'error' => 'Missing "url" parameter'], Response::HTTP_BAD_REQUEST);
		return new JsonResponse(['ok' => true, 'recipe' => $this->scraper->fetchRecipe((string) $url), 'source' => $url]);
	}
}
