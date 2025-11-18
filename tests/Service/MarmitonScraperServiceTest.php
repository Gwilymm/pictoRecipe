<?php

namespace App\Tests\Service;

use App\Service\MarmitonScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MarmitonScraperServiceTest extends TestCase
{
	public function testFetchRecipeExtractsServingsAndTimes(): void
	{
		$html = <<<HTML
        <html>
        <body>
            <div class="recipe-primary">
                <div class="recipe-primary__item"><span>pour 4 personnes</span></div>
            </div>

            <div class="recipe-preparation__time">
                <div class="time__total"><div>1 h</div></div>
                <div class="time__details">
                    <div><span>Préparation :</span><div>20 min</div></div>
                    <div><span>Cuisson :</span><div>40 min</div></div>
                </div>
            </div>

            <ul class="mrtn-recette_ingredients-items">
                <li class="card-ingredient"><div class="ingredient-name">Farine</div><div class="card-ingredient-quantity"><span class="count">250</span><span class="unit">g</span></div></li>
            </ul>

            <div class="recipe-step-list">
                <div class="recipe-step-list__container">
                    <div class="recipe-step-list__head"><span>1</span></div>
                    <p>Faire quelque chose</p>
                </div>
            </div>
        </body>
        </html>
        HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('servings', $result);
		$this->assertSame(4, $result['servings']);

		$this->assertArrayHasKey('times', $result);
		$this->assertIsArray($result['times']);
		$this->assertArrayHasKey('details', $result['times']);
		$foundPrep = false;
		$foundCook = false;
		foreach ($result['times']['details'] as $d) {
			if (stripos($d['label'], 'préparation') !== false) {
				$foundPrep = true;
				$this->assertStringContainsString('20', $d['value']);
			}
			if (stripos($d['label'], 'cuisson') !== false) {
				$foundCook = true;
				$this->assertStringContainsString('40', $d['value']);
			}
		}
		$this->assertTrue($foundPrep);
		$this->assertTrue($foundCook);
	}

	public function testFetchRecipeExtractsServingsFromInputValue(): void
	{
		$html = <<<'HTML'
		<html>
		<body>
		    <div class="recipe-ingredients__qt-counter__value_container unit-true">
		        <input class="recipe-ingredients__qt-counter__value title-5" type="text" value="5" min="1" max="50" aria-label="counter">
		        <span class="recipe-ingredients__qt-counter_unit">personnes</span>
		    </div>
		</body>
		</html>
		HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('servings', $result);
		$this->assertSame(5, $result['servings']);
	}

	public function testDoesNotPickUnrelatedInputValue(): void
	{
		$html = <<<'HTML'
		<html>
		<body>
		    <div class="some-other-input">
		        <input class="some-input" type="text" value="30">
		    </div>
		    <div class="recipe-ingredients__qt-counter__value_container unit-true">
		        <input class="recipe-ingredients__qt-counter__value title-5" type="text" value="4" min="1" max="50" aria-label="counter">
		        <span class="recipe-ingredients__qt-counter_unit">personnes</span>
		    </div>
		</body>
		</html>
		HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('servings', $result);
		$this->assertSame(4, $result['servings'], 'Should pick the input from the recipe counter (4) and ignore unrelated 30');
	}

	public function testPrefersPersonnesOverParts(): void
	{
		$html = <<<'HTML'
		<html>
		<body>
		    <div class="mrtn-recette_informations">
		        <div>30 parts</div>
		    </div>
		    <div class="recipe-primary">
		        <div class="recipe-primary__item"><span>pour 4 personnes</span></div>
		    </div>
		</body>
		</html>
		HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('servings', $result);
		$this->assertSame(4, $result['servings'], 'When both 30 parts and 4 personnes appear, prefer 4 personnes');
	}

	public function testExtractsServingsFromJsonLd(): void
	{
		$html = <<<'HTML'
		<html>
		<body>
		<script type="application/ld+json">
		{
		  "@context": "http://schema.org",
		  "@type": "Recipe",
		  "name": "Test",
		  "recipeYield": "4 personnes"
		}
		</script>
		</body>
		</html>
		HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('servings', $result);
		$this->assertSame(4, $result['servings']);
	}

	public function testExtractsTimesFromJsonLd(): void
	{
		$html = <<<'HTML'
		<html>
		<body>
		<script type="application/ld+json">
		{
		  "@context": "http://schema.org",
		  "@type": "Recipe",
		  "name": "Test",
		  "prepTime": "PT20M",
		  "cookTime": "PT10M",
		  "totalTime": "PT30M"
		}
		</script>
		</body>
		</html>
		HTML;

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->any())
			->method('getContent')
			->willReturn($html);

		$httpMock = $this->createMock(HttpClientInterface::class);
		$httpMock->expects($this->any())
			->method('request')
			->willReturn($responseMock);

		$service = new MarmitonScraperService($httpMock, new NullLogger());
		$result = $service->fetchRecipe('https://dummy');

		$this->assertArrayHasKey('times', $result);
		$this->assertIsArray($result['times']);
		$this->assertSame('30 min', $result['times']['total']);
		$labels = array_column($result['times']['details'], 'label');
		$values = array_column($result['times']['details'], 'value');
		$this->assertContains('Préparation', $labels);
		$this->assertContains('Cuisson', $labels);
		$this->assertContains('20 min', $values);
		$this->assertContains('10 min', $values);
	}
}
