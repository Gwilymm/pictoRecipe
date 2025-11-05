<?php

namespace App\DataFixtures;

use App\Entity\Recipe;
use App\Entity\Ingredient;
use App\Entity\Step;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Créons 3 recettes de démonstration
        $recipesData = [
            [
                'title' => 'Cookies maison',
                'description' => 'De délicieux cookies moelleux aux pépites de chocolat 🍪',
                'servings' => 6,
                'prep' => 15,
                'cook' => 10,
                'ingredients' => [
                    ['Farine', '250', 'g'],
                    ['Beurre', '125', 'g'],
                    ['Sucre roux', '150', 'g'],
                    ['Œuf', '1', null],
                    ['Pépites de chocolat', '100', 'g'],
                ],
                'steps' => [
                    'Préchauffer le four à 180°C.',
                    'Mélanger le beurre et le sucre.',
                    'Ajouter l’œuf puis la farine.',
                    'Incorporer les pépites de chocolat.',
                    'Former des boules et enfourner 10 minutes.',
                ],
            ],
            [
                'title' => 'Crêpes sucrées',
                'description' => 'Des crêpes fines et dorées pour le goûter 🥞',
                'servings' => 4,
                'prep' => 10,
                'cook' => 15,
                'ingredients' => [
                    ['Farine', '250', 'g'],
                    ['Lait', '500', 'ml'],
                    ['Œufs', '3', null],
                    ['Sucre', '2', 'c.à.s'],
                    ['Beurre fondu', '30', 'g'],
                ],
                'steps' => [
                    'Mélanger la farine, le sucre et les œufs.',
                    'Incorporer petit à petit le lait.',
                    'Ajouter le beurre fondu et mélanger.',
                    'Laisser reposer la pâte 30 min.',
                    'Faire cuire chaque crêpe 1 min de chaque côté.',
                ],
            ],
            [
                'title' => 'Salade César',
                'description' => 'Une salade fraîche au poulet et parmesan 🥗',
                'servings' => 2,
                'prep' => 20,
                'cook' => 10,
                'ingredients' => [
                    ['Laitue romaine', '1', null],
                    ['Poulet grillé', '150', 'g'],
                    ['Croutons', '50', 'g'],
                    ['Parmesan râpé', '30', 'g'],
                    ['Sauce César', '4', 'c.à.s'],
                ],
                'steps' => [
                    'Laver et couper la laitue.',
                    'Griller le poulet et le trancher.',
                    'Mélanger la salade avec les croutons et la sauce.',
                    'Saupoudrer de parmesan avant de servir.',
                ],
            ],
        ];

        foreach ($recipesData as $data) {
            $recipe = (new Recipe())
                ->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setServings($data['servings'])
                ->setPrepTimeMinutes($data['prep'])
                ->setCookTimeMinutes($data['cook']);

            // Ingrédients
            $position = 1;
            foreach ($data['ingredients'] as [$name, $amount, $unit]) {
                $ingredient = (new Ingredient())
                    ->setName($name)
                    ->setAmount($amount)
                    ->setUnit($unit)
                    ->setPosition($position++)
                    ->setRecipe($recipe);
                $manager->persist($ingredient);
            }

            // Étapes
            $position = 1;
            foreach ($data['steps'] as $content) {
                $step = (new Step())
                    ->setContent($content)
                    ->setPosition($position++)
                    ->setRecipe($recipe);
                $manager->persist($step);
            }

            $manager->persist($recipe);
        }

        $manager->flush();
    }
}
