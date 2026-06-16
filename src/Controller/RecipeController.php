<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Entity\Recipe;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Service\PdfGenerator;
use App\Service\MarmitonApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
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
    private const PDF_GENERATION_TIMEOUT_SECONDS = 120;
    private const PDF_LOCK_WAIT_SECONDS = 25;

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
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Repository\PictogramRepository $pictogramRepository,
        #[Autowire(service: 'monolog.logger')]
        LoggerInterface $logger
    ): Response
    {
        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);

        if (!$request->isMethod('POST')) {
            $logger->info('recipe.new.opened', $this->requestLogContext($request));
        } else {
            $logger->info('recipe.new.submitted', array_merge(
                $this->requestLogContext($request),
                $this->submittedRecipeLogContext($request)
            ));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info('recipe.new.valid', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe)
            ));

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

            foreach ($this->recipeStepsLogContext($recipe) as $stepContext) {
                $logger->info('recipe.step.before_save', array_merge(
                    $this->requestLogContext($request),
                    ['recipe_id' => $recipe->getId()],
                    $stepContext
                ));
            }

            $entityManager->flush();

            $logger->info('recipe.new.saved', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe)
            ));

            $logger->info('recipe.new.redirect', array_merge(
                $this->requestLogContext($request),
                [
                    'recipe_id' => $recipe->getId(),
                    'route_to' => 'app_recipe_preview',
                    'status' => Response::HTTP_SEE_OTHER,
                ]
            ));

            // Après création, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted()) {
            $logger->warning('recipe.new.invalid', array_merge(
                $this->requestLogContext($request),
                $this->formErrorsLogContext($form),
                $this->submittedRecipeLogContext($request)
            ));
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
    public function pdf(
        Recipe $recipe,
        PdfGenerator $pdfGenerator,
        Request $request,
        #[Autowire(service: 'monolog.logger')]
        LoggerInterface $logger
    ): Response
    {
        // Start timing
        $t0 = microtime(true);
        $this->raisePdfRuntimeLimit();
        $lockHandle = null;

        $baseContext = array_merge(
            $this->requestLogContext($request),
            [
                'recipe_id' => $recipe->getId(),
                'download' => $request->query->get('download'),
                'force' => $request->query->get('force') === '1',
            ]
        );

        $logger->info('recipe.pdf.requested', $baseContext);

        // Bloquer le prefetch du navigateur (hover/anticipation)
        $purpose = $request->headers->get('Purpose') ?: $request->headers->get('Sec-Purpose');
        if ($purpose === 'prefetch' || stripos((string)$purpose, 'prefetch') !== false) {
            $logger->info('recipe.pdf.skipped', array_merge($baseContext, [
                'reason' => 'prefetch_detected',
                'purpose' => $purpose,
            ]));
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // If not explicitly requested via ?download=1, do not generate the PDF (protect against prefetch/polls)
        if ($request->query->get('download') !== '1') {
            $logger->info('recipe.pdf.skipped', array_merge($baseContext, [
                'reason' => 'missing_download_flag',
                'status' => Response::HTTP_NO_CONTENT,
            ]));

            // Return 204 No Content to indicate nothing to download (fast response)
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        try {
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

            $logger->info('recipe.pdf.rendered_html', array_merge($baseContext, [
                'duration_ms' => round(($tRenderEnd - $tRenderStart) * 1000, 1),
                'html_bytes' => strlen($html),
            ]));

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
            $cacheContext = array_merge($baseContext, [
                'cache_path' => $cachePath,
                'hash_path' => $hashPath,
            ]);

            // If cached, check both last update timestamp and HTML hash; return cache if fresh
            if (!$force && file_exists($cachePath) && is_readable($cachePath)) {
                $cacheMTime = filemtime($cachePath);
                $updatedAt = $recipe->getUpdatedAt();
                $storedHash = is_readable($hashPath) ? @trim((string)file_get_contents($hashPath)) : null;

                $mtimeFresh = $cacheMTime !== false && (!$updatedAt instanceof \DateTimeInterface || $cacheMTime > $updatedAt->getTimestamp());
                $hashFresh = $storedHash && hash_equals($storedHash, $htmlHash);

                if ($mtimeFresh && $hashFresh) {
                    $logger->info('recipe.pdf.cache_hit', array_merge($cacheContext, [
                        'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                        'recipe_updated' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
                        'hash_match' => true,
                    ]));
                    return $this->pdfFileResponse($recipe, $cachePath, $htmlHash, $cacheMTime ?: null);
                }

                $logger->info('recipe.pdf.cache_miss', array_merge($cacheContext, [
                    'reason' => 'cache_outdated',
                    'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                    'recipe_updated' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
                    'hash_match' => $storedHash ? hash_equals($storedHash, $htmlHash) : null,
                ]));
            } else {
                $logger->info('recipe.pdf.cache_miss', array_merge($cacheContext, [
                    'reason' => $force ? 'forced' : 'cache_missing',
                ]));
            }

            $lockDir = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lock' . DIRECTORY_SEPARATOR . 'pdf';
            if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
                throw new \RuntimeException('Impossible de creer le dossier des verrous PDF.');
            }

            $lockPath = $lockDir . DIRECTORY_SEPARATOR . $recipe->getId() . '.lock';
            $lockHandle = @fopen($lockPath, 'c');
            if (!is_resource($lockHandle)) {
                throw new \RuntimeException('Impossible de créer le verrou de génération PDF.');
            }

            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                $logger->info('recipe.pdf.lock_wait', array_merge($cacheContext, [
                    'lock_path' => $lockPath,
                    'wait_seconds' => self::PDF_LOCK_WAIT_SECONDS,
                ]));

                $waitStart = microtime(true);
                while ((microtime(true) - $waitStart) < self::PDF_LOCK_WAIT_SECONDS) {
                    usleep(250000);
                    if (!$force && $this->isFreshPdfCache($cachePath, $hashPath, $htmlHash, $recipe)) {
                        $cacheMTime = filemtime($cachePath);
                        $logger->info('recipe.pdf.cache_hit_after_wait', array_merge($cacheContext, [
                            'wait_duration_ms' => round((microtime(true) - $waitStart) * 1000, 1),
                            'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                        ]));

                        fclose($lockHandle);

                        return $this->pdfFileResponse($recipe, $cachePath, $htmlHash, $cacheMTime ?: null);
                    }
                }

                $logger->warning('recipe.pdf.lock_busy', array_merge($cacheContext, [
                    'lock_path' => $lockPath,
                    'wait_duration_ms' => round((microtime(true) - $waitStart) * 1000, 1),
                    'status' => Response::HTTP_ACCEPTED,
                ]));

                fclose($lockHandle);
                $lockHandle = null;

                return new Response('PDF en cours de génération. Réessayez dans quelques secondes.', Response::HTTP_ACCEPTED, [
                    'Retry-After' => '5',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }

            if (!$force && $this->isFreshPdfCache($cachePath, $hashPath, $htmlHash, $recipe)) {
                $cacheMTime = filemtime($cachePath);
                $logger->info('recipe.pdf.cache_hit', array_merge($cacheContext, [
                    'cache_mtime' => $cacheMTime ? date('Y-m-d H:i:s', $cacheMTime) : null,
                    'hash_match' => true,
                    'after_lock' => true,
                ]));

                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                $lockHandle = null;

                return $this->pdfFileResponse($recipe, $cachePath, $htmlHash, $cacheMTime ?: null);
            }

            // Generate PDF via Browsershot/Chromium.
            $tPdfStart = microtime(true);
            $pdfOutput = $pdfGenerator->generateFromHtml($html, $projectPublic, self::PDF_GENERATION_TIMEOUT_SECONDS);
            $tPdfEnd = microtime(true);

            $pdfBytes = strlen($pdfOutput);
            $tEnd = microtime(true);
            $logger->info('recipe.pdf.generated', array_merge($cacheContext, [
                'generation_duration_ms' => round(($tPdfEnd - $tPdfStart) * 1000, 1),
                'total_duration_ms' => round(($tEnd - $t0) * 1000, 1),
                'pdf_bytes' => $pdfBytes,
            ]));

            $tmpPath = $cachePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (@file_put_contents($tmpPath, $pdfOutput, LOCK_EX) === false || !@rename($tmpPath, $cachePath)) {
                @unlink($tmpPath);
                throw new \RuntimeException("Impossible d'ecrire le cache PDF.");
            }
            @file_put_contents($hashPath, $htmlHash, LOCK_EX);

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            $lockHandle = null;

            // Return cached file response (serves the freshly created file)
            return $this->pdfFileResponse($recipe, $cachePath, $htmlHash, filemtime($cachePath) ?: null);
        } catch (\Throwable $e) {
            if (is_resource($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }

            $logger->error('recipe.pdf.failed', array_merge($baseContext, [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'duration_ms' => round((microtime(true) - $t0) * 1000, 1),
            ]));

            throw $e;
        }
    }

    #[Route('/{id<\d+>}/show', name: 'app_recipe_show', methods: ['GET'])]
    public function show(Recipe $recipe): Response
    {
        return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_recipe_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Recipe $recipe,
        EntityManagerInterface $entityManager,
        \App\Repository\PictogramRepository $pictogramRepository,
        #[Autowire(service: 'monolog.logger')]
        LoggerInterface $logger
    ): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);

        if (!$request->isMethod('POST')) {
            $logger->info('recipe.edit.opened', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe)
            ));
        } else {
            $logger->info('recipe.edit.submitted', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe),
                $this->submittedRecipeLogContext($request)
            ));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info('recipe.edit.valid', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe)
            ));

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

            foreach ($this->recipeStepsLogContext($recipe) as $stepContext) {
                $logger->info('recipe.step.before_save', array_merge(
                    $this->requestLogContext($request),
                    ['recipe_id' => $recipe->getId()],
                    $stepContext
                ));
            }

            $entityManager->flush();

            $logger->info('recipe.edit.saved', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe)
            ));

            $logger->info('recipe.edit.redirect', array_merge(
                $this->requestLogContext($request),
                [
                    'recipe_id' => $recipe->getId(),
                    'route_to' => 'app_recipe_preview',
                    'status' => Response::HTTP_SEE_OTHER,
                ]
            ));

            // Après mise à jour, afficher la prévisualisation avec les informations à jour
            return $this->redirectToRoute('app_recipe_preview', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted()) {
            $logger->warning('recipe.edit.invalid', array_merge(
                $this->requestLogContext($request),
                $this->recipeLogContext($recipe),
                $this->formErrorsLogContext($form),
                $this->submittedRecipeLogContext($request)
            ));
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestLogContext(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('request_id'),
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'content_length' => $this->contentLength($request),
            'is_xml_http_request' => $request->isXmlHttpRequest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipeLogContext(Recipe $recipe): array
    {
        return [
            'recipe_id' => $recipe->getId(),
            'ingredient_count' => $recipe->getIngredients()->count(),
            'step_count' => $recipe->getSteps()->count(),
            'utensil_count' => $recipe->getUtensils()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedRecipeLogContext(Request $request): array
    {
        $payload = $request->request->all();
        $recipePayload = $payload['recipe'] ?? [];
        $ingredients = is_array($recipePayload) && isset($recipePayload['ingredients']) && is_array($recipePayload['ingredients'])
            ? $recipePayload['ingredients']
            : [];
        $steps = is_array($recipePayload) && isset($recipePayload['steps']) && is_array($recipePayload['steps'])
            ? $recipePayload['steps']
            : [];
        $utensils = is_array($recipePayload) && isset($recipePayload['utensils']) && is_array($recipePayload['utensils'])
            ? $recipePayload['utensils']
            : [];

        return [
            'submitted_counts' => [
                'ingredients' => count($ingredients),
                'steps' => count($steps),
                'utensils' => count($utensils),
            ],
            'submitted_steps' => $this->submittedStepsLogContext($steps),
        ];
    }

    /**
     * @param array<mixed> $steps
     *
     * @return array<int, array<string, mixed>>
     */
    private function submittedStepsLogContext(array $steps): array
    {
        $submittedSteps = [];
        foreach ($steps as $index => $stepPayload) {
            if (!is_array($stepPayload)) {
                continue;
            }

            $pictogramUrls = $stepPayload['pictogramUrls'] ?? null;

            $submittedSteps[] = [
                'step_index' => is_numeric($index) ? (int) $index : $index,
                'position' => $stepPayload['position'] ?? null,
                'content_preview' => $this->previewText($stepPayload['content'] ?? null),
                'pictogramUrl' => $stepPayload['pictogramUrl'] ?? null,
                'pictogramUrls' => $pictogramUrls,
                'pictogram_count' => $this->countSubmittedPictogramUrls($pictogramUrls),
            ];
        }

        return $submittedSteps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recipeStepsLogContext(Recipe $recipe): array
    {
        $steps = [];
        foreach ($recipe->getSteps() as $index => $step) {
            $pictogramUrls = $step->getPictogramUrls() ?? [];

            $steps[] = [
                'step_id' => $step->getId(),
                'step_index' => $index,
                'position' => $step->getPosition(),
                'content_preview' => $this->previewText($step->getContent()),
                'pictogramUrl' => $step->getPictogramUrl(),
                'pictogramUrls' => $pictogramUrls,
                'pictogram_count' => count($pictogramUrls),
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private function formErrorsLogContext(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $errors[] = [
                'field' => $origin?->getName(),
                'message' => $error->getMessage(),
            ];
        }

        return [
            'form_error_count' => count($errors),
            'form_errors' => $errors,
        ];
    }

    private function previewText(mixed $value, int $maxLength = 80): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '...';
    }

    private function contentLength(Request $request): int
    {
        return max(0, (int) $request->headers->get('content-length', 0));
    }

    private function raisePdfRuntimeLimit(): void
    {
        if (!function_exists('set_time_limit')) {
            return;
        }

        try {
            @set_time_limit(self::PDF_GENERATION_TIMEOUT_SECONDS);
        } catch (\Throwable) {
            // Some hosts disable set_time_limit; Browsershot still has its own timeout.
        }
    }

    private function isFreshPdfCache(string $cachePath, string $hashPath, string $htmlHash, Recipe $recipe): bool
    {
        if (!file_exists($cachePath) || !is_readable($cachePath)) {
            return false;
        }

        $cacheMTime = filemtime($cachePath);
        $updatedAt = $recipe->getUpdatedAt();
        $storedHash = is_readable($hashPath) ? @trim((string) file_get_contents($hashPath)) : null;

        $mtimeFresh = $cacheMTime !== false && (!$updatedAt instanceof \DateTimeInterface || $cacheMTime > $updatedAt->getTimestamp());
        $hashFresh = is_string($storedHash) && $storedHash !== '' && hash_equals($storedHash, $htmlHash);

        return $mtimeFresh && $hashFresh;
    }

    private function pdfFileResponse(Recipe $recipe, string $cachePath, string $htmlHash, ?int $cacheMTime = null): BinaryFileResponse
    {
        $response = new BinaryFileResponse($cachePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string) $recipe->getTitle())) . '.pdf'
        );
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        if ($cacheMTime) {
            $response->setLastModified(\DateTime::createFromFormat('U', (string) $cacheMTime) ?: new \DateTime());
        }
        $response->setEtag('W/"' . $htmlHash . '"');

        return $response;
    }

    private function countSubmittedPictogramUrls(mixed $value): ?int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return 0;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? count($decoded) : null;
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
