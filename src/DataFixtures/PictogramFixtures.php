<?php

namespace App\DataFixtures;

use App\Entity\Pictogram;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures pour créer des pictogrammes de test
 */
class PictogramFixtures extends Fixture
{
	public function load(ObjectManager $manager): void
	{
		$pictograms = [
			['name' => 'Pâte brisée', 'filePath' => 'uploads/pictograms/pate-brisee.png', 'format' => 'png'],
			['name' => 'Tomate', 'filePath' => 'uploads/pictograms/tomate.svg', 'format' => 'svg'],
			['name' => 'Fouet', 'filePath' => 'uploads/pictograms/fouet.png', 'format' => 'png'],
		];

		foreach ($pictograms as $data) {
			$pictogram = new Pictogram();
			$pictogram->setName($data['name']);
			$pictogram->setFilePath($data['filePath']);
			$pictogram->setFormat($data['format']);

			$manager->persist($pictogram);
			$this->addReference('pictogram_' . strtolower(str_replace(' ', '_', $data['name'])), $pictogram);
		}

		$manager->flush();
	}
}
