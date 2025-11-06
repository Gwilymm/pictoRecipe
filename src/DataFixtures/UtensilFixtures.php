<?php

namespace App\DataFixtures;

use App\Entity\Utensil;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures pour créer des ustensiles de base
 */
class UtensilFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$utensils = [
			['name' => 'Fouet', 'picto' => null],
			['name' => 'Saladier', 'picto' => null],
			['name' => 'Poêle', 'picto' => null],
			['name' => 'Casserole', 'picto' => null],
			['name' => 'Four', 'picto' => null],
			['name' => 'Plaque de cuisson', 'picto' => null],
			['name' => 'Couteau', 'picto' => null],
			['name' => 'Planche à découper', 'picto' => null],
			['name' => 'Balance de cuisine', 'picto' => null],
			['name' => 'Verre doseur', 'picto' => null],
			['name' => 'Cuillère en bois', 'picto' => null],
			['name' => 'Spatule', 'picto' => null],
			['name' => 'Passoire', 'picto' => null],
			['name' => 'Râpe', 'picto' => null],
			['name' => 'Rouleau à pâtisserie', 'picto' => null],
			['name' => 'Moule à gâteau', 'picto' => null],
			['name' => 'Mixeur', 'picto' => null],
			['name' => 'Robot pâtissier', 'picto' => null],
		];

		foreach ($utensils as $data) {
			$utensil = (new Utensil())
				->setName($data['name'])
				->setPictogramUrl($data['picto']);

			$manager->persist($utensil);
		}

		$manager->flush();
	}
}
