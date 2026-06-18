<?php

namespace App\Tests\Form;

use App\Entity\Step;
use App\Form\DataTransformer\JsonToArrayTransformer;
use App\Form\StepType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class StepTypeTest extends TypeTestCase
{
    public function testNullContentSubmissionLeavesFormInvalidInsteadOfThrowing(): void
    {
        $step = (new Step())->setContent('Couper les tomates.');
        $form = $this->factory->create(StepType::class, $step);

        $form->submit([
            'content' => null,
            'durationMinutes' => '',
            'position' => '0',
            'pictogramUrl' => '',
            'pictogramUrls' => '["/picto/a.png"]',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());

        $submittedStep = $form->getData();

        self::assertInstanceOf(Step::class, $submittedStep);
        self::assertSame('', $submittedStep->getContent());
        self::assertSame(['/picto/a.png'], $submittedStep->getPictogramUrls());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new PreloadedExtension([
                new StepType(new JsonToArrayTransformer()),
            ], []),
            new ValidatorExtension($validator),
        ];
    }
}
