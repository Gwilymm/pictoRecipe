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
    public function pdf(Recipe $recipe, Pdf $pdf): Response
    {
        // Rendre le template HTML pour le PDF
        $projectPublic = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public';

        // Collect local image paths to inline as base64 (to avoid remote fetches and speed up rendering)
        $localPaths = [];

        foreach ($recipe->getIngredients() as $ingredient) {
            if ($ingredient->getPictogram()) {
                $localPaths[] = ltrim($ingredient->getPictogram()->getFilePath(), '/');
            } elseif ($ingredient->getPictogramUrl() && !str_starts_with($ingredient->getPictogramUrl(), 'http')) {
                $localPaths[] = ltrim(parse_url($ingredient->getPictogramUrl(), PHP_URL_PATH) ?: $ingredient->getPictogramUrl(), '/');
            }
        }

        foreach ($recipe->getUtensils() as $utensil) {
            if ($utensil->getPictogram()) {
                $localPaths[] = ltrim($utensil->getPictogram()->getFilePath(), '/');
            } elseif ($utensil->getPictogramUrl() && !str_starts_with($utensil->getPictogramUrl(), 'http')) {
                $localPaths[] = ltrim(parse_url($utensil->getPictogramUrl(), PHP_URL_PATH) ?: $utensil->getPictogramUrl(), '/');
            }
        }

        foreach ($recipe->getSteps() as $step) {
            if ($step->getPictogram()) {
                $localPaths[] = ltrim($step->getPictogram()->getFilePath(), '/');
            }
            if ($step->getPictogramUrl() && !str_starts_with($step->getPictogramUrl(), 'http')) {
                $localPaths[] = ltrim(parse_url($step->getPictogramUrl(), PHP_URL_PATH) ?: $step->getPictogramUrl(), '/');
            }
            if ($step->getPictogramUrls()) {
                foreach ($step->getPictogramUrls() as $u) {
                    if ($u && !str_starts_with($u, 'http')) {
                        $localPaths[] = ltrim(parse_url($u, PHP_URL_PATH) ?: $u, '/');
                    }
                }
            }
        }

        // Unique and filter
        $localPaths = array_values(array_unique(array_filter($localPaths)));

        $inlineImages = $this->buildInlineImages($localPaths, $projectPublic);

        $html = $this->renderView('recipe/pdf.html.twig', [
            'recipe' => $recipe,
            // fournir le chemin absolu du dossier public pour permettre l'accès aux fichiers locaux via file:// si nécessaire
            'project_public_dir' => $projectPublic,
            // map of relative path => data:image... base64
            'inline_images' => $inlineImages,
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

    /**
     * Build a map of relative file path => data URI (base64) for small local images.
     * This avoids multiple file:// reads and remote HTTP fetches when generating PDFs.
     * We limit inlining to files under public and with a reasonable size to avoid blowing HTML memory.
     *
     * @param string[] $relativePaths
     * @param string $projectPublicDir
     * @return array<string,string>
     */
    private function buildInlineImages(array $relativePaths, string $projectPublicDir): array
    {
        $map = [];
        $maxBytes = 200 * 1024; // 200 KB max per image to inline

        foreach ($relativePaths as $rel) {
            $clean = ltrim($rel, '/');
            $abs = $projectPublicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
            if (!file_exists($abs) || !is_readable($abs)) {
                continue;
            }
            $size = filesize($abs);
            if ($size === false || $size > $maxBytes) {
                // skip large files
                continue;
            }

            // detect mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $abs) : mime_content_type($abs);
            if ($finfo) {
                finfo_close($finfo);
            }

            if (! $mime) {
                // fallback by extension
                $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                switch ($ext) {
                    case 'svg': $mime = 'image/svg+xml'; break;
                    case 'png': $mime = 'image/png'; break;
                    case 'jpg': case 'jpeg': $mime = 'image/jpeg'; break;
                    case 'webp': $mime = 'image/webp'; break;
                    case 'gif': $mime = 'image/gif'; break;
                    default: $mime = 'application/octet-stream';
                }
            }

            $data = file_get_contents($abs);
            if ($data === false) {
                continue;
            }

            $base = base64_encode($data);
            $map[$clean] = sprintf('data:%s;base64,%s', $mime, $base);
        }

        return $map;
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
