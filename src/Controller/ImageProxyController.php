<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Simple image proxy to avoid hotlinking issues and provide caching.
 * Allows a limited host allowlist — prevents open proxy abuse.
 */
class ImageProxyController extends AbstractController
{
	private const ALLOWED_DOMAINS = [
		'afcdn.com',
		'marmiton.org',
	];

	public function __construct(private HttpClientInterface $http, private LoggerInterface $logger) {}

	#[Route('/api/image-proxy', name: 'api_image_proxy', methods: ['GET'])]
	public function proxy(Request $request): Response
	{
		$url = (string) $request->query->get('url', '');
		if ($url === '') {
			return new Response('Missing url', Response::HTTP_BAD_REQUEST);
		}

		$parts = parse_url($url);
		if (!$parts || !isset($parts['host'])) {
			return new Response('Invalid url', Response::HTTP_BAD_REQUEST);
		}

		$host = $parts['host'];
		$allowed = false;
		foreach (self::ALLOWED_DOMAINS as $domain) {
			if (preg_match('/(^|\\.)' . preg_quote($domain, '/') . '$/i', $host)) {
				$allowed = true;
				break;
			}
		}
		if (!$allowed) {
			return new Response('Host not allowed', Response::HTTP_FORBIDDEN);
		}

		try {
			$res = $this->http->request('GET', $url, [
				'headers' => [
					'User-Agent' => 'PictoRecette/1.0',
					'Accept' => 'image/*,*/*;q=0.8'
				],
				'timeout' => 30,
				'max_redirects' => 5,
			]);

			if ($res->getStatusCode() !== 200) {
				$this->logger->warning('Image proxy upstream error', ['url' => $url, 'status' => $res->getStatusCode()]);
				return new Response('Upstream image error', Response::HTTP_BAD_GATEWAY);
			}

			$contentType = $res->getHeaders(false)['content-type'][0] ?? 'application/octet-stream';

			$response = new StreamedResponse(function () use ($res) {
				echo $res->getContent();
			});

			$response->headers->set('Content-Type', $contentType);
			$response->headers->set('Cache-Control', 'public, max-age=86400, s-maxage=86400');

			return $response;
		} catch (\Throwable $e) {
			$this->logger->error('Image proxy failed', ['url' => $url, 'error' => $e->getMessage()]);
			return new Response('Failed to fetch image', Response::HTTP_BAD_GATEWAY);
		}
	}
}
