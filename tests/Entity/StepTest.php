<?php

namespace App\Tests\Entity;

use App\Entity\Step;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class StepTest extends TestCase
{
    public function testSetContentAcceptsNullAndLeavesStepInvalid(): void
    {
        $step = new Step();

        $step->setContent(null);

        self::assertSame('', $step->getContent());

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($step);

        self::assertGreaterThanOrEqual(1, $violations->count());
        self::assertSame('content', $violations[0]->getPropertyPath());
    }
}
