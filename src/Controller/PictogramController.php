<?php

namespace App\Controller;

use App\Entity\Pictogram;
use App\Form\PictogramType;
use App\Repository\PictogramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/pictogram')]
final class PictogramController extends AbstractController
{
	#[Route(name: 'app_pictogram_index', methods: ['GET'])]
	public function index(PictogramRepository $pictogramRepository): Response
	{
		return $this->render('pictogram/index.html.twig', [
			'pictograms' => $pictogramRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']),
		]);
	}

	#[Route('/wikimedia', name: 'app_pictogram_wikimedia', methods: ['GET'])]
	public function wikimedia(): Response
	{
		return $this->render('pictogram/wikimedia.html.twig');
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

			$this->addFlash('success', '✅ Pictogramme "' . $pictogram->getName() . '" ajouté avec succès !');
			return $this->redirectToRoute('app_pictogram_index');
		}

		return $this->render('pictogram/new.html.twig', [
			'pictogram' => $pictogram,
			'form' => $form,
		]);
	}

	#[Route('/{id}/validate', name: 'app_pictogram_validate', methods: ['POST'])]
	public function validate(Request $request, Pictogram $pictogram, EntityManagerInterface $entityManager): Response
	{
		if ($this->isCsrfTokenValid('validate' . $pictogram->getId(), $request->getPayload()->getString('_token'))) {
			$pictogram->setValidated(true);
			$entityManager->flush();

			$this->addFlash('success', 'Image validée pour la recherche automatique.');
		}

		return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_pictogram_index'));
	}


	#[Route('/{id}', name: 'app_pictogram_show', methods: ['GET'])]
	public function show(Pictogram $pictogram): Response
	{
		return $this->render('pictogram/show.html.twig', [
			'pictogram' => $pictogram,
		]);
	}

	#[Route('/{id}/edit', name: 'app_pictogram_edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, Pictogram $pictogram, EntityManagerInterface $entityManager, HttpClientInterface $http): Response
	{
		$form = $this->createForm(PictogramType::class, $pictogram);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$file = $form->get('imageFile')->getData();
			$externalUrl  = $request->request->get('externalImageTemp');

			// If no uploaded file but an external image was selected via the UI,
			// download it and turn it into a temporary File object to be processed
			if (!$file && $externalUrl) {
				try {
					$resp = $http->request('GET', $externalUrl);
					$bytes = $resp->getContent();

					$tempPath = tempnam(sys_get_temp_dir(), 'picto_');
					file_put_contents($tempPath, $bytes);

					$file = new \Symfony\Component\HttpFoundation\File\File($tempPath);
				} catch (\Throwable $e) {
					$this->addFlash('error', "Impossible de télécharger l'image externe.");
					return $this->redirectToRoute('app_pictogram_edit', ['id' => $pictogram->getId()]);
				}
			}
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

						$thumbDir = $this->getParameter('pictogram_directory') . '/thumbs';
						if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
						$this->createThumbnail($destination, $thumbDir . '/' . $newFilename, 70);
					} else {
						$origFilename = uniqid() . '.' . $file->guessExtension();
						$file->move($this->getParameter('pictogram_directory'), $origFilename);
						$pictogram->setFilePath('uploads/pictograms/' . $origFilename);
						$pictogram->setFormat($file->guessExtension());
					}
				}
			}

			$entityManager->flush();

			$this->addFlash('success', '✅ Pictogramme "' . $pictogram->getName() . '" modifié avec succès !');
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
			$name = $pictogram->getName();
			$entityManager->remove($pictogram);
			$entityManager->flush();
			$this->addFlash('success', '🗑️ Pictogramme "' . $name . '" supprimé avec succès !');
		}

		return $this->redirectToRoute('app_pictogram_index', [], Response::HTTP_SEE_OTHER);
	}


	/**
	 * Try to convert a raster image to PNG using GD. Returns true on success.
	 */
	private function convertToPng(string $sourcePath, string $destinationPath, ?string $mime): bool
	{
		try {
			// Charger l'image source
			switch ($mime) {
				case 'image/jpeg':
				case 'image/jpg':
					$src = @imagecreatefromjpeg($sourcePath);
					break;
				case 'image/png':
					$src = @imagecreatefrompng($sourcePath);
					break;
				case 'image/gif':
					$src = @imagecreatefromgif($sourcePath);
					break;
				case 'image/webp':
					$src = @imagecreatefromwebp($sourcePath);
					break;
				default:
					return false;
			}

			if (!$src) return false;

			$srcW = imagesx($src);
			$srcH = imagesy($src);

			// === FORMAT FINAL 256x256 ===
			$finalSize = 256;

			// Créer canvas final carré avec fond blanc
			$canvas = imagecreatetruecolor($finalSize, $finalSize);
			$white = imagecolorallocate($canvas, 255, 255, 255);
			imagefilledrectangle($canvas, 0, 0, $finalSize, $finalSize, $white);

			// Calcul du ratio
			$ratio = min($finalSize / $srcW, $finalSize / $srcH);
			$newW = (int)($srcW * $ratio);
			$newH = (int)($srcH * $ratio);

			// Positionnement centré
			$dstX = (int)(($finalSize - $newW) / 2);
			$dstY = (int)(($finalSize - $newH) / 2);

			// Redimensionnement dans le carré
			imagecopyresampled(
				$canvas,
				$src,
				$dstX,
				$dstY,
				0,
				0,
				$newW,
				$newH,
				$srcW,
				$srcH
			);

			// Enregistrer PNG final
			$ok = imagepng($canvas, $destinationPath, 9);

			imagedestroy($src);
			imagedestroy($canvas);

			return $ok;
		} catch (\Throwable $e) {
			return false;
		}
	}


	/**
	 * Create a PNG thumbnail from a raster image using GD (preserves transparency when possible).
	 * Returns true on success.
	 */
	private function createThumbnail(string $sourcePath, string $thumbnailPath, int $size = 70): bool
	{
		if (!file_exists($sourcePath)) return false;

		$src = @imagecreatefrompng($sourcePath);
		if (!$src) return false;

		// Canvas carré blanc
		$canvas = imagecreatetruecolor($size, $size);
		$white = imagecolorallocate($canvas, 255, 255, 255);
		imagefilledrectangle($canvas, 0, 0, $size, $size, $white);

		$srcW = imagesx($src);
		$srcH = imagesy($src);

		$ratio = min($size / $srcW, $size / $srcH);
		$newW = (int)($srcW * $ratio);
		$newH = (int)($srcH * $ratio);

		$dstX = (int)(($size - $newW) / 2);
		$dstY = (int)(($size - $newH) / 2);

		imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

		$ok = imagepng($canvas, $thumbnailPath, 9);

		imagedestroy($src);
		imagedestroy($canvas);

		return $ok;
	}
}
