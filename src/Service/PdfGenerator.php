<?php

namespace App\Service;

use Spatie\Browsershot\Browsershot;

class PdfGenerator
{
	public function generateFromHtml(string $html, ?string $projectPublicDir = null): string
	{
		// If a project public dir is provided, inline local image files as base64 data URIs
		if ($projectPublicDir) {
			$html = preg_replace_callback(
				'/src=(?:"|\')(?P<url>(?:https?:\/\/127\.0\.0\.1)?\/[^"\']+)(?:"|\')/i',
				function ($m) use ($projectPublicDir) {
					$url = $m['url'];

					// strip host if present
					if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
						$parsed = parse_url($url);
						$path = $parsed['path'] ?? $url;
					} else {
						$path = $url;
					}

					$file = rtrim($projectPublicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');
					if (!file_exists($file) || !is_readable($file)) {
						return $m[0]; // leave original if file not found
					}

					$data = @file_get_contents($file);
					if ($data === false) {
						return $m[0];
					}

					$finfo = new \finfo(FILEINFO_MIME_TYPE);
					$mime = $finfo->file($file) ?: 'application/octet-stream';
					$b64 = base64_encode($data);

					return 'src="data:' . $mime . ';base64,' . $b64 . '"';
				},
				$html
			);
		}

		return Browsershot::html($html)
			->setOption('landscape', false)
			->format('A4')
			->margins(10, 10, 10, 10)
			->showBackground()
			->waitUntilNetworkIdle()
			->setOption(
				'args',
				[
					'--no-sandbox',
					'--disable-setuid-sandbox',
					'--ignore-certificate-errors'
				]
			)
			->pdf();
	}
}
