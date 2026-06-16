<?php

namespace App\Controller;

use App\Service\OpenFoodFactsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PictoSearchController extends AbstractController
{
	#[Route('/api/picto/search', name: 'picto_search', methods: ['GET'])]
	public function search(
		Request $request,
		OpenFoodFactsService $off,
		#[Autowire(service: 'monolog.logger')]
		LoggerInterface $logger
	): JsonResponse
	{
		$t0 = microtime(true);
		$query = trim($request->query->get('q', ''));
		$limit = (int) $request->query->get('limit', 12);
		$brand = $request->query->get('brand', null);
		$baseContext = [
			'request_id' => $request->attributes->get('request_id'),
			'route' => $request->attributes->get('_route'),
			'method' => $request->getMethod(),
			'source' => 'openfoodfacts',
			'keyword' => $query,
			'limit' => $limit,
			'brand_filter_present' => is_string($brand) && trim($brand) !== '',
		];

		$logger->info('pictogram.search.requested', $baseContext);

		if ($query === '') {
			$logger->info('pictogram.search.no_result', array_merge($baseContext, [
				'reason' => 'empty_keyword',
				'result_count' => 0,
				'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
				'status' => Response::HTTP_OK,
			]));

			return $this->json([
				'success' => true,
				'results' => [],
			]);
		}

		$results = $off->searchByName($query, $limit, $brand);
		$event = count($results) > 0 ? 'pictogram.search.completed' : 'pictogram.search.no_result';
		$logger->info($event, array_merge($baseContext, [
			'result_count' => count($results),
			'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
			'status' => Response::HTTP_OK,
		]));

		return $this->json([
			'success' => true,
			'results' => $results,
		]);
	}
}
