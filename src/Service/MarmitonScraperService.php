<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MarmitonScraperService
{
	private const USER_AGENT = 'Mozilla/5.0 (compatible; PictoRecipe/1.0)';
	private const REQUEST_TIMEOUT = 30;

	public function __construct(
		private HttpClientInterface $http,
		private LoggerInterface $logger
	) {}
	/**
	 * Search for recipes on Marmiton
	 */
	public function searchRecipes(string $query, int $limit = 20, array $filters = []): array
	{
		$this->logger->info('Searching Marmiton', [
			'query' => $query,
			'limit' => $limit,
			'filters' => $filters
		]);

		$url = 'https://www.marmiton.org/recettes/recherche.aspx?' . http_build_query([
			'aqt' => $query
		]);

		$response = $this->http->request('GET', $url, [
			'headers' => [
				'User-Agent' => self::USER_AGENT,
				'Accept-Language' => 'fr-FR,fr;q=0.9'
			],
			'timeout' => self::REQUEST_TIMEOUT
		]);

		$html = $response->getContent();
		$crawler = new Crawler($html);

		$results = [];

		$crawler->filter('ul.search-list li.search-list__item')->each(
			function (Crawler $node) use (&$results, $limit) {

				if (count($results) >= $limit) return;

				$titleNode = $node->filter('a.card-content__title')->first();
				if (!$titleNode->count()) return;

				$title = trim($titleNode->text());
				$link  = $titleNode->attr('href');

				if (str_starts_with($link, '/')) {
					$link = 'https://www.marmiton.org' . $link;
				}

				$imageNode = $node->filter('img')->first();
				$image = $imageNode->count()
					? ($imageNode->attr('src') ?: $imageNode->attr('data-src') ?: '')
					: '';

				$rating = $node->filter('.rating__rating')->count()
					? trim($node->filter('.rating__rating')->text())
					: null;

				$reviews = $node->filter('.rating__nbreviews')->count()
					? trim($node->filter('.rating__nbreviews')->text())
					: null;

				$category = $node->filter('.image-label')->count()
					? trim($node->filter('.image-label')->text())
					: null;

				$results[] = [
					'title' => $title,
					'name'  => $title,
					'link'  => $link,
					'url'   => $link,
					'image' => $image,
					'picture' => $image,
					'category' => $category,
					'rating'   => $rating,
					'reviews'  => $reviews,
				];
			}
		);

		return $results;
	}

	/**
	 * Fetch a Marmiton recipe and return a clean JSON object
	 */
	public function fetchRecipe(string $url): array
	{
		$this->logger->info('Fetching Marmiton recipe', ['url' => $url]);

		$response = $this->http->request('GET', $url, [
			'headers' => [
				'User-Agent' => self::USER_AGENT,
				'Accept-Language' => 'fr-FR,fr;q=0.9',
			],
			'timeout' => self::REQUEST_TIMEOUT,
		]);

		$html = $response->getContent();
		$crawler = new Crawler($html);

		$data = [
			'ok' => true,
			'title' => $this->extractTitle($crawler),
			'primary' => $this->extractPrimary($crawler),
			'times' => $this->extractTimes($crawler),
			'ingredients' => $this->extractIngredients($crawler),
			'utensils' => $this->extractUtensils($crawler),
			'steps' => $this->extractSteps($crawler),
		];

		return $data;
	}

	/* -----------------------------------------
	   EXTRACTION : TITRE
	----------------------------------------- */
	private function extractTitle(Crawler $crawler): string
	{
		foreach (['.mrtn-recette_title', '.recipe-title', 'h1'] as $selector) {
			$node = $crawler->filter($selector)->first();
			if ($node->count()) {
				return trim($node->text());
			}
		}
		return '';
	}

	/* -----------------------------------------
	   EXTRACTION : INFORMATIONS PRINCIPALES
	----------------------------------------- */
	private function extractPrimary(Crawler $crawler): array
	{
		$data = [];

		$primary = $crawler->filter('.recipe-primary');
		if ($primary->count()) {
			$primary->filter('.recipe-primary__item')->each(function (Crawler $item) use (&$data) {
				$text = trim($item->filter('span')->text());
				$data[] = $text;
			});
		}

		return $data;
	}

	/* -----------------------------------------
	   EXTRACTION : TEMPS (total + détails)
	----------------------------------------- */
	private function extractTimes(Crawler $crawler): array
	{
		$result = [
			'total' => null,
			'details' => []
		];

		$timeRoot = $crawler->filter('.recipe-preparation__time')->first();
		if (!$timeRoot->count()) return $result;

		// Temps total
		$totalNode = $timeRoot->filter('.time__total div')->first();
		if ($totalNode->count()) {
			$result['total'] = trim($totalNode->text());
		}

		// Détails
		$timeRoot->filter('.time__details > div')->each(function (Crawler $div) use (&$result) {
			$label = trim(str_replace(':', '', $div->filter('span')->text()));
			$value = trim($div->filter('div')->text());
			$result['details'][] = ['label' => $label, 'value' => $value];
		});

		return $result;
	}

	/* -----------------------------------------
	   EXTRACTION : INGREDIENTS
	----------------------------------------- */
	private function extractIngredients(Crawler $crawler): array
	{
		$result = [];

		$root = $crawler->filter('.mrtn-recette_ingredients-items')->first();
		if (!$root->count()) return $result;

		$currentGroup = null;

		foreach ($root->children() as $child) {
			$item = new Crawler($child);

			// Groupe (ex : "Pour la pâte sablée")
			if ($item->attr('class') === 'mrtn-recette_ingredients-items-group-title') {
				$currentGroup = trim($item->text());
				continue;
			}

			// Carte ingrédient
			if ($item->attr('class') === 'card-ingredient') {

				$name = $item->filter('.ingredient-name')->count()
					? trim($item->filter('.ingredient-name')->text())
					: '';

				$qty = $item->filter('.card-ingredient-quantity .count')->count()
					? trim($item->filter('.card-ingredient-quantity .count')->text())
					: '';

				$unit = $item->filter('.card-ingredient-quantity .unit')->count()
					? trim($item->filter('.card-ingredient-quantity .unit')->text())
					: '';

				$complement = $item->filter('.ingredient-complement')->count()
					? trim($item->filter('.ingredient-complement')->text())
					: '';

				$result[] = [
					'group' => $currentGroup,
					'name' => $name,
					'quantity' => $qty,
					'unit' => $unit,
					'complement' => $complement,
				];
			}
		}

		return $result;
	}

	/* -----------------------------------------
	   EXTRACTION : USTENSILES
	----------------------------------------- */
	private function extractUtensils(Crawler $crawler): array
	{
		$result = [];

		$crawler->filter('.mrtn-recette_utensils .card-utensil')->each(function (Crawler $node) use (&$result) {

			$quantity = $node->filter('.card-utensil-quantity')->count()
				? trim($node->filter('.card-utensil-quantity')->text())
				: '';

			$name = $node->attr('data-name') ?: '';

			$result[] = [
				'name' => $name,
				'quantity' => $quantity,
			];
		});

		return $result;
	}

	/* -----------------------------------------
	   EXTRACTION : ÉTAPES DE PRÉPARATION
	----------------------------------------- */
	private function extractSteps(Crawler $crawler): array
	{
		$result = [];

		$steps = $crawler->filter('.recipe-step-list .recipe-step-list__container');
		if (!$steps->count()) {
			return $result;
		}

		$steps->each(function (Crawler $stepNode) use (&$result) {

			$number = $stepNode->filter('.recipe-step-list__head span')->count()
				? trim($stepNode->filter('.recipe-step-list__head span')->text())
				: null;

			$text = $stepNode->filter('p')->count()
				? trim(preg_replace('/\s+/', ' ', $stepNode->filter('p')->text()))
				: '';

			if ($text) {
				$result[] = [
					'number' => $number,
					'text' => $text,
				];
			}
		});

		return $result;
	}
}
