<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Service\PdfGenerator;
use App\Service\MarmitonApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recipe')]
final class RecipeController extends AbstractController
{
    #[Route(name: 'app_recipe_index', methods: ['GET'])]
    public function index(Request $request, RecipeRepository $recipeRepository): Response
    {
        // Pagination & search
        $q = trim((string)$request->query->get('q', ''));
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = 8;

        $qb = $recipeRepository->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('r.title LIKE :q OR r.description LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $firstResult = ($page - 1) * $perPage;
        $qb->setFirstResult($firstResult)->setMaxResults($perPage);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($qb->getQuery());
        $total = count($paginator);
        $totalPages = (int)ceil($total / $perPage);

        return $this->render('recipe/index.html.twig', [
            'recipes' => iterator_to_array($paginator),
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/search', name: 'app_recipe_search', methods: ['GET'])]
    public function search(): Response
    {
        return $this->render('recipe/search.html.twig');
    }

    #[Route('/import', name: 'app_recipe_import', methods: ['GET'])]
    public function importUrl(): Response
    {
        return $this->render('recipe/import.html.twig');
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

            // mark updatedAt so cache invalidation works correctly
            $recipe->setUpdatedAt(new \DateTime());

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

            // update timestamp to indicate recipe changed
            $recipe->setUpdatedAt(new \DateTime());

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
    public function pdf(Recipe $recipe, PdfGenerator $pdfGenerator, Request $request, \Psr\Log\LoggerInterface $logger): Response
    {
        // Start timing
        $t0 = microtime(true);

        // Bloquer le prefetch du navigateur (hover/anticipation)
        $purpose = $request->headers->get('Purpose') ?: $request->headers->get('Sec-Purpose');
        if ($purpose === 'prefetch' || stripos((string)$purpose, 'prefetch') !== false) {
            $logger->info('PDF generation blocked (prefetch detected)', [
                'recipe_id' => $recipe->getId(),
                'purpose' => $purpose,
            ]);
            return new Response('', Response::HTTP_NO_CONTENT);
        }

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
        // Include the template source hash so layout-only changes invalidate cached PDFs.
        $templatePath = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'recipe' . DIRECTORY_SEPARATOR . 'pdf.html.twig';
        $templateHash = is_readable($templatePath) ? sha1_file($templatePath) : '';
        $htmlHash = sha1($html . '|' . ($templateHash ?: ''));
        $hashPath = $cachePath . '.sha1';
        $force = $request->query->get('force') === '1';

        // If cached, check both last update timestamp and HTML hash; return cache if fresh
        if (!$force && file_exists($cachePath) && is_readable($cachePath)) {
            $cacheMTime = filemtime($cachePath);
            $updatedAt = $recipe->getUpdatedAt();
            $storedHash = is_readable($hashPath) ? @trim((string)file_get_contents($hashPath)) : null;

            $mtimeFresh = $cacheMTime !== false && (!$updatedAt instanceof \DateTimeInterface || $cacheMTime > $updatedAt->getTimestamp());
            $hashFresh = $storedHash && hash_equals($storedHash, $htmlHash);

            if ($mtimeFresh && $hashFresh) {
                $logger->info('Returning cached PDF', [
                    'recipe_id' => $recipe->getId(),
                    'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                    'recipe_updated' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : 'null',
                    'hash_match' => true,
                ]);
                $response = new BinaryFileResponse($cachePath);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$recipe->getTitle())) . '.pdf');
                // Avoid browser-level caching of the download
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                if ($cacheMTime) {
                    $response->setLastModified(\DateTime::createFromFormat('U', (string)$cacheMTime) ?: new \DateTime());
                }
                $response->setEtag('W/"' . $htmlHash . '"');
                return $response;
            }

            $logger->info('Cache outdated, regenerating', [
                'recipe_id' => $recipe->getId(),
                'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                'recipe_updated' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : 'null',
                'hash_match' => $storedHash ? hash_equals($storedHash, $htmlHash) : null,
            ]);
        }

        // Generate PDF via DOMPDF
        $tPdfStart = microtime(true);
        $pdfOutput = $pdfGenerator->generateFromHtml($html, $projectPublic);
        $tPdfEnd = microtime(true);

        $pdfBytes = strlen($pdfOutput);
        $tEnd = microtime(true);
        $logger->info('PDF generation complete', ['recipe_id' => $recipe->getId(), 'total_dur_ms' => round(($tEnd - $t0) * 1000, 1), 'pdf_bytes' => $pdfBytes]);

        // Store in cache
        if ($pdfOutput !== false) {
            @file_put_contents($cachePath, $pdfOutput);
            @file_put_contents($hashPath, $htmlHash);
        }

        // Return cached file response (serves the freshly created file)
        $response = new BinaryFileResponse($cachePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$recipe->getTitle())) . '.pdf');
        // Avoid browser-level caching of the download
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->setEtag('W/"' . $htmlHash . '"');
        return $response;
    }

    #[Route('/{id<\d+>}/show', name: 'app_recipe_show', methods: ['GET'])]
    public function show(Recipe $recipe): Response
    {
        return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_recipe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $entityManager, \App\Repository\PictogramRepository $pictogramRepository, \Psr\Log\LoggerInterface $logger): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $payload = $request->request->all();

        if ($request->isMethod('POST')) {
            $logger->info('RecipeController::edit submitted step pictograms payload', $this->buildSubmittedStepsLogContext($recipe, $payload));
        }

        // Debug: log request payload for XHR posts to help diagnose invalid payloads
        if ($request->isXmlHttpRequest()) {
            // Convert request->request->all() to strings for safe logging
            $recipePayload = $payload['recipe'] ?? null;
            $logger->debug('RecipeController::edit XHR payload', [
                'keys' => array_keys($payload),
                'recipe_is_array' => is_array($recipePayload),
                'recipe_sample' => is_array($recipePayload) ? json_encode(array_slice($recipePayload, 0, 20)) : (string) $recipePayload,
            ]);
            // Log raw request content for additional debugging (truncate for safety)
            $logger->debug('RecipeController::edit XHR raw content', ['length' => strlen($request->getContent()), 'content' => substr($request->getContent(), 0, 10240)]);
        }
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

            // update timestamp to indicate recipe changed
            $recipe->setUpdatedAt(new \DateTime());

            $logger->info('RecipeController::edit step pictograms before flush', $this->buildRecipeStepsLogContext($recipe));

            $entityManager->flush();

            // Après mise à jour, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        // If XHR and invalid, return a helpful JSON payload for debugging purposes
        if ($request->isXmlHttpRequest()) {
            // Extract form errors safely
            $errors = array_map(function ($e) {
                if (is_object($e) && method_exists($e, 'getMessage')) {
                    return $e->getMessage();
                }
                return (string) $e;
            }, iterator_to_array($form->getErrors(true)));
            $errorText = implode('; ', $errors);
            $logger->warning('RecipeController::edit form invalid', ['errors' => $errors, 'submitted' => $request->request->all()]);
            return new JsonResponse(['ok' => false, 'errors' => $errors, 'errorText' => $errorText, 'keys' => array_keys($payload), 'recipe_is_array' => is_array($payload['recipe'] ?? null)], 422);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildSubmittedStepsLogContext(Recipe $recipe, array $payload): array
    {
        $recipePayload = $payload['recipe'] ?? [];
        $submittedSteps = is_array($recipePayload) && isset($recipePayload['steps']) && is_array($recipePayload['steps'])
            ? $recipePayload['steps']
            : [];

        $steps = [];
        foreach ($submittedSteps as $index => $stepPayload) {
            if (!is_array($stepPayload)) {
                continue;
            }

            $steps[] = [
                'index' => $index,
                'position' => $stepPayload['position'] ?? null,
                'content' => $stepPayload['content'] ?? null,
                'pictogramUrl' => $stepPayload['pictogramUrl'] ?? null,
                'pictogramUrls' => $stepPayload['pictogramUrls'] ?? null,
            ];
        }

        return [
            'recipe_id' => $recipe->getId(),
            'steps_count' => count($submittedSteps),
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecipeStepsLogContext(Recipe $recipe): array
    {
        $steps = [];
        foreach ($recipe->getSteps() as $step) {
            $steps[] = [
                'id' => $step->getId(),
                'position' => $step->getPosition(),
                'content' => $step->getContent(),
                'pictogramUrl' => $step->getPictogramUrl(),
                'pictogramUrls' => $step->getPictogramUrls(),
            ];
        }

        return [
            'recipe_id' => $recipe->getId(),
            'steps_count' => count($steps),
            'steps' => $steps,
        ];
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



    #[Route('/{id}', name: 'app_recipe_delete', methods: ['POST'])]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');

        if ($this->isCsrfTokenValid('delete' . $recipe->getId(), $token)) {
            $name = $recipe->getTitle();
            $entityManager->remove($recipe);
            $entityManager->flush();
            $this->addFlash('success', 'Recette "' . $name . '" supprimée.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
    }
}
