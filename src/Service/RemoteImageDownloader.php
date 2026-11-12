<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Télécharge une image distante (URL saisie par l'utilisateur) et l'enregistre localement.
 *
 * Le client injecté refuse les adresses de réseaux privés (voir app.public_http_client dans
 * config/services.yaml) : sans ça, une URL saisie pourrait faire interroger le réseau interne (SSRF).
 */
final class RemoteImageDownloader
{
	public const MAX_BYTES = 5 * 1024 * 1024;

	private const EXTENSIONS = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
	];

	public function __construct(
		#[Autowire(service: 'app.public_http_client')]
		private readonly HttpClientInterface $httpClient,
	) {}

	/**
	 * @return string le nom du fichier créé dans $directory
	 *
	 * @throws \InvalidArgumentException message affichable à l'utilisateur (URL refusée, image invalide…)
	 * @throws \RuntimeException         erreur d'écriture côté serveur
	 */
	public function download(string $url, string $directory): string
	{
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		if (!\in_array($scheme, ['http', 'https'], true)) {
			throw new \InvalidArgumentException('L’adresse de l’image doit commencer par http:// ou https://.');
		}

		$content = $this->fetch($url);

		$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
		$extension = self::EXTENSIONS[$mime] ?? null;
		if ($extension === null) {
			throw new \InvalidArgumentException('Cette adresse ne mène pas à une image JPEG, PNG ou WebP.');
		}

		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
			throw new \RuntimeException('Impossible de créer le dossier des images de recettes.');
		}

		$filename = bin2hex(random_bytes(16)) . '.' . $extension;
		if (file_put_contents($directory . '/' . $filename, $content) === false) {
			throw new \RuntimeException('Impossible d’enregistrer l’image téléchargée.');
		}

		return $filename;
	}

	private function fetch(string $url): string
	{
		$unreachable = 'L’image n’a pas pu être récupérée. Vérifiez l’adresse.';
		$tooBig = 'L’image dépasse 5 Mo.';

		try {
			$response = $this->httpClient->request('GET', $url, [
				'timeout' => 10,
				'max_duration' => 20,
				'max_redirects' => 3,
				'headers' => ['Accept' => 'image/jpeg,image/png,image/webp'],
			]);

			if ($response->getStatusCode() !== 200) {
				throw new \InvalidArgumentException($unreachable);
			}

			$length = $response->getHeaders(false)['content-length'][0] ?? null;
			if ($length !== null && (int) $length > self::MAX_BYTES) {
				$response->cancel();
				throw new \InvalidArgumentException($tooBig);
			}

			// Lecture par morceaux : on s'arrête dès que la limite est dépassée (Content-Length absent ou faux)
			$content = '';
			foreach ($this->httpClient->stream($response) as $chunk) {
				$content .= $chunk->getContent();
				if (\strlen($content) > self::MAX_BYTES) {
					$response->cancel();
					throw new \InvalidArgumentException($tooBig);
				}
			}
		} catch (TransportExceptionInterface $e) {
			// Inclut les adresses refusées par le client « réseau public uniquement »
			throw new \InvalidArgumentException($unreachable, 0, $e);
		}

		if ($content === '') {
			throw new \InvalidArgumentException($unreachable);
		}

		return $content;
	}
}
