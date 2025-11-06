<?php

namespace App\Service;

use Spatie\Browsershot\Browsershot;

class PdfGenerator
{
	public function generateFromHtml(string $html): string
	{
		return Browsershot::html($html)
			->setOption('landscape', false)
			->format('A4')
			->margins(10, 10, 10, 10)
			->showBackground()
			->waitUntilNetworkIdle()
			->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors'])
			->pdf();
	}
}
