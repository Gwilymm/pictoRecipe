<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MarmitonScraperService
{
	private const BASE_URL = 'https://www.marmiton.org';
	private const SEARCH_URL = 'https://www.marmiton.org/recettes/recherche.aspx';
	private const USER_AGENT = 'Mozilla/5.0 (compatible; PictoRecipe/1.0)';
	private const CACHE_TTL = 1800; // 30 minutes
	private const REQUEST_TIMEOUT = 30;

	public function __construct(
		private HttpClientInterface $httpClient,
		private CacheInterface $cache,
		private LoggerInterface $logger
	) {}

	/**
	 * Search for recipes on Marmiton
	 *
	 * @param string $query Search term
	 * @param int $limit Maximum number of results
	 * @param array $filters Additional filters (withPhoto, vegetarian, etc.)
	 * @return array List of recipes
	 */
	public function searchRecipes(string $query, int $limit = 20, array $filters = []): array
	{
		$cacheKey = 'marmiton_search_' . md5($query . json_encode($filters) . $limit);

		return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $limit, $filters) {
			$item->expiresAfter(self::CACHE_TTL);

			try {
				$params = ['aqt' => $query];

				// Apply filters
				if (!empty($filters['withPhoto'])) {
					$params['pht'] = '1';
				}
				// Add more filter mappings as needed

				$url = self::SEARCH_URL . '?' . http_build_query($params);
				$this->logger->info('Fetching Marmiton search', ['url' => $url]);

				$response = $this->httpClient->request('GET', $url, [
					'headers' => [
						'User-Agent' => self::USER_AGENT,
						'Accept-Language' => 'fr-FR,fr;q=0.9',
					],
					'timeout' => self::REQUEST_TIMEOUT,
				]);

				$html = $response->getContent();
				return $this->parseSearchResults($html, $limit);
			} catch (\Exception $e) {
				$this->logger->error('Error fetching Marmiton search', [
					'error' => $e->getMessage(),
					'query' => $query,
				]);
				throw $e;
			}
		});
	}

	/**
	 * Parse search results HTML
	 */
	private function parseSearchResults(string $html, int $limit): array
	{
		$crawler = new Crawler($html);
		$recipes = [];

		$crawler->filter('ul.search-list li.search-list__item')->each(function (Crawler $node, $i) use (&$recipes, $limit) {
			if (count($recipes) >= $limit) {
				return;
			}

			try {
				$titleLink = $node->filter('a.card-content__title')->first();
				$name = $titleLink->count() ? trim($titleLink->text()) : '';
				$link = $titleLink->count() ? $titleLink->attr('href') : '';

				if ($link && str_starts_with($link, '/')) {
					$link = self::BASE_URL . $link;
				}

				// Extract image
				$imageNode = $node->filter('img')->first();
				$image = '';
				if ($imageNode->count()) {
					$image = $imageNode->attr('src') ?: $imageNode->attr('data-src') ?: '';
				}

				// Extract metadata
				$category = $node->filter('.image-label')->count()
					? trim($node->filter('.image-label')->first()->text())
					: '';

				$rating = $node->filter('.rating__rating')->count()
					? trim($node->filter('.rating__rating')->first()->text())
					: '';

				$reviews = $node->filter('.rating__nbreviews')->count()
					? trim($node->filter('.rating__nbreviews')->first()->text())
					: '';

				$recipes[] = [
					'name' => $name,
					'title' => $name,
					'link' => $link,
					'url' => $link,
					'image' => $image,
					'picture' => $image,
					'category' => $category,
					'rating' => $rating,
					'reviews' => $reviews,
					'position' => $i + 1,
				];
			} catch (\Exception $e) {
				$this->logger->warning('Error parsing recipe item', ['error' => $e->getMessage()]);
			}
		});

		$this->logger->info('Parsed Marmiton search results', ['count' => count($recipes)]);
		return $recipes;
	}

	/**
	 * Fetch a single recipe page
	 *
	 * @param string $url Recipe URL
	 * @return array Recipe data with HTML content
	 */
	public function fetchRecipe(string $url): array
	{
		$cacheKey = 'marmiton_recipe_' . md5($url);

		return $this->cache->get($cacheKey, function (ItemInterface $item) use ($url) {
			$item->expiresAfter(self::CACHE_TTL);

			try {
				$this->logger->info('Fetching Marmiton recipe', ['url' => $url]);

				$response = $this->httpClient->request('GET', $url, [
					'headers' => [
						'User-Agent' => self::USER_AGENT,
						'Accept-Language' => 'fr-FR,fr;q=0.9',
					],
					'timeout' => self::REQUEST_TIMEOUT,
				]);

				$html = $response->getContent();
				return $this->extractRecipeFragments($html);
			} catch (\Exception $e) {
				$this->logger->error('Error fetching Marmiton recipe', [
					'error' => $e->getMessage(),
					'url' => $url,
				]);
				throw $e;
			}
		});
	}

	/**
	 * Extract relevant fragments from recipe HTML
	 */
	private function extractRecipeFragments(string $html): array
	{
		$crawler = new Crawler($html);
		$fragments = [];

		// Extract main sections
		$selectors = [
			'title' => ['.mrtn-recette_title', '.recipe-title', 'h1'],
			'primary' => ['.recipe-primary', '.marmiton-extract'],
			'ingredients' => ['.mrtn-recette_ingredients', '.recipe-ingredients'],
			'utensils' => ['.mrtn-recette_utensils', '.recipe-utensils'],
			'preparation' => ['.recipe-preparation', '.recipe-step-list'],
		];

		foreach ($selectors as $key => $selectorList) {
			foreach ($selectorList as $selector) {
				try {
					$node = $crawler->filter($selector)->first();
					if ($node->count() > 0) {
						if ($key === 'title') {
							$fragments[$key] = trim($node->text());
						} else {
							$fragments[$key] = $node->outerHtml();
						}
						break;
					}
				} catch (\Exception $e) {
					// Try next selector
				}
			}
		}

		// If we found fragments, combine them
		if (!empty($fragments)) {
			$combinedHtml = '<div class="marmiton-extract">';

			if (!empty($fragments['title'])) {
				$combinedHtml .= '<h2>' . htmlspecialchars($fragments['title']) . '</h2>';
			}

			foreach (['primary', 'ingredients', 'utensils', 'preparation'] as $section) {
				if (!empty($fragments[$section])) {
					$combinedHtml .= $fragments[$section];
				}
			}

			$combinedHtml .= '</div>';

			return [
				'ok' => true,
				'html' => $this->sanitizeHtml($combinedHtml),
				'fragments' => $fragments,
			];
		}

		// Fallback: return sanitized full HTML
		return [
			'ok' => true,
			'html' => $this->sanitizeHtml($html),
		];
	}

	/**
	 * Basic HTML sanitization (remove scripts, dangerous attributes)
	 */
	private function sanitizeHtml(string $html): string
	{
		// Remove script tags
		$html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

		// Remove event handlers
		$html = preg_replace('/\son\w+\s*=\s*["\'].*?["\']/i', '', $html);

		// You can add more sophisticated sanitization here
		// Consider using a library like HTML Purifier for production

		return $html;
	}
}
