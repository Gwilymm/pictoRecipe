<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CuisineazScraperService
{
	private const USER_AGENT = 'Mozilla/5.0 (compatible; PictoRecipe/1.0)';
	private const REQUEST_TIMEOUT = 30;

	public function __construct(
		private HttpClientInterface $http,
		private LoggerInterface $logger
	) {}

	/**
	 * Search for recipes on CuisineAZ
	 */
	public function searchRecipes(string $query, int $limit = 20, array $filters = []): array
	{
		$this->logger->info('Searching CuisineAZ', ['query' => $query, 'limit' => $limit, 'filters' => $filters]);

		$url = 'https://www.cuisineaz.com/recettes/recherche_terme.aspx?' . http_build_query([
			'recherche' => $query
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

		// Find results as article.tile.searchResult and extract title + image
		$crawler->filter('article.tile.searchResult, article.searchResult, article.tile')->each(function (Crawler $article) use (&$results, $limit) {
			if (count($results) >= $limit) return;

			$titleNode = $article->filter('.tile_title a')->first();
			if (!$titleNode->count()) return;

			$title = trim($titleNode->text());
			// Remove URI 'Recette : ' prefix if present
			$title = preg_replace('/^Recette\s*:\s*/iu', '', $title);

			$href = $titleNode->attr('href') ?: '';
			if ($href === '') return;
			$link = $href;
			if (str_starts_with($link, '/')) {
				$link = 'https://www.cuisineaz.com' . $link;
			}

			$image = '';
			$imgNode = $article->filter('.tile_thumbnail img')->first();
			if ($imgNode->count()) {
				$image = $imgNode->attr('src') ?: $imgNode->attr('data-src') ?: $imgNode->attr('data-srcset') ?: '';
			}

			if ($title === '' && $image === '') return;

			$results[] = [
				'title' => $title ?: basename($link),
				'name' => $title ?: basename($link),
				'link' => $link,
				'url' => $link,
				'image' => $image,
				'picture' => $image,
				'source' => 'cuisineaz',
			];
		});

		// Deduplicate by url
		$unique = [];
		$final = [];
		foreach ($results as $r) {
			if (isset($unique[$r['url']])) continue;
			$unique[$r['url']] = true;
			$final[] = $r;
			if (count($final) >= $limit) break;
		}

		return $final;
	}

	/**
	 * Fetch a CuisineAZ recipe and return a unified JSON object
	 */
	public function fetchRecipe(string $url): array
	{
		$this->logger->info('Fetching CuisineAZ recipe', ['url' => $url]);

		$response = $this->http->request('GET', $url, [
			'headers' => [
				'User-Agent' => self::USER_AGENT,
				'Accept-Language' => 'fr-FR,fr;q=0.9'
			],
			'timeout' => self::REQUEST_TIMEOUT
		]);

		$html = $response->getContent();
		$crawler = new Crawler($html);

		// Prefer JSON-LD recipe if present
		$script = $crawler->filter('script[type="application/ld+json"]')->first();
		if ($script->count()) {
			$text = trim($script->text());
			$decoded = json_decode($text, true);
			if (is_array($decoded)) {
				// If it's an array, find the recipe object
				if (isset($decoded['@type']) && strtolower($decoded['@type']) === 'recipe') {
					$recipeObj = $decoded;
				} else {
					$recipeObj = null;
					foreach ($decoded as $d) {
						if (is_array($d) && isset($d['@type']) && strtolower($d['@type']) === 'recipe') {
							$recipeObj = $d;
							break;
						}
					}
				}

				if (!empty($recipeObj) && is_array($recipeObj)) {
					$title = $recipeObj['name'] ?? '';
					$ingredients = [];
					foreach (($recipeObj['recipeIngredient'] ?? []) as $ing) {
						$ingredients[] = ['group' => null, 'name' => $ing, 'quantity' => '', 'unit' => '', 'complement' => ''];
					}

					// Prefer JSON-LD description, otherwise fallback to DOM
					$description = $recipeObj['description'] ?? null;
					if (empty($description)) {
						$descNode = $crawler->filter('section.recipe_description #editorial_container p')->first();
						if ($descNode->count()) $description = trim($descNode->text());
					}

					// Try to extract author & published date
					$author = null;
					$published = null;
					$authorNode = $crawler->filter('section.recipe_description .recipe_author_container a.bold')->first();
					if ($authorNode->count()) $author = trim($authorNode->text());
					$pubNode = $crawler->filter('section.recipe_description .recipe_author_container p.recipe_author_p')->last();
					if ($pubNode->count()) $published = trim($pubNode->text());

					$steps = [];
					if (!empty($recipeObj['recipeInstructions']) && is_array($recipeObj['recipeInstructions'])) {
						foreach ($recipeObj['recipeInstructions'] as $idx => $ins) {
							if (is_array($ins) && isset($ins['text'])) {
								$text = trim($ins['text']);
							} else {
								$text = is_string($ins) ? trim($ins) : '';
							}
							if ($text !== '') $steps[] = ['number' => $idx + 1, 'text' => $text];
						}
					}
					// If steps came from JSON-LD, ensure the 'number' field is a readable label like 'Étape 1'
					foreach ($steps as $i => $s) {
						if (empty($s['number']) || is_int($s['number'])) {
							$steps[$i]['number'] = 'Étape ' . ($i + 1);
						}
					}

					$times = ['total' => $recipeObj['totalTime'] ?? null, 'details' => []];
					if (!empty($recipeObj['prepTime'])) $times['details'][] = ['label' => 'Préparation', 'value' => $recipeObj['prepTime']];
					if (!empty($recipeObj['cookTime'])) $times['details'][] = ['label' => 'Cuisson', 'value' => $recipeObj['cookTime']];

					// Try to collect extra info from DOM if available
					$difficulty = null;
					$kcal = null;
					$budget = null;
					$servingsDom = null;

					$utilsRoot = $crawler->filter('section.recipe_informations_container .recipe_utils_informations_container')->first();
					if ($utilsRoot->count()) {
						$utilsRoot->filter('.recipe_utils_information_container')->each(function (Crawler $ti) use (&$difficulty, &$kcal, &$budget, &$servingsDom) {
							// Difficulty
							if ($ti->filter('span.svg_hat')->count()) {
								$p = $ti->filter('p.recipe_utils_information')->first();
								if ($p->count()) $difficulty = trim($p->text());
							}
							// Servings
							if ($ti->filter('span.svg_user_filled')->count()) {
								$p = $ti->filter('p.recipe_utils_information')->first();
								if ($p->count()) {
									if (preg_match('/(\d+)/u', trim($p->text()), $m)) $servingsDom = (int)$m[1];
								}
							}
							// Budget
							if ($ti->filter('span.svg_price')->count()) {
								$p = $ti->filter('p.recipe_utils_information')->first();
								if ($p->count()) $budget = trim($p->text());
							}
							// Kcal
							if ($ti->filter('span.svg_kcal')->count()) {
								$p = $ti->filter('p.recipe_utils_information')->first();
								if ($p->count() && preg_match('/(\d+)/u', trim($p->text()), $m)) $kcal = (int)$m[1];
							}
						});
					}

					$primary = [];
					if (!empty($difficulty)) $primary[] = $difficulty;
					if (!empty($budget)) $primary[] = $budget;
					if (!empty($kcal)) $primary[] = $kcal . ' kcal';

					return [
						'ok' => true,
						'title' => $title,
						'primary' => $primary,
						'description' => $description ?? null,
						'author' => $author,
						'published' => $published,
						'times' => $times,
						'servings' => $recipeObj['recipeYield'] ?? $servingsDom ?? null,
						'ingredients' => $ingredients,
						'utensils' => [],
						'steps' => $steps,
						'difficulty' => $difficulty,
						'kcal' => $kcal,
						'budget' => $budget,
					];
				}
			}
		}

		// Fallback to DOM extraction
		$title = '';
		$h1 = $crawler->filter('h1')->first();
		if ($h1->count()) $title = trim($h1->text());

		$ingredients = [];
		// try common ingredient selectors
		// CuisineAZ: ingredient_list li.ingredient_item -> label + quantity
		$crawler->filter('section.recipe_section.ingredients ul.ingredient_list li.ingredient_item')->each(function (Crawler $n) use (&$ingredients) {
			$nameNode = $n->filter('.ingredient_label')->first();
			$qtyNode = $n->filter('.js-ingredient-qte')->first();
			if (!$nameNode->count()) return;
			$name = trim($nameNode->text());
			$qty = $qtyNode->count() ? trim($qtyNode->text()) : '';
			$imgNode = $n->filter('img.ingredient_img')->first();
			$image = $imgNode->count() ? ($imgNode->attr('src') ?: $imgNode->attr('data-src') ?: '') : '';
			$ingredients[] = ['group' => null, 'name' => $name, 'quantity' => $qty, 'unit' => '', 'complement' => '', 'image' => $image];
		});

		$steps = [];
		// CuisineAZ : preparation_steps li.preparation_step with title h3.recipe_section_h3 and paragraph
		$crawler->filter('ul.preparation_steps li.preparation_step')->each(function (Crawler $n) use (&$steps) {
			// title (Étape 1)
			$titleNode = $n->filter('.preparation_step_title_container h3.recipe_section_h3')->first();
			$titleText = $titleNode->count() ? trim($titleNode->text()) : null;
			$p = $n->filter('p')->first();
			$text = $p->count() ? trim(preg_replace('/\s+/', ' ', $p->text())) : '';
			if ($text !== '') {
				$steps[] = ['number' => $titleText ?: 'Étape ' . (count($steps) + 1), 'text' => $text];
			}
		});
		// Fallback: older or different selectors
		if (count($steps) === 0) {
			$crawler->filter('.preparation-step, .instructions li, .recipe-steps li, .method li')->each(function (Crawler $n) use (&$steps) {
				$text = trim($n->text());
				if ($text !== '') $steps[] = ['number' => 'Étape ' . (count($steps) + 1), 'text' => $text];
			});
		}

		$times = ['total' => null, 'details' => []];
		// CuisineAZ specific: time blocks in the recipe informations section
		$timeRoot = $crawler->filter('section.primary_background.recipe_informations_container .recipe_time_informations_container')->first();
		if ($timeRoot->count()) {
			$timeRoot->filter('.recipe_time_information_container')->each(function (Crawler $ti) use (&$times) {
				$labelNode = $ti->filter('.recipe_time_information_title')->first();
				$valueNode = $ti->filter('.recipe_time_information')->first();
				$label = $labelNode->count() ? trim($labelNode->text()) : '';
				$value = $valueNode->count() ? trim($valueNode->text()) : '';
				if ($value === '') return;
				if (preg_match('/total/i', $label)) {
					$times['total'] = $value;
				} else {
					$times['details'][] = ['label' => $label ?: 'Temps', 'value' => $value];
				}
			});
		} else {
			$crawler->filter('.time, .recipe-infos__time, .duration')->each(function (Crawler $n) use (&$times) {
				$t = trim($n->text());
				if ($t !== '') $times['details'][] = ['label' => 'Temps', 'value' => $t];
			});
		}

		// Parse utils block for difficulty, servings, budget, kcal
		$difficulty = null;
		$kcal = null;
		$budget = null;
		$servingsVal = null;
		$utilsRoot = $crawler->filter('section.recipe_informations_container .recipe_utils_informations_container')->first();
		if ($utilsRoot->count()) {
			$utilsRoot->filter('.recipe_utils_information_container')->each(function (Crawler $ti) use (&$difficulty, &$kcal, &$budget, &$servingsVal) {
				if ($ti->filter('span.svg_hat')->count()) {
					$p = $ti->filter('p.recipe_utils_information')->first();
					if ($p->count()) $difficulty = trim($p->text());
				}
				if ($ti->filter('span.svg_user_filled')->count()) {
					$p = $ti->filter('p.recipe_utils_information')->first();
					if ($p->count() && preg_match('/(\d+)/u', trim($p->text()), $m)) $servingsVal = (int)$m[1];
				}
				if ($ti->filter('span.svg_price')->count()) {
					$p = $ti->filter('p.recipe_utils_information')->first();
					if ($p->count()) $budget = trim($p->text());
				}
				if ($ti->filter('span.svg_kcal')->count()) {
					$p = $ti->filter('p.recipe_utils_information')->first();
					if ($p->count() && preg_match('/(\d+)/u', trim($p->text()), $m)) $kcal = (int)$m[1];
				}
			});
		}

		$primary = [];
		if (!empty($difficulty)) $primary[] = $difficulty;
		if (!empty($budget)) $primary[] = $budget;
		if (!empty($kcal)) $primary[] = $kcal . ' kcal';

		// description
		$description = null;
		$descNode = $crawler->filter('section.recipe_description #editorial_container p')->first();
		if ($descNode->count()) $description = trim($descNode->text());

		// author & published date
		$author = null;
		$published = null;
		$authorNode = $crawler->filter('section.recipe_description .recipe_author_container a.bold')->first();
		if ($authorNode->count()) $author = trim($authorNode->text());
		$pubNode = $crawler->filter('section.recipe_description .recipe_author_container p.recipe_author_p')->last();
		if ($pubNode->count()) $published = trim($pubNode->text());

		return [
			'ok' => true,
			'title' => $title,
			'primary' => $primary,
			'description' => $description ?? null,
			'times' => $times,
			'servings' => $servingsVal ?? null,
			'ingredients' => $ingredients,
			'utensils' => [],
			'steps' => $steps,
			'difficulty' => $difficulty,
			'kcal' => $kcal,
			'budget' => $budget,
		];
	}
}
