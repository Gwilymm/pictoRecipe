<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ArasaacApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur principal de l'application de recherche de pictogrammes
 */
class HomeController extends AbstractController
{
	public function __construct(
		private readonly ArasaacApiService $arasaacApiService
	) {}

	/**
	 * Page d'accueil avec recherche de pictogrammes
	 * 
	 * @param Request $request
	 * @return Response
	 */
	#[Route('/', name: 'app_home', methods: ['GET'])]
	public function index(Request $request): Response
	{
		$keyword = $request->query->get('q', '');
		$pictograms = [];
		$error = null;
		$searched = false;

		// Si un mot-clé est fourni, effectuer la recherche
		if (!empty(trim($keyword))) {
			$searched = true;
			try {
				$pictograms = $this->arasaacApiService->search($keyword);
			} catch (\Exception $e) {
				$error = $e->getMessage();
			}
		}

		return $this->render('home/index.html.twig', [
			'keyword' => $keyword,
			'pictograms' => $pictograms,
			'error' => $error,
			'searched' => $searched,
		]);
	}
}
