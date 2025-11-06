<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recipe')]
final class RecipeController extends AbstractController
{
    #[Route(name: 'app_recipe_index', methods: ['GET'])]
    public function index(RecipeRepository $recipeRepository): Response
    {
        return $this->render('recipe/index.html.twig', [
            'recipes' => $recipeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_recipe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Assigner les positions aux ingrédients et étapes
            $position = 0;
            foreach ($recipe->getIngredients() as $ingredient) {
                $ingredient->setPosition($position++);
            }

            $position = 0;
            foreach ($recipe->getSteps() as $step) {
                $step->setPosition($position++);
            }

            $entityManager->persist($recipe);
            $entityManager->flush();

            // Après création, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/new.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    /**
     * Affiche la prévisualisation complète de la recette avec mise en page pictogramme.
     */
    #[Route('/{id}/preview', name: 'app_recipe_preview', methods: ['GET'])]
    public function preview(Recipe $recipe): Response
    {
        // build a form populated with the current recipe so the preview can submit it (save/update)
        $form = $this->createForm(RecipeType::class, $recipe);

        return $this->render('recipe/preview.html.twig', [
            'recipe' => $recipe,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/preview/save', name: 'app_recipe_preview_save', methods: ['POST'])]
    public function previewSave(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Determine if this is an update (id present) or a new recipe
        $data = $request->request->get('recipe');
        $id = is_array($data) && array_key_exists('id', $data) ? $data['id'] : null;

        if ($id) {
            $recipe = $entityManager->getRepository(Recipe::class)->find($id);
            if (! $recipe) {
                $this->addFlash('error', 'Recette introuvable.');
                return $this->redirectToRoute('app_recipe_index');
            }
        } else {
            $recipe = new Recipe();
        }

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ensure positions are set
            $position = 0;
            foreach ($recipe->getIngredients() as $ingredient) {
                $ingredient->setPosition($position++);
            }
            $position = 0;
            foreach ($recipe->getSteps() as $step) {
                $step->setPosition($position++);
            }

            $entityManager->persist($recipe);
            $entityManager->flush();

            $this->addFlash('success', 'Recette enregistrée.');

            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()]);
        }

        // If invalid, re-render preview with the form (errors will be visible in form if template shows them)
        return $this->render('recipe/preview.html.twig', [
            'recipe' => $recipe,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Génère et télécharge la recette en PDF.
     * Utilise knp_snappy pour convertir le HTML en PDF avec pictogrammes.
     */
    #[Route('/{id}/pdf', name: 'app_recipe_pdf', methods: ['GET'])]
    public function pdf(Recipe $recipe, Pdf $pdf): Response
    {
        // Rendre le template HTML pour le PDF
        $html = $this->renderView('recipe/pdf.html.twig', [
            'recipe' => $recipe,
        ]);

        // Générer le PDF avec le nom de la recette
        $rawTitle = (string) $recipe->getTitle();
        // Sanitize filename for filesystem / headers: keep letters, numbers, dash and underscore
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', trim($rawTitle)) ?: 'recipe';
        $filename = $safe . '.pdf';

        // Use filename* for UTF-8 compatibility while keeping a safe ASCII filename
        $disposition = sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $filename, rawurlencode($rawTitle . '.pdf'));

        return new Response(
            $pdf->getOutputFromHtml($html, [
                'encoding' => 'UTF-8',
                'page-size' => 'A4',
                'margin-top' => 10,
                'margin-right' => 10,
                'margin-bottom' => 10,
                'margin-left' => 10,
                'enable-local-file-access' => true,
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition,
            ]
        );
    }

    #[Route('/{id}', name: 'app_recipe_show', methods: ['GET'])]
    public function show(Recipe $recipe): Response
    {
        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recipe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Assigner les positions aux ingrédients et étapes
            $position = 0;
            foreach ($recipe->getIngredients() as $ingredient) {
                $ingredient->setPosition($position++);
            }

            $position = 0;
            foreach ($recipe->getSteps() as $step) {
                $step->setPosition($position++);
            }

            $entityManager->flush();

            // Après mise à jour, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_recipe_delete', methods: ['POST'])]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $recipe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($recipe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
    }
}
