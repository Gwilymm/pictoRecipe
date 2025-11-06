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
	public function new(Request $request, EntityManagerInterface $entityManager): Response
	{
		$pictogram = new Pictogram();
		$form = $this->createForm(PictogramType::class, $pictogram);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$file = $form->get('imageFile')->getData();
			if ($file) {
				$mime = $file->getMimeType();
				// If SVG, keep original
				if ($mime === 'image/svg+xml') {
					$newFilename = uniqid() . '.svg';
					$file->move($this->getParameter('pictogram_directory'), $newFilename);
					$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
					$pictogram->setFormat('svg');
				} else {
					// For raster images, convert to PNG
					$newFilename = uniqid() . '.png';
					$destination = $this->getParameter('pictogram_directory') . DIRECTORY_SEPARATOR . $newFilename;
					$source = $file->getPathname();

					if ($this->convertToPng($source, $destination, $mime)) {
						$pictogram->setFilePath('uploads/pictograms/' . $newFilename);
						$pictogram->setFormat('png');
					} else {
						// Fallback: move original file (keep extension)
						$origFilename = uniqid() . '.' . $file->guessExtension();
						$file->move($this->getParameter('pictogram_directory'), $origFilename);
						$pictogram->setFilePath('uploads/pictograms/' . $origFilename);
						$pictogram->setFormat($file->guessExtension());
					}
				}
			}

			$entityManager->persist($pictogram);
			$entityManager->flush();

			return $this->redirectToRoute('app_pictogram_index', [], Response::HTTP_SEE_OTHER);
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
}
