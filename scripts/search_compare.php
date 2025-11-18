<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Service\MarmitonScraperService;
use App\Service\CuisineazScraperService;
use Symfony\Component\HttpClient\HttpClient;
use Psr\Log\NullLogger;

$query = $argv[1] ?? 'citron';
$limit = isset($argv[2]) ? (int)$argv[2] : 20;
$sort = $argv[3] ?? 'none'; // 'none' | 'title' | 'rating'

$http = HttpClient::create([
	'headers' => [
		'User-Agent' => 'Mozilla/5.0 (compatible; PictoRecipe/1.0)',
		'Accept-Language' => 'fr-FR,fr;q=0.9',
	],
	'timeout' => 30,
]);

$logger = new NullLogger();
$mService = new MarmitonScraperService($http, $logger);
$cService = new CuisineazScraperService($http, $logger);

echo "Searching: $query (limit=$limit)\n";

$mResults = [];
try {
	$mResults = $mService->searchRecipes($query, $limit);
} catch (\Throwable $e) {
	fwrite(STDERR, "Marmiton search failed: " . $e->getMessage() . "\n");
}

$cResults = [];
try {
	$cResults = $cService->searchRecipes($query, $limit);
} catch (\Throwable $e) {
	fwrite(STDERR, "CuisineAZ search failed: " . $e->getMessage() . "\n");
}

// Merge, deduplicate by URL (keep first occurrence)
$merged = [];
$seen = [];
foreach (['marmiton' => $mResults, 'cuisineaz' => $cResults] as $src => $arr) {
	foreach ($arr as $it) {
		$key = $it['url'] ?? $it['link'] ?? ($it['title'] ?? null);
		if (!$key) continue;
		if (isset($seen[$key])) continue;
		$seen[$key] = true;
		$it['source'] = $it['source'] ?? $src;
		$merged[] = $it;
	}
}

$out = [
	'query' => $query,
	'limit' => $limit,
	'counts' => [
		'marmiton' => count($mResults),
		'cuisineaz' => count($cResults),
		'merged' => count($merged),
	],
	'marmiton' => $mResults,
	'cuisineaz' => $cResults,
	'merged' => $merged,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents('/tmp/search_compare.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Helper: sort arrays
function extractRating($item)
{
	if (empty($item['rating'])) return 0.0;
	if (preg_match('/(\d+(?:[\.,]\d+)?)/u', $item['rating'], $m)) {
		return (float) str_replace(',', '.', $m[1]);
	}
	return 0.0;
}

function sortResults(array &$arr, string $sortMode)
{
	if ($sortMode === 'title') {
		usort($arr, function ($a, $b) {
			return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
		});
	} elseif ($sortMode === 'rating') {
		usort($arr, function ($a, $b) {
			$ra = extractRating($a);
			$rb = extractRating($b);
			if ($ra == $rb) return 0;
			return ($ra > $rb) ? -1 : 1; // desc
		});
	}
}

if (in_array($sort, ['title', 'rating'])) {
	sortResults($mResults, $sort);
	sortResults($cResults, $sort);
	// rebuild merged from sorted lists
	$merged = [];
	$seen = [];
	foreach (['marmiton' => $mResults, 'cuisineaz' => $cResults] as $src => $arr) {
		foreach ($arr as $it) {
			$key = $it['url'] ?? $it['link'] ?? ($it['title'] ?? null);
			if (!$key) continue;
			if (isset($seen[$key])) continue;
			$seen[$key] = true;
			$it['source'] = $it['source'] ?? $src;
			$merged[] = $it;
		}
	}
	// update out
	$out['marmiton'] = $mResults;
	$out['cuisineaz'] = $cResults;
	$out['merged'] = $merged;
	$out['counts']['marmiton'] = count($mResults);
	$out['counts']['cuisineaz'] = count($cResults);
	$out['counts']['merged'] = count($merged);
	file_put_contents('/tmp/search_compare.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
