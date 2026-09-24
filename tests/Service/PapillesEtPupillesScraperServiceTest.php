<?php

namespace App\Tests\Service;

use App\Service\PapillesEtPupillesScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PapillesEtPupillesScraperServiceTest extends TestCase
{
	public function testFetchRecipeUsesDetailedDomContentWhenJsonLdIsIncomplete(): void
	{
		$html = <<<'HTML'
		<html><body>
		<script type="application/ld+json">
		{"@type":"Recipe","name":"Cake test","image":["https://images.example/cake.jpg"],"recipeYield":["8"],"prepTime":"PT15M","recipeIngredient":["Farine"],"recipeInstructions":[]}
		</script>
		<div id="the_content"><div class="post_content">
		<h2>Ingrédients</h2>
		<ul><li>160 g de farine</li><li>3 oeufs</li></ul>
		<h2>Préparation</h2>
		<p>Préchauffez le four.</p><p>Mélangez les ingrédients.</p>
		<style></style>
		</div></div>
		</body></html>
		HTML;

		$service = new PapillesEtPupillesScraperService(
			new MockHttpClient(new MockResponse($html, ['http_code' => 200])),
			new NullLogger(),
		);

		$recipe = $service->fetchRecipe('https://www.papillesetpupilles.fr/cake-test/');

		self::assertSame('8', $recipe['servings']);
		self::assertSame('https://images.example/cake.jpg', $recipe['image']);
		self::assertSame('160', $recipe['ingredients'][0]['quantity']);
		self::assertSame('g', $recipe['ingredients'][0]['unit']);
		self::assertSame('farine', $recipe['ingredients'][0]['name']);
		self::assertCount(2, $recipe['steps']);
		self::assertSame('Préchauffez le four.', $recipe['steps'][0]['text']);
	}

	public function testRejectsUrlsOutsidePapillesEtPupilles(): void
	{
		$service = new PapillesEtPupillesScraperService(new MockHttpClient(), new NullLogger());

		$this->expectException(\InvalidArgumentException::class);
		$service->fetchRecipe('https://example.com/recipe');
	}
}
