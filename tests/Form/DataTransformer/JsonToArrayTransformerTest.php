<?php

namespace App\Tests\Form\DataTransformer;

use App\Form\DataTransformer\JsonToArrayTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class JsonToArrayTransformerTest extends TestCase
{
    public function testTransformArrayToJsonString(): void
    {
        $transformer = new JsonToArrayTransformer();

        self::assertSame('["\/picto\/a.png","\/picto\/b.png"]', $transformer->transform(['/picto/a.png', '/picto/b.png']));
    }

    public function testReverseTransformJsonStringToArray(): void
    {
        $transformer = new JsonToArrayTransformer();

        self::assertSame(['/picto/a.png', '/picto/b.png'], $transformer->reverseTransform('["/picto/a.png","/picto/b.png"]'));
    }

    public function testReverseTransformEmptyValueToNull(): void
    {
        $transformer = new JsonToArrayTransformer();

        self::assertNull($transformer->reverseTransform(''));
        self::assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformInvalidJsonThrowsExplicitException(): void
    {
        $transformer = new JsonToArrayTransformer();

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('JSON invalide pour le champ pictogrammes');

        $transformer->reverseTransform('["/picto/a.png"');
    }

    public function testReverseTransformScalarJsonThrowsExplicitException(): void
    {
        $transformer = new JsonToArrayTransformer();

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('tableau JSON');

        $transformer->reverseTransform('"not-an-array"');
    }
}
