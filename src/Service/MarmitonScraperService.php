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

				// If the image label explicitly says 'Sponsorisé' (or similar), skip it
				if ($category && preg_match('/sponsor|sponsoris(e|é)|sponso/i', $category)) {
					return;
				}

				// Skip sponsored / promoted results: try heuristics based on common markers
				$sponsored = false;
				$adSelectors = ['.ad', '.sponsored', '.search-list__ad', '.tile-ad', '.card--sponsored', '.search-list__item--sponsored', '.tile-promo', '.tile_promoted', '.card__sponsored', '.advert', '.result-ad'];
				foreach ($adSelectors as $sel) {
					try {
						if ($node->filter($sel)->count()) {
							$sponsored = true;
							break;
						}
					} catch (\Exception $e) {
						// ignore filter errors for odd selectors
					}
				}

				// Additionally check common sponsored words in the node text
				if (!$sponsored) {
					$text = mb_strtolower(trim($node->text()));
					if (preg_match('/sponsor|sponsorisé|sponso|publicit|promotion|promo|partenaire/i', $text)) {
						$sponsored = true;
					}
				}

				if ($sponsored) {
					// skip this item
					return;
				}

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
					'source' => 'marmiton',
				];
			}
		);

		return $results;
	}

	/**
	 * Convert ISO 8601 duration (e.g. PT1H30M, PT20M) to a human readable string like "1 h 30 min" or "20 min".
	 */
	private function isoDurationToReadable(string $iso): string
	{
		$iso = trim($iso);
		if ($iso === '') return '';

		// Try to parse patterns like PT1H30M, PT20M, PT1H
		$h = 0;
		$m = 0;
		if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/u', $iso, $mch)) {
			if (!empty($mch[1])) $h = (int) $mch[1];
			if (!empty($mch[2])) $m = (int) $mch[2];
		} else {
			// fallback: extract numbers
			if (preg_match('/(\d+)\s*h/u', $iso, $mh)) $h = (int) $mh[1];
			if (preg_match('/(\d+)\s*min/u', $iso, $mm)) $m = (int) $mm[1];
		}

		if ($h > 0 && $m > 0) return sprintf('%d h %d min', $h, $m);
		if ($h > 0) return sprintf('%d h', $h);
		if ($m > 0) return sprintf('%d min', $m);
		return '';
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
			'servings' => $this->extractServings($crawler),
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

		// First: try to extract times from JSON-LD (schema.org Recipe) or JS data
		$scriptTexts = [];
		$crawler->filter('script')->each(function (Crawler $s) use (&$scriptTexts) {
			$text = trim($s->text());
			if ($text !== '') $scriptTexts[] = $text;
		});

		foreach ($scriptTexts as $scriptText) {
			// JSON-LD block with ISO durations
			if (str_contains($scriptText, 'prepTime') || str_contains($scriptText, 'cookTime') || str_contains($scriptText, 'totalTime')) {
				// try to extract JSON blocks
				if (preg_match_all('/(\{.*?\})/s', $scriptText, $m)) {
					foreach ($m[1] as $jsonBlock) {
						$decoded = json_decode($jsonBlock, true);
						if (is_array($decoded)) {
							$prep = $decoded['prepTime'] ?? $decoded['prep_time'] ?? null;
							$cook = $decoded['cookTime'] ?? $decoded['cook_time'] ?? null;
							$total = $decoded['totalTime'] ?? $decoded['total_time'] ?? null;
							if ($total !== null || $prep !== null || $cook !== null) {
								if ($total !== null) $result['total'] = $this->isoDurationToReadable((string) $total);
								if ($prep !== null) $result['details'][] = ['label' => 'Préparation', 'value' => $this->isoDurationToReadable((string) $prep)];
								if ($cook !== null) $result['details'][] = ['label' => 'Cuisson', 'value' => $this->isoDurationToReadable((string) $cook)];
								return $result;
							}
						}
					}
				}
			}
		}

		// Fallback to DOM extraction
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
	   EXTRACTION : NOMBRE DE PARTS (SERVINGS)
	----------------------------------------- */
	private function extractServings(Crawler $crawler): ?int
	{
		// 1) Try to find servings in JSON-LD script (recipeYield) or Marmiton JS data
		try {
			$crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $script) use (&$crawler) {
				// noop - we will process below by reading all script tags
			});
		} catch (\Throwable $e) {
			// ignore
		}

		// Look through all script tags for JSON-LD or Marmiton JS object
		$scriptTexts = [];
		$crawler->filter('script')->each(function (Crawler $s) use (&$scriptTexts) {
			$text = trim($s->text());
			if ($text !== '') $scriptTexts[] = $text;
		});

		foreach ($scriptTexts as $scriptText) {
			// try JSON-LD first
			if (str_contains($scriptText, '{') && str_contains($scriptText, 'recipeYield')) {
				// try to locate the JSON block(s)
				if (preg_match_all('/(\{.*?\})/s', $scriptText, $m)) {
					foreach ($m[1] as $jsonBlock) {
						$decoded = json_decode($jsonBlock, true);
						if (is_array($decoded)) {
							if (isset($decoded['recipeYield'])) {
								$match = $this->extractIntFromString((string) $decoded['recipeYield']);
								if ($match !== null) return $match;
							}
							// nested recipe object
							if (isset($decoded['@type']) && strtolower($decoded['@type']) === 'recipe' && isset($decoded['recipeYield'])) {
								$match = $this->extractIntFromString((string) $decoded['recipeYield']);
								if ($match !== null) return $match;
							}
						}
					}
				}
			}

			// try Marmiton JS structure like Mrtn.recipesData or Mrtn variable with nb_pers
			if (str_contains($scriptText, 'nb_pers') || str_contains($scriptText, 'Mrtn.recipesData')) {
				if (preg_match('/"nb_pers"\s*:\s*(\d+)/u', $scriptText, $m2)) {
					$val = (int) $m2[1];
					if ($val > 0) return $val;
				}
				// sometimes inside JS object without quotes
				if (preg_match('/nb_pers\s*:\s*(\d+)/u', $scriptText, $m3)) {
					$val = (int) $m3[1];
					if ($val > 0) return $val;
				}
			}
		}

		// Try some selectors seen in Marmiton markup
		$selectors = [
			'.recipe-infos__people .recipe-infos__value',
			'.recipe-infos__people',
			'.recipe-infos__quantity .recipe-infos__value',
			'.mrtn-recette_informations__quantity',
			'.mrtn-recette-infos__people',
			'.mrtn-recette_informations', // fallback
			'.recipe-ingredients__qt-counter__value_container',
			'.recipe-ingredients__qt-counter__value_container input',
			'input.recipe-ingredients__qt-counter__value'
		];

		$highPriority = null;
		$lowPriority = null;
		foreach ($selectors as $sel) {
			$node = $crawler->filter($sel)->first();
			if ($node->count()) {
				// If the node contains an input element, prefer its value attribute
				// but only if the unit says it's 'personnes' or similar
				$input = $node->filter('input')->first();
				if ($input->count()) {
					$valueAttr = trim((string) $input->attr('value'));
					$matchVal = $this->extractIntFromString($valueAttr);
					// check that the unit is correct (span/unit text)
					$unitNode = $node->filter('.recipe-ingredients__qt-counter_unit')->first();
					$unitText = $unitNode->count() ? mb_strtolower(trim($unitNode->text())) : '';
					if ($matchVal !== null && ($unitText !== '')) {
						$unitTextLow = mb_strtolower($unitText);
						if (preg_match('/\b(personn|pers|personne|personnes)\b/u', $unitTextLow)) {
							$highPriority = $matchVal;
							$this->logger->debug('Marmiton: found high-priority servings from input', ['value' => $matchVal, 'unit' => $unitText, 'selector' => $sel]);
						} elseif (preg_match('/\b(part|parts|portion|portions)\b/u', $unitTextLow)) {
							$lowPriority = $matchVal;
							$this->logger->debug('Marmiton: found low-priority servings from input', ['value' => $matchVal, 'unit' => $unitText, 'selector' => $sel]);
						} else {
							// Other unit (g, min, etc.): ignore
							$this->logger->debug('Marmiton: ignoring input value for servings due to unit mismatch', ['value' => $matchVal, 'unit' => $unitText, 'selector' => $sel]);
						}
					}
					// Log ambiguous input found without persone unit, ignore
					if ($matchVal !== null) {
						$this->logger->debug('Marmiton: ignored numeric input for servings because no person unit found', ['value' => $matchVal, 'selector' => $sel, 'unit' => $unitText]);
					}
				}
				$text = trim($node->text());
				$match = $this->extractIntFromString($text);
				if ($match !== null) {
					$tLow = mb_strtolower($text);
					if (preg_match('/\b(personn|pers|personne|personnes)\b/u', $tLow) || preg_match('/pour\s*\d+/u', $tLow)) {
						$highPriority = $match;
						$this->logger->debug('Marmiton: found high-priority servings from text', ['value' => $match, 'text' => $text, 'selector' => $sel]);
					} elseif (preg_match('/\b(part|parts|portion|portions)\b/u', $tLow)) {
						$lowPriority = $match;
						$this->logger->debug('Marmiton: found low-priority servings from text', ['value' => $match, 'text' => $text, 'selector' => $sel]);
					} else {
						// ignore numbers without person/part context
						$this->logger->debug('Marmiton: found numeric value but text has no person/part context', ['value' => $match, 'text' => $text, 'selector' => $sel]);
					}
				}
			}
		}

		// Prefer high priority (personnes) over primary content, then low priority (parts)
		if ($highPriority !== null) return $highPriority;

		// Fallback: check primary items (they often contain values like "4 pers.")
		$primary = $this->extractPrimary($crawler);
		foreach ($primary as $p) {
			$match = $this->extractIntFromString($p);
			if ($match !== null) return $match;
		}

		if ($lowPriority !== null) return $lowPriority;

		// Fallback: check primary items (they often contain values like "4 pers.")
		$primary = $this->extractPrimary($crawler);
		foreach ($primary as $p) {
			$match = $this->extractIntFromString($p);
			if ($match !== null) return $match;
		}

		return null;
	}

	private function extractIntFromString(string $text): ?int
	{
		if (preg_match('/(\d+)/u', $text, $m)) {
			$val = (int) $m[1];
			return $val > 0 ? $val : null;
		}
		return null;
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
