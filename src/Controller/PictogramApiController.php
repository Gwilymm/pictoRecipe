<?php

namespace App\Controller;

use App\Service\ArasaacApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Controller pour la recherche de pictogrammes ARASAAC
 */
class PictogramApiController extends AbstractController
{
	public function __construct(
		private readonly ArasaacApiService $arasaacService
	) {}

	/**
	 * Recherche de pictogrammes via l'API ARASAAC
	 * 
	 * @param Request $request
	 * @return JsonResponse
	 */
	#[Route('/api/pictograms/search', name: 'api_pictogram_search', methods: ['GET'])]
	public function search(Request $request): JsonResponse
	{
		$keyword = $request->query->get('q', '');

		if (empty(trim($keyword))) {
			return $this->json([
				'success' => false,
				'message' => 'Le mot-clé de recherche est requis',
				'results' => []
			], 400);
		}

		try {
			$results = $this->arasaacService->search($keyword);

			return $this->json([
				'success' => true,
				'keyword' => $keyword,
				'count' => count($results),
				'results' => $results
			]);
		} catch (\Exception $e) {
			return $this->json([
				'success' => false,
				'message' => 'Erreur lors de la recherche : ' . $e->getMessage(),
				'results' => []
			], 500);
		}
	}
}
