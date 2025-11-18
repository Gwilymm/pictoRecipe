<?php

namespace App\Controller;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Step;
use App\Entity\Utensil;
use App\Repository\UtensilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/recipes', name: 'api_recipes_')]
class ApiRecipeImportController extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly UtensilRepository $utensilRepository,
		private readonly LoggerInterface $logger,
	) {}

	/**
	 * Import a recipe from Marmiton JSON structure
	 */
	#[Route('/import', name: 'import', methods: ['POST'])]
	public function import(Request $request): JsonResponse
	{
		try {
			$payload = json_decode($request->getContent(), true);

			if (!\is_array($payload)) {
				return $this->badRequest('Invalid JSON body');
			}

			// Accept both full Marmiton payload { ok, recipe } and direct recipe object
			$recipeData = $payload['recipe'] ?? $payload;

			// Validate minimal structure
			$errors = $this->validateIncomingRecipe($recipeData);
			if ($errors) {
				return new JsonResponse([
					'ok' => false,
					'error' => 'Invalid recipe payload',
					'details' => $errors,
				], Response::HTTP_BAD_REQUEST);
			}

			$this->logger->info('Starting import of Marmiton recipe', [
				'title' => $recipeData['title'] ?? null,
				'ingredients_count' => \count($recipeData['ingredients'] ?? []),
				'steps_count' => \count($recipeData['steps'] ?? []),
				'utensils_count' => \count($recipeData['utensils'] ?? []),
			]);

			$recipe = new Recipe();
			$recipe->setTitle((string) $recipeData['title']);

			// parse servings if provided
			if (isset($recipeData['servings'])) {
				$servings = $this->parseServings($recipeData['servings']);
				if ($servings !== null) {
					$recipe->setServings($servings);
				}
			}

			// Map times if present
			$times = $recipeData['times'] ?? null;
			if (\is_array($times)) {
				$details = $times['details'] ?? [];
				if (\is_array($details)) {
					foreach ($details as $d) {
						$label = isset($d['label']) ? mb_strtolower((string) $d['label']) : '';
						$value = isset($d['value']) ? (string) $d['value'] : '';
						if ($label !== '' && $value !== '') {
							$minutes = $this->parseDurationToMinutes($value);
							if (str_contains($label, 'prépar')) {
								$recipe->setPrepTimeMinutes($minutes);
							} elseif (str_contains($label, 'cuisson')) {
								$recipe->setCookTimeMinutes($minutes);
							}
						}
					}
				}
			}

			// Ingredients with positions
			$position = 0;
			foreach (($recipeData['ingredients'] ?? []) as $ing) {
				$ingredient = new Ingredient();
				$ingredient->setName((string) ($ing['name'] ?? 'Ingrédient'));

				$rawAmount = (string) ($ing['quantity'] ?? '0');
				$amount = $this->normalizeAmount($rawAmount);
				$ingredient->setAmount($amount);

				$unit = $ing['unit'] ?? null;
				$ingredient->setUnit($unit !== '' ? (string) $unit : null);

				// Optional section/group (e.g., Marmiton: mrtn-recette_ingredients-items-group-title)
				$section = $ing['group'] ?? $ing['section'] ?? null;
				if (is_string($section)) {
					$section = trim($section);
				}
				$ingredient->setSection($section !== '' ? $section : null);

				$ingredient->setPosition($position++);
				$recipe->addIngredient($ingredient);
			}

			// Steps with positions
			$position = 0;
			foreach (($recipeData['steps'] ?? []) as $st) {
				$step = new Step();
				$step->setPosition($position++);
				$content = (string) ($st['text'] ?? $st['content'] ?? '');
				$step->setContent($content);
				$recipe->addStep($step);
			}

			// Utensils (optional): find or create by name (case-insensitive)
			foreach (($recipeData['utensils'] ?? []) as $ut) {
				$name = trim((string) ($ut['name'] ?? ''));
				if ($name === '') {
					continue;
				}
				// Case-insensitive search to avoid duplicates like "Four" vs "four"
				$existing = $this->utensilRepository->createQueryBuilder('u')
					->where('LOWER(u.name) = LOWER(:name)')
					->setParameter('name', $name)
					->setMaxResults(1)
					->getQuery()
					->getOneOrNullResult();

				if ($existing) {
					$recipe->addUtensil($existing);
				} else {
					$utensil = (new Utensil())->setName($name);
					$this->em->persist($utensil);
					$recipe->addUtensil($utensil);
				}
			}

			$this->em->persist($recipe);
			$this->em->flush();

			$this->logger->info('Recipe imported successfully', [
				'recipe_id' => $recipe->getId(),
				'title' => $recipe->getTitle(),
			]);

			return new JsonResponse([
				'ok' => true,
				'id' => $recipe->getId(),
			], Response::HTTP_CREATED);
		} catch (\Throwable $e) {
			$this->logger->error('Recipe import failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return new JsonResponse([
				'ok' => false,
				'error' => 'Import failed: ' . $e->getMessage(),
			], Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	private function parseServings(mixed $value): ?int
	{
		if ($value === null) return null;
		if (is_int($value)) return $value > 0 ? $value : null;
		$text = (string) $value;
		if (preg_match('/(\d+)/u', $text, $m)) {
			$val = (int) $m[1];
			return $val > 0 ? $val : null;
		}
		return null;
	}

	private function badRequest(string $message): JsonResponse
	{
		return new JsonResponse([
			'ok' => false,
			'error' => $message,
		], Response::HTTP_BAD_REQUEST);
	}

	/**
	 * Validate minimal structure for Marmiton recipe payload
	 * @return array<string, string> errors map
	 */
	private function validateIncomingRecipe(?array $recipe): array
	{
		$errors = [];
		if (!\is_array($recipe)) {
			$errors['recipe'] = 'Missing recipe object';
			return $errors;
		}

		if (empty($recipe['title'])) {
			$errors['title'] = 'Title is required';
		}

		if (!isset($recipe['ingredients']) || !\is_array($recipe['ingredients']) || count($recipe['ingredients']) === 0) {
			$errors['ingredients'] = 'At least one ingredient is required';
		} else {
			foreach ($recipe['ingredients'] as $idx => $ing) {
				if (empty($ing['name'])) {
					$errors["ingredients.$idx.name"] = 'Ingredient name is required';
				}
			}
		}

		if (!isset($recipe['steps']) || !\is_array($recipe['steps']) || count($recipe['steps']) === 0) {
			$errors['steps'] = 'At least one step is required';
		} else {
			foreach ($recipe['steps'] as $idx => $st) {
				$content = $st['text'] ?? $st['content'] ?? '';
				if (trim((string) $content) === '') {
					$errors["steps.$idx.text"] = 'Step text is required';
				}
			}
		}

		return $errors;
	}

	private function parseDurationToMinutes(string $value): int
	{
		$value = trim(mb_strtolower($value));
		$minutes = 0;

		// Extract hours and minutes separately so stray text (like "Préparation : 20 min")
		// doesn't prevent matching. We prefer explicit "X h" and "Y min" groups,
		// falling back to any first integer found.
		$h = 0;
		$min = 0;

		if (preg_match('/(\d+)\s*h(?:eures?)?/u', $value, $mh)) {
			$h = (int) $mh[1];
		}
		if (preg_match('/(\d+)\s*min(?:utes?)?/u', $value, $mm)) {
			$min = (int) $mm[1];
		}

		if ($h === 0 && $min === 0) {
			// fallback: any integer in the string
			if (preg_match('/(\d{1,3})/u', $value, $m2)) {
				$min = (int) $m2[1];
			}
		}

		$minutes = $h * 60 + $min;

		return max(0, $minutes);
	}

	private function normalizeAmount(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '0.00';
		}
		// Replace comma by dot and extract numeric part
		$raw = str_replace(',', '.', $raw);
		if (!is_numeric($raw)) {
			// Try to capture number from "1 1/2" or similar patterns
			if (preg_match('/\d+(?:[\.,]\d+)?/u', $raw, $m)) {
				$raw = str_replace(',', '.', $m[0]);
			} else {
				return '0.00';
			}
		}
		return number_format((float) $raw, 2, '.', '');
	}
}
