<?php

namespace App\Tests\Service;

use App\Entity\Pictogram;
use App\Service\WikimediaCommonsApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WikimediaCommonsApiServiceTest extends TestCase
{
	public function testSearchNormalizesWikimediaImageMetadata(): void
	{
		$client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
			self::assertSame('GET', $method);
			self::assertStringStartsWith('https://commons.wikimedia.org/w/api.php', $url);
			self::assertSame('strawberry', $options['query']['gsrsearch']);
			self::assertSame('20', $options['query']['gsrlimit']);
			self::assertSame('6', $options['query']['gsrnamespace']);
			self::assertSame('PictoRecette/1.0', $options['headers']['User-Agent']);

			return new MockResponse(json_encode([
				'query' => [
					'pages' => [
						[
							'pageid' => 123,
							'title' => 'File:Strawberry.jpg',
							'imageinfo' => [[
								'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a1/Strawberry.jpg',
								'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/Strawberry.jpg/400px-Strawberry.jpg',
								'width' => 1200,
								'height' => 800,
								'mime' => 'image/jpeg',
								'extmetadata' => [
									'LicenseShortName' => ['value' => '<span>CC BY-SA 4.0</span>'],
									'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
									'Artist' => ['value' => '<a href="#">Jane Doe</a>'],
									'Credit' => ['value' => '<p>Own work</p>'],
									'ImageDescription' => ['value' => '<b>Fresh strawberry</b>'],
								],
							]],
						],
						[
							'pageid' => 456,
							'title' => 'File:Document.pdf',
							'imageinfo' => [[
								'url' => 'https://upload.wikimedia.org/wikipedia/commons/0/00/Document.pdf',
								'mime' => 'application/pdf',
							]],
						],
					],
				],
			], JSON_THROW_ON_ERROR), ['http_code' => 200]);
		});

		$service = new WikimediaCommonsApiService($client, new ArrayAdapter(), new NullLogger());

		$results = $service->search('fraise', 50);

		self::assertCount(1, $results);
		$result = $results[0]->toArray();
		self::assertSame('File:Strawberry.jpg', $result['title']);
		self::assertSame(Pictogram::SOURCE_WIKIMEDIA_COMMONS, $result['source']);
		self::assertSame('123', $result['source_id']);
		self::assertSame('image/jpeg', $result['mime']);
		self::assertSame('CC BY-SA 4.0', $result['license']);
		self::assertSame('Jane Doe', $result['author']);
		self::assertSame('Own work', $result['credit']);
		self::assertSame('Fresh strawberry', $result['description']);
		self::assertSame('Jane Doe - CC BY-SA 4.0 - Wikimedia Commons', $result['attribution']);
	}

	public function testLicenseAllowListKeepsCommonsCompatibleLicenses(): void
	{
		$service = new WikimediaCommonsApiService(
			new MockHttpClient(),
			new ArrayAdapter(),
			new NullLogger(),
		);

		self::assertTrue($service->isAllowedLicense('CC0'));
		self::assertTrue($service->isAllowedLicense('CC BY-SA 4.0'));
		self::assertTrue($service->isAllowedLicense('Public domain'));
		self::assertFalse($service->isAllowedLicense(null));
		self::assertFalse($service->isAllowedLicense('All rights reserved'));
	}
}
