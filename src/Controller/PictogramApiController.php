<?php

namespace App\Controller;

use App\Service\ArasaacApiService;
use App\Repository\PictogramRepository;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
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
	public function search(Request $request, PictogramRepository $pictogramRepository): JsonResponse
	{
		$keyword = $request->query->get('q', '');

		if (empty(trim($keyword))) {
			return $this->json([
				'success' => false,
				'message' => 'Le mot-clé de recherche est requis',
				'results' => []
			], 400);
		}

		// Prepare aggregated results: local pictograms + ARASAAC
		$aggregated = [];

		// 1) Local pictograms matching the keyword (case-insensitive)
		$qb = $pictogramRepository->createQueryBuilder('p');
		$qb->andWhere($qb->expr()->like('LOWER(p.name)', ':kw'))
			->setParameter('kw', '%' . mb_strtolower($keyword) . '%')
			->setMaxResults(50);

		$locals = $qb->getQuery()->getResult();

		foreach ($locals as $local) {
			$aggregated[] = [
				'id' => 'local_' . $local->getId(),
				'name' => $local->getName(),
				'imageUrl' => '/' . ltrim($local->getFilePath(), '/'),
				'source' => 'local',
			];
		}

		// 2) ARASAAC results (best-effort). If ARASAAC fails, return only locals.
		try {
			$arasaac = $this->arasaacService->search($keyword);

			// annotate source and append
			foreach ($arasaac as $item) {
				$item['source'] = 'arasaac';
				$aggregated[] = $item;
			}
		} catch (\Exception $e) {
			// log and continue — we'll still return local results
			// (the service already logs internally)
		}

		return $this->json([
			'success' => true,
			'keyword' => $keyword,
			'count' => count($aggregated),
			'results' => $aggregated
		]);
	}
}
