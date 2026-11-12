<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RemoteImageDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RemoteImageDownloaderTest extends TestCase
{
	// PNG 1×1 valide
	private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x35\x81\x84\x00\x00\x00\x00IEND\xaeB`\x82";

	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/remote-image-test-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->dir . '/*') ?: []);
		@rmdir($this->dir);
	}

	public function testSavesAValidImage(): void
	{
		$downloader = new RemoteImageDownloader(new MockHttpClient(new MockResponse(self::PNG)));

		$filename = $downloader->download('https://example.com/photo', $this->dir);

		self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $filename);
		self::assertSame(self::PNG, file_get_contents($this->dir . '/' . $filename));
	}

	public function testRejectsNonImageContent(): void
	{
		$downloader = new RemoteImageDownloader(new MockHttpClient(new MockResponse('<html>pas une image</html>')));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('JPEG, PNG ou WebP');
		$downloader->download('https://example.com/page.html', $this->dir);
	}

	public function testRejectsTooLargeImages(): void
	{
		$body = self::PNG . str_repeat("\0", RemoteImageDownloader::MAX_BYTES);
		$downloader = new RemoteImageDownloader(new MockHttpClient(new MockResponse($body)));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('5 Mo');
		$downloader->download('https://example.com/huge.png', $this->dir);
	}

	public function testRejectsHttpErrors(): void
	{
		$downloader = new RemoteImageDownloader(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

		$this->expectException(\InvalidArgumentException::class);
		$downloader->download('https://example.com/missing.png', $this->dir);
	}

	public function testRejectsNonHttpSchemes(): void
	{
		$downloader = new RemoteImageDownloader(new MockHttpClient(new MockResponse(self::PNG)));

		$this->expectException(\InvalidArgumentException::class);
		$downloader->download('file:///etc/passwd', $this->dir);
	}

	public function testRejectsPrivateNetworkAddresses(): void
	{
		// Même enveloppe que app.public_http_client : les IP privées sont refusées avant toute requête
		$client = new NoPrivateNetworkHttpClient(new MockHttpClient(new MockResponse(self::PNG)));
		$downloader = new RemoteImageDownloader($client);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('n’a pas pu être récupérée');
		$downloader->download('http://192.168.1.10/photo.png', $this->dir);
	}
}
