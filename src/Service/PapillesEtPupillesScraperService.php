<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PapillesEtPupillesScraperService
{
	private const BASE_URL = 'https://www.papillesetpupilles.fr';
	private const USER_AGENT = 'Mozilla/5.0 (compatible; PictoRecette/1.0)';

	public function __construct(
		private HttpClientInterface $http,
		private LoggerInterface $logger,
	) {}

	public function searchRecipes(string $query, int $limit = 20, array $filters = []): array
	{
		$url = self::BASE_URL . '/?s=' . rawurlencode($query);
		$html = $this->request($url);
		$crawler = new Crawler($html);
		$results = [];

		$crawler->filter('#the_content .post_size_2, article, .post, .recipe-card')->each(function (Crawler $node) use (&$results, $limit): void {
			if (count($results) >= $limit) return;
			$linkNode = $node->filter('h4.title a, h2 a, h3 a, .entry-title a')->first();
			if (!$linkNode->count()) return;
			$link = $this->absoluteUrl($linkNode->attr('href') ?? '');
			$title = trim($linkNode->text());
			if ($link === '' || $title === '') return;
			$imageNode = $node->filter('img')->first();
			$image = $imageNode->count() ? ($imageNode->attr('src') ?: $imageNode->attr('data-src') ?: '') : '';
			$results[] = ['title' => $title, 'name' => $title, 'link' => $link, 'url' => $link, 'image' => $image, 'picture' => $image, 'source' => 'papillesetpupilles'];
		});

		return $this->uniqueByUrl($results, $limit);
	}

	public function fetchRecipe(string $url): array
	{
		$this->logger->info('Fetching Papilles & Pupilles recipe', ['url' => $url]);
		$crawler = new Crawler($this->request($this->absoluteUrl($url)));
		$recipe = $this->findRecipeJsonLd($crawler);
		$title = trim((string) ($recipe['name'] ?? $this->firstText($crawler, ['h1', '.entry-title'])));
		$content = $this->extractContentSections($crawler);

		$ingredients = $content['ingredients'];
		if (!$ingredients) {
		foreach (($recipe['recipeIngredient'] ?? []) as $ingredient) {
			if (is_string($ingredient) && trim($ingredient) !== '') $ingredients[] = $this->parseIngredient(trim($ingredient));
		}
		}
		if (!$ingredients) {
			$crawler->filter('.recipe-ingredients li, .ingredients li, [itemprop="recipeIngredient"]')->each(function (Crawler $node) use (&$ingredients): void {
				$text = trim($node->text());
				if ($text !== '') $ingredients[] = ['group' => null, 'name' => $text, 'quantity' => '', 'unit' => '', 'complement' => ''];
			});
		}

		$steps = $content['steps'];
		$instructions = $recipe['recipeInstructions'] ?? [];
		if (is_string($instructions)) $instructions = [$instructions];
		if (!$steps) {
			foreach (is_array($instructions) ? $instructions : [] as $instruction) {
				$text = trim((string) (is_array($instruction) ? ($instruction['text'] ?? '') : $instruction));
				if ($text !== '') $steps[] = ['number' => 'Étape ' . (count($steps) + 1), 'text' => $text];
			}
		}
		if (!$steps) {
			$crawler->filter('.recipe-instructions li, .instructions li, [itemprop="recipeInstructions"]')->each(function (Crawler $node) use (&$steps): void {
				$text = trim($node->text());
				if ($text !== '') $steps[] = ['number' => 'Étape ' . (count($steps) + 1), 'text' => $text];
			});
		}

		$servings = $recipe['recipeYield'] ?? null;
		if (is_array($servings)) $servings = $servings[0] ?? null;

		return ['ok' => true, 'title' => $title, 'description' => $recipe['description'] ?? null, 'image' => $recipe['image'] ?? null, 'primary' => [], 'times' => ['total' => $recipe['totalTime'] ?? null, 'details' => $this->timeDetails($recipe)], 'servings' => $servings, 'ingredients' => $ingredients, 'utensils' => [], 'steps' => $steps];
	}

	/** @return array{ingredients: array<int, array<string, ?string>>, steps: array<int, array<string, string>>} */
	private function extractContentSections(Crawler $crawler): array
	{
		$ingredients = [];
		$steps = [];
		$section = null;
		$content = $crawler->filter('#the_content .post_content')->first();
		if (!$content->count()) $content = $crawler->filter('#the_content')->first();
		if (!$content->count()) return compact('ingredients', 'steps');

		$content->children()->each(function (Crawler $node) use (&$section, &$ingredients, &$steps): void {
			$tag = strtolower($node->nodeName());
			$text = trim(preg_replace('/\s+/u', ' ', $node->text('')) ?? '');
			$normalized = mb_strtolower($text);

			if ($tag === 'h2') {
				$section = str_contains($normalized, 'ingrédient')
					? 'ingredients'
					: (str_contains($normalized, 'préparation') ? 'steps' : null);
				return;
			}

			if ($section === 'ingredients' && $tag === 'ul') {
				$node->filter('li')->each(function (Crawler $item) use (&$ingredients): void {
					$text = trim(preg_replace('/\s+/u', ' ', $item->text()) ?? '');
					if ($text !== '') $ingredients[] = $this->parseIngredient($text);
				});
			} elseif ($section === 'steps' && $tag === 'p' && $text !== '') {
				$steps[] = ['number' => 'Étape ' . (count($steps) + 1), 'text' => $text];
			} elseif ($section === 'steps' && $tag === 'style') {
				$section = null;
			}
		});

		return compact('ingredients', 'steps');
	}

	/** @return array{group: null, name: string, quantity: string, unit: string, complement: string} */
	private function parseIngredient(string $text): array
	{
		$quantity = '';
		$unit = '';
		$name = $text;
		$units = 'kg|g|mg|l|cl|ml|cuillères?\s+à\s+(?:soupe|café)|c\.\s*à\s*(?:s\.|c\.)|pincées?|sachets?';

		if (preg_match('/^([\d]+(?:[,.]\d+)?|\d+\/\d+)\s*(?:(' . $units . ')\b)?\s*(?:de\s+|d[’\']\s*)?(.*)$/iu', $text, $matches)) {
			$quantity = str_replace(',', '.', $matches[1]);
			$unit = trim($matches[2] ?? '');
			$name = trim($matches[3] ?? $text);
		}

		return ['group' => null, 'name' => $name ?: $text, 'quantity' => $quantity, 'unit' => $unit, 'complement' => ''];
	}

	private function request(string $url): string
	{
		$this->assertSupportedUrl($url);
		$response = $this->http->request('GET', $url, [
			'headers' => [
				'User-Agent' => self::USER_AGENT,
				'Accept' => 'text/html,application/xhtml+xml',
				'Accept-Language' => 'fr-FR,fr;q=0.9',
			],
			'timeout' => 30,
		]);

		if ($response->getStatusCode() !== 403) {
			return $response->getContent();
		}

		$this->logger->info('Papilles & Pupilles rejected the HTTP client; retrying with Chromium', ['url' => $url]);

		return $this->requestWithBrowser($url);
	}

	private function requestWithBrowser(string $url): string
	{
		$script = dirname(__DIR__, 2) . '/bin/fetch-papilles-page.js';
		$process = new Process(['/usr/bin/node', $script, $url], null, [
			'NODE_PATH' => '/usr/local/lib/node_modules',
		]);
		$process->setTimeout(50);
		$process->mustRun();

		return $process->getOutput();
	}

	private function assertSupportedUrl(string $url): void
	{
		$host = strtolower((string) parse_url($url, PHP_URL_HOST));
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		if ($scheme !== 'https' || !in_array($host, ['papillesetpupilles.fr', 'www.papillesetpupilles.fr'], true)) {
			throw new \InvalidArgumentException('Only Papilles & Pupilles HTTPS URLs are supported.');
		}
	}

	private function findRecipeJsonLd(Crawler $crawler): array
	{
		$found = [];
		$crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$found): void {
			$data = json_decode(trim($node->text()), true);
			$objects = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : (is_array($data) && array_is_list($data) ? $data : [$data]);
			foreach ($objects as $object) {
				if (!is_array($object)) continue;
				$type = $object['@type'] ?? [];
				$types = is_array($type) ? $type : [$type];
				if (in_array('Recipe', $types, true) || in_array('recipe', array_map('strtolower', $types), true)) { $found = $object; return; }
			}
		});
		return $found;
	}

	private function firstText(Crawler $crawler, array $selectors): string { foreach ($selectors as $selector) { $node = $crawler->filter($selector)->first(); if ($node->count()) return trim($node->text()); } return ''; }
	private function absoluteUrl(string $url): string { return str_starts_with($url, '/') ? self::BASE_URL . $url : $url; }
	private function uniqueByUrl(array $items, int $limit): array { $seen = []; $result = []; foreach ($items as $item) { if (isset($seen[$item['url']])) continue; $seen[$item['url']] = true; $result[] = $item; if (count($result) >= $limit) break; } return $result; }
	private function timeDetails(array $recipe): array { $details = []; foreach (['prepTime' => 'Préparation', 'cookTime' => 'Cuisson'] as $key => $label) if (!empty($recipe[$key])) $details[] = ['label' => $label, 'value' => $recipe[$key]]; return $details; }
}
