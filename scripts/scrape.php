<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Service\MarmitonScraperService;
use Symfony\Component\HttpClient\HttpClient;
use Psr\Log\NullLogger;

$url = $argv[1] ?? 'https://www.marmiton.org/recettes/recette_creme-de-citron-lemon-curd_11210.aspx';

$http = HttpClient::create([
	'headers' => [
		'User-Agent' => 'Mozilla/5.0 (compatible; PictoRecette/1.0)',
		'Accept-Language' => 'fr-FR,fr;q=0.9',
	],
	'timeout' => 30,
]);

$logger = new NullLogger();
$service = new MarmitonScraperService($http, $logger);

$result = $service->fetchRecipe($url);

echo "Result for: $url\n";
print_r($result);
// Also write a JSON copy to /tmp for container-based imports/tests
file_put_contents('/tmp/scrape.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
