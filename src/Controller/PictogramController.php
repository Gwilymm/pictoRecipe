<?php

namespace App\Controller;

use App\Entity\Pictogram;
use App\Form\PictogramType;
use App\Repository\PictogramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/pictogram')]
final class PictogramController extends AbstractController
{
	#[Route(name: 'app_pictogram_index', methods: ['GET'])]
	public function index(PictogramRepository $pictogramRepository): Response
	{
		return $this->render('pictogram/index.html.twig', [
			'pictograms' => $pictogramRepository->findAll(),
		]);
	}

	#[Route('/new', name: 'app_pictogram_new', methods: ['GET', 'POST'])]
	public function new(
		Request $request,
		EntityManagerInterface $entityManager,
		HttpClientInterface $http
	): Response {
		$pictogram = new Pictogram();
		$form = $this->createForm(PictogramType::class, $pictogram);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {

			$uploadedFile = $form->get('imageFile')->getData();
			$externalUrl  = $request->request->get('externalImageTemp');

			/**
			 * Si une IMAGE OFF est choisie → on LA TÉLÉCHARGE comme si c’était un file upload
			 */
			if (!$uploadedFile && $externalUrl) {
				try {
					$response = $http->request('GET', $externalUrl);
					$bytes = $response->getContent();

					// Temp file pour imiter un upload
					$tempPath = tempnam(sys_get_temp_dir(), 'picto_');
					file_put_contents($tempPath, $bytes);

					// Symfony UploadedFile-like behaviour
					$uploadedFile = new \Symfony\Component\HttpFoundation\File\File(
						$tempPath
					);
				} catch (\Throwable $e) {
					$this->addFlash('error', "Impossible de télécharger l'image externe.");
					return $this->redirectToRoute('app_pictogram_new');
				}
			}

			/**
			 * Pipeline unique : que l’image vienne d’un upload ou d’OpenFoodFacts
			 */
			if ($uploadedFile) {
				$mime = $uploadedFile->getMimeType();

				// SVG → garder original
				if ($mime === 'image/svg+xml') {
					$newFilename = uniqid() . '.svg';
					$uploadedFile->move($this->getParameter('pictogram_directory'), $newFilename);

					$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
					$pictogram->setFormat('svg');
				} else {
					// Convertir RASTER → PNG (même comportement que ton code)
					$newFilename = uniqid() . '.png';
					$destination = $this->getParameter('pictogram_directory') . '/' . $newFilename;

					if ($this->convertToPng($uploadedFile->getPathname(), $destination, $mime)) {
						$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
						$pictogram->setFormat('png');

						// Thumbnail
						$thumbDir = $this->getParameter('pictogram_directory') . '/thumbs';
						if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

						$this->createThumbnail($destination, $thumbDir . '/' . $newFilename, 70);
					}
				}
			} else {
				$this->addFlash('error', 'Veuillez uploader une image ou en sélectionner une.');
				return $this->redirectToRoute('app_pictogram_new');
			}

			$entityManager->persist($pictogram);
			$entityManager->flush();

			return $this->redirectToRoute('app_pictogram_index');
		}

		return $this->render('pictogram/new.html.twig', [
			'pictogram' => $pictogram,
			'form' => $form,
		]);
	}


	#[Route('/{id}', name: 'app_pictogram_show', methods: ['GET'])]
	public function show(Pictogram $pictogram): Response
	{
		return $this->render('pictogram/show.html.twig', [
			'pictogram' => $pictogram,
		]);
	}

	#[Route('/{id}/edit', name: 'app_pictogram_edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, Pictogram $pictogram, EntityManagerInterface $entityManager): Response
	{
		$form = $this->createForm(PictogramType::class, $pictogram, ['require_image' => false]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$file = $form->get('imageFile')->getData();
			if ($file) {
				$mime = $file->getMimeType();
				if ($mime === 'image/svg+xml') {
					$newFilename = uniqid() . '.svg';
					$file->move($this->getParameter('pictogram_directory'), $newFilename);
					$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
					$pictogram->setFormat('svg');
				} else {
					$newFilename = uniqid() . '.png';
					$destination = $this->getParameter('pictogram_directory') . DIRECTORY_SEPARATOR . $newFilename;
					$source = $file->getPathname();

					if ($this->convertToPng($source, $destination, $mime)) {
						$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
						$pictogram->setFormat('png');
					} else {
						$origFilename = uniqid() . '.' . $file->guessExtension();
						$file->move($this->getParameter('pictogram_directory'), $origFilename);
						$pictogram->setFilePath('uploads/pictograms/' . $origFilename);
						$pictogram->setFormat($file->guessExtension());
					}
				}
			}

			$entityManager->flush();

			return $this->redirectToRoute('app_pictogram_index', [], Response::HTTP_SEE_OTHER);
		}

		return $this->render('pictogram/edit.html.twig', [
			'pictogram' => $pictogram,
			'form' => $form,
		]);
	}

	#[Route('/{id}', name: 'app_pictogram_delete', methods: ['POST'])]
	public function delete(Request $request, Pictogram $pictogram, EntityManagerInterface $entityManager): Response
	{
		if ($this->isCsrfTokenValid('delete' . $pictogram->getId(), $request->getPayload()->getString('_token'))) {
			$entityManager->remove($pictogram);
			$entityManager->flush();
		}

		return $this->redirectToRoute('app_pictogram_index', [], Response::HTTP_SEE_OTHER);
	}


	/**
	 * Try to convert a raster image to PNG using GD. Returns true on success.
	 */
	private function convertToPng(string $sourcePath, string $destinationPath, ?string $mime): bool
	{
		if (!function_exists('imagepng')) {
			return false;
		}

		try {
			$img = null;
			switch ($mime) {
				case 'image/jpeg':
				case 'image/jpg':
					if (function_exists('imagecreatefromjpeg')) {
						$img = @imagecreatefromjpeg($sourcePath);
					}
					break;
				case 'image/png':
					if (function_exists('imagecreatefrompng')) {
						$img = @imagecreatefrompng($sourcePath);
					}
					break;
				case 'image/gif':
					if (function_exists('imagecreatefromgif')) {
						$img = @imagecreatefromgif($sourcePath);
					}
					break;
				case 'image/webp':
					if (function_exists('imagecreatefromwebp')) {
						$img = @imagecreatefromwebp($sourcePath);
					}
					break;
				default:
					// unsupported mime
					return false;
			}

			if (!$img) {
				return false;
			}

			// Preserve alpha for PNG
			imagesavealpha($img, true);
			imagealphablending($img, false);

			// Write PNG
			$ok = imagepng($img, $destinationPath);
			imagedestroy($img);

			return (bool) $ok;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Create a PNG thumbnail from a raster image using GD (preserves transparency when possible).
	 * Returns true on success.
	 */
	private function createThumbnail(string $sourcePath, string $thumbnailPath, int $maxSide = 70): bool
	{
		if (!function_exists('imagecreatetruecolor')) {
			return false;
		}

		if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
			return false;
		}

		$mime = mime_content_type($sourcePath) ?: '';
		$img = null;
		switch ($mime) {
			case 'image/png':
				if (function_exists('imagecreatefrompng')) $img = @imagecreatefrompng($sourcePath);
				break;
			case 'image/jpeg':
			case 'image/jpg':
				if (function_exists('imagecreatefromjpeg')) $img = @imagecreatefromjpeg($sourcePath);
				break;
			case 'image/gif':
				if (function_exists('imagecreatefromgif')) $img = @imagecreatefromgif($sourcePath);
				break;
			case 'image/webp':
				if (function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($sourcePath);
				break;
			default:
				// unsupported or SVG
				return false;
		}

		if (! $img) {
			return false;
		}

		$width = imagesx($img);
		$height = imagesy($img);

		// compute new size preserving ratio
		$scale = min($maxSide / max($width, $height), 1);
		$newW = (int) max(1, floor($width * $scale));
		$newH = (int) max(1, floor($height * $scale));

		$thumb = imagecreatetruecolor($newW, $newH);
		// preserve transparency for PNG and GIF
		imagealphablending($thumb, false);
		imagesavealpha($thumb, true);
		$transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
		imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);

		imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);

		// Ensure directory exists
		$dir = dirname($thumbnailPath);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$ok = imagepng($thumb, $thumbnailPath);

		imagedestroy($thumb);
		imagedestroy($img);

		return (bool) $ok;
	}
}
