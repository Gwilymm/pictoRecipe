<?php

namespace App\Tests\Controller;

use App\Controller\PictogramController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PictogramControllerExternalImageTest extends TestCase
{
	public function testWikimediaThumbnailHostIsAllowed(): void
	{
		$controller = (new \ReflectionClass(PictogramController::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(PictogramController::class, 'downloadExternalImage');
		$client = new MockHttpClient(new MockResponse('image-content', [
			'http_code' => 200,
			'response_headers' => ['content-type: image/png'],
		]));

		$file = $method->invoke(
			$controller,
			'https://thumb.wikimedia.org/example/image.png',
			$client,
		);

		try {
			self::assertSame('image-content', file_get_contents($file->getPathname()));
		} finally {
			@unlink($file->getPathname());
		}
	}

	public function testUnrelatedThumbnailHostRemainsForbidden(): void
	{
		$controller = (new \ReflectionClass(PictogramController::class))->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod(PictogramController::class, 'downloadExternalImage');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Domaine image non autorisé');

		$method->invoke($controller, 'https://thumb.wikimedia.org.example.com/image.png', new MockHttpClient());
	}
}
