<?php

namespace App\Tests\Controller;

use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiRecipeImportControllerTest extends WebTestCase
{
	public function testImportPersistsRecipeThumbnail(): void
	{
		$client = static::createClient();
		$client->jsonRequest('POST', '/api/recipes/import', [
			'recipe' => [
				'title' => 'Recette avec image',
				'image' => ['url' => 'https://images.example/recette.jpg'],
				'ingredients' => [[
					'name' => 'Farine',
					'quantity' => '100',
					'unit' => 'g',
				]],
				'steps' => [[
					'text' => 'Mélanger.',
				]],
			],
		]);

		self::assertResponseStatusCodeSame(201);
		$data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

		$entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$recipe = $entityManager->getRepository(Recipe::class)->find($data['id']);
		self::assertInstanceOf(Recipe::class, $recipe);
		self::assertSame('https://images.example/recette.jpg', $recipe->getImagePath());
		self::assertSame(50, $recipe->getImagePositionX());
		self::assertSame(50, $recipe->getImagePositionY());
		self::assertSame(1.0, $recipe->getImageZoom());

		$entityManager->remove($recipe);
		$entityManager->flush();
	}
}
