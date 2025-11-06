<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
    public function new(Request $request, EntityManagerInterface $entityManager, \App\Repository\PictogramRepository $pictogramRepository): Response
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

            // Map submitted pictogramUrl fields to local Pictogram relations when applicable
            $this->mapPictogramsOnRecipe($recipe, $pictogramRepository);

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
    public function previewSave(Request $request, EntityManagerInterface $entityManager, \App\Repository\PictogramRepository $pictogramRepository): Response
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

            // Map pictogramUrl -> Pictogram relation for local pictograms
            $this->mapPictogramsOnRecipe($recipe, $pictogramRepository);

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
    public function pdf(Recipe $recipe, Pdf $pdf, Request $request, \Psr\Log\LoggerInterface $logger): Response
    {
        // Start timing
        $t0 = microtime(true);

        // If not explicitly requested via ?download=1, do not generate the PDF (protect against prefetch/polls)
        if ($request->query->get('download') !== '1') {
            $logger->info('PDF generation skipped (no download flag)', [
                'recipe_id' => $recipe->getId(),
                'referer' => $request->headers->get('referer'),
                'user_agent' => $request->headers->get('user-agent'),
                'ts' => date('c'),
            ]);

            // Return 204 No Content to indicate nothing to download (fast response)
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Log PDF requests to help diagnose unexpected automatic generation
        $logger->info('PDF generation requested (start)', [
            'recipe_id' => $recipe->getId(),
            'referer' => $request->headers->get('referer'),
            'user_agent' => $request->headers->get('user-agent'),
            'ts' => date('c'),
        ]);
        // Rendre le template HTML pour le PDF
        $projectPublic = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public';

        // Simplified rendering: do not inline images (base64). Let wkhtmltopdf read local files via file://
        $tRenderStart = microtime(true);
        $html = $this->renderView('recipe/pdf.html.twig', [
            'recipe' => $recipe,
            // provide absolute public dir for file:// references inside the template
            'project_public_dir' => $projectPublic,
        ]);
        $tRenderEnd = microtime(true);

        $logger->info('Rendered HTML for PDF', ['recipe_id' => $recipe->getId(), 'dur_ms' => round(($tRenderEnd - $tRenderStart) * 1000, 1)]);

        // PDF caching: store under public/cache/pdf/{id}.pdf
        $cacheDir = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pdf';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $recipe->getId() . '.pdf';
        $force = $request->query->get('force') === '1';

        // If cached and up-to-date, return it
        $updatedAt = $recipe->getUpdatedAt();
        if (!$force && file_exists($cachePath) && is_readable($cachePath)) {
            $cacheMTime = filemtime($cachePath);
            if ($cacheMTime !== false && $updatedAt instanceof \DateTimeInterface && $cacheMTime > $updatedAt->getTimestamp()) {
                $logger->info('Returning cached PDF', ['recipe_id' => $recipe->getId(), 'cache_path' => $cachePath]);
                $response = new BinaryFileResponse($cachePath);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$recipe->getTitle())) . '.pdf');
                return $response;
            }
        }

        // Generate PDF via wkhtmltopdf
        $tPdfStart = microtime(true);
        $pdfOutput = $pdf->getOutputFromHtml($html, [
            'encoding' => 'UTF-8',
            'page-size' => 'A4',
            'margin-top' => 10,
            'margin-right' => 10,
            'margin-bottom' => 10,
            'margin-left' => 10,
            'enable-local-file-access' => true,
        ]);
        $tPdfEnd = microtime(true);

        $pdfBytes = strlen($pdfOutput);
        $tEnd = microtime(true);
        $logger->info('PDF generation complete', ['recipe_id' => $recipe->getId(), 'total_dur_ms' => round(($tEnd - $t0) * 1000, 1), 'pdf_bytes' => $pdfBytes]);

        // Store in cache
        if ($pdfOutput !== false) {
            @file_put_contents($cachePath, $pdfOutput);
        }

        // Return cached file response (serves the freshly created file)
        $response = new BinaryFileResponse($cachePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$recipe->getTitle())) . '.pdf');
        return $response;
    }

    #[Route('/{id}', name: 'app_recipe_show', methods: ['GET'])]
    public function show(Recipe $recipe): Response
    {
        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recipe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $entityManager, \App\Repository\PictogramRepository $pictogramRepository): Response
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

            // Map pictogramUrl -> Pictogram relation for local pictograms
            $this->mapPictogramsOnRecipe($recipe, $pictogramRepository);

            $entityManager->flush();

            // Après mise à jour, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    private function mapPictogramsOnRecipe(Recipe $recipe, \App\Repository\PictogramRepository $pictogramRepository): void
    {
        foreach ($recipe->getIngredients() as $ingredient) {
            $url = $ingredient->getPictogramUrl();
            if (! $url) {
                $ingredient->setPictogram(null);
                continue;
            }

            // Local pictograms are served from /uploads/pictograms/<file>
            if (str_contains($url, 'uploads/pictograms')) {
                // normalize to stored filePath (no leading slash)
                $filePath = ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');
                $local = $pictogramRepository->findOneBy(['filePath' => $filePath]);
                if ($local) {
                    $ingredient->setPictogram($local);
                }
            } else {
                // External ARASAAC URL — do not set a local relation
                $ingredient->setPictogram(null);
            }
        }

        // Steps may contain multiple pictogramUrls (array) or single pictogramUrl — handle only single URL mapping on step.pictogramUrl
        foreach ($recipe->getSteps() as $step) {
            $url = $step->getPictogramUrl();
            if (! $url) {
                $step->setPictogram(null);
                continue;
            }

            if (str_contains($url, 'uploads/pictograms')) {
                $filePath = ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');
                $local = $pictogramRepository->findOneBy(['filePath' => $filePath]);
                if ($local) {
                    $step->setPictogram($local);
                }
            } else {
                $step->setPictogram(null);
            }
        }

        // Utensils are managed separately (in form they are a collection of choices), but if any utensil entity has a pictogramUrl field we map it similarly — check for method existence
        if (method_exists($recipe, 'getUtensils')) {
            foreach ($recipe->getUtensils() as $utensil) {
                if (! method_exists($utensil, 'getPictogramUrl')) {
                    continue;
                }
                $url = $utensil->getPictogramUrl();
                if (! $url) {
                    $utensil->setPictogram(null);
                    continue;
                }
                if (str_contains($url, 'uploads/pictograms')) {
                    $filePath = ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');
                    $local = $pictogramRepository->findOneBy(['filePath' => $filePath]);
                    if ($local) {
                        $utensil->setPictogram($local);
                    }
                } else {
                    $utensil->setPictogram(null);
                }
            }
        }
    }

    // Inline image building removed: PDF generation now uses file:// references and a disk cache to speed up generation.

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
