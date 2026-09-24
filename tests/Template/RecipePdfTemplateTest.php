<?php

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class RecipePdfTemplateTest extends TestCase
{
	public function testIngredientsWithTheSameSectionAreRenderedTogether(): void
	{
		$twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
		$twig->addFunction(new TwigFunction('asset', static fn (string $path): string => $path));

		$ingredient = static fn (string $name, string $section): array => [
			'name' => $name,
			'section' => $section,
			'amount' => 1,
			'unit' => 'g',
			'pictogram' => null,
			'pictogramUrl' => null,
		];

		$html = $twig->render('recipe/pdf.html.twig', [
			'recipe' => [
				'title' => 'Recette test',
				'imagePath' => 'uploads/recipes/cake.jpg',
				'imagePositionX' => 70,
				'imagePositionY' => 30,
				'imageZoom' => 1.5,
				'ingredients' => [
					$ingredient('Farine', 'cake'),
					$ingredient('Beurre du moule', 'moule'),
					$ingredient('Levure chimique', 'cake'),
				],
				'utensils' => [],
				'steps' => [],
			],
		]);

		self::assertSame(1, substr_count($html, '>cake</td>'));
		self::assertSame(1, substr_count($html, '>moule</td>'));
		self::assertLessThan(strpos($html, '>moule</td>'), strpos($html, 'Levure chimique'));
		self::assertStringContainsString('class="recipe-heading-image"', $html);
		self::assertStringContainsString('src="http://127.0.0.1/uploads/recipes/cake.jpg"', $html);
		self::assertStringContainsString('object-position: 70% 30%', $html);
		self::assertStringContainsString('transform-origin: 70% 30%', $html);
		self::assertStringContainsString('transform: scale(1.5)', $html);
	}
}
