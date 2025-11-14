<?php

namespace App\Controller;

use App\Service\OpenFoodFactsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PictoSearchController extends AbstractController
{
	#[Route('/api/picto/search', name: 'picto_search', methods: ['GET'])]
	public function search(Request $request, OpenFoodFactsService $off): JsonResponse
	{
		$query = trim($request->query->get('q', ''));
		$limit = (int) $request->query->get('limit', 12);

		if ($query === '') {
			return $this->json([
				'success' => true,
				'results' => [],
			]);
		}

		$results = $off->searchByName($query, $limit);

		return $this->json([
			'success' => true,
			'results' => $results,
		]);
	}
}
