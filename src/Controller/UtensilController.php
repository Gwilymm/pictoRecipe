<?php

namespace App\Controller;

use App\Entity\Utensil;
use App\Form\UtensilType;
use App\Repository\UtensilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/utensil')]
class UtensilController extends AbstractController
{
	#[Route(name: 'app_utensil_index', methods: ['GET'])]
	public function index(UtensilRepository $utensilRepository): Response
	{
		return $this->render('utensil/index.html.twig', [
			'utensils' => $utensilRepository->findAll(),
		]);
	}

	#[Route('/new', name: 'app_utensil_new', methods: ['GET', 'POST'])]
	public function new(Request $request, EntityManagerInterface $entityManager): Response
	{
		$utensil = new Utensil();
		$form = $this->createForm(UtensilType::class, $utensil);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$entityManager->persist($utensil);
			$entityManager->flush();

			$this->addFlash('success', 'Ustensile créé avec succès !');

			return $this->redirectToRoute('app_utensil_index', [], Response::HTTP_SEE_OTHER);
		}

		return $this->render('utensil/new.html.twig', [
			'utensil' => $utensil,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'app_utensil_edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, Utensil $utensil, EntityManagerInterface $entityManager): Response
	{
		$form = $this->createForm(UtensilType::class, $utensil);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$entityManager->flush();

			$this->addFlash('success', 'Ustensile modifié avec succès !');

			return $this->redirectToRoute('app_utensil_index', [], Response::HTTP_SEE_OTHER);
		}

		return $this->render('utensil/edit.html.twig', [
			'utensil' => $utensil,
			'form' => $form,
		]);
	}

	#[Route('/{id}', name: 'app_utensil_delete', methods: ['POST'])]
	public function delete(Request $request, Utensil $utensil, EntityManagerInterface $entityManager): Response
	{
		// Use the POST request parameters to retrieve the CSRF token (standard behavior)
		$token = $request->request->get('_token');

		if ($this->isCsrfTokenValid('delete' . $utensil->getId(), $token)) {
			$entityManager->remove($utensil);
			$entityManager->flush();

			$this->addFlash('success', 'Ustensile supprimé avec succès !');
		}

		return $this->redirectToRoute('app_utensil_index', [], Response::HTTP_SEE_OTHER);
	}
}
