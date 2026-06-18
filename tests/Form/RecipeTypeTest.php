<?php

namespace App\Tests\Form;

use App\Form\RecipeType;
use PHPUnit\Framework\TestCase;

final class RecipeTypeTest extends TestCase
{
    public function testRemoveEmptySubmittedStepsDropsGhostRowsOnly(): void
    {
        $type = new RecipeType();
        $cleanup = \Closure::bind(
            static fn (RecipeType $type, array $data): array => $type->removeEmptySubmittedSteps($data),
            null,
            RecipeType::class
        );

        $data = [
            'steps' => [
                0 => [
                    'position' => '0',
                    'content' => 'Faire revenir les oignons.',
                    'durationMinutes' => '',
                    'pictogramUrl' => '',
                    'pictogramUrls' => '[]',
                ],
                3 => [
                    'position' => null,
                    'content' => null,
                    'durationMinutes' => '',
                    'pictogramUrl' => '',
                    'pictogramUrls' => null,
                ],
                4 => [
                    'position' => '4',
                    'content' => '',
                    'durationMinutes' => '',
                    'pictogramUrl' => '',
                    'pictogramUrls' => '["/uploads/pictograms/farine.png"]',
                ],
            ],
        ];

        $cleanedData = $cleanup($type, $data);

        self::assertArrayHasKey(0, $cleanedData['steps']);
        self::assertArrayNotHasKey(3, $cleanedData['steps']);
        self::assertArrayHasKey(4, $cleanedData['steps']);
    }
}
