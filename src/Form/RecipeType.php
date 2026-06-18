<?php

namespace App\Form;

use App\Entity\Recipe;
use App\Entity\Utensil;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'attr' => ['class' => 'input input-bordered w-full'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'textarea textarea-bordered w-full', 'rows' => 4],
            ])
            ->add('servings', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-24'],
            ])
            ->add('prepTimeMinutes', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-24'],
            ])
            ->add('cookTimeMinutes', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-24'],
            ])
            // Ingredients collection
            ->add('ingredients', CollectionType::class, [
                'entry_type' => IngredientType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'entry_options' => ['label' => false],
            ])
            // Steps collection
            ->add('steps', CollectionType::class, [
                'entry_type' => StepType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'entry_options' => ['label' => false],
            ])
            // Utensils selection (ManyToMany) - render as expanded checkboxes so we can show pictograms
            ->add('utensils', EntityType::class, [
                'class' => Utensil::class,
                'choice_label' => 'name',
                'multiple' => true,
                // Render as individual checkbox inputs (expanded) so we can place images next to labels
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                // Expose the pictogram URL on each choice so the template can render the image
                'choice_attr' => function (?Utensil $choice, $key, $value) {
                    return $choice && $choice->getPictogramUrl() ? ['data-pictogram' => $choice->getPictogramUrl()] : [];
                },
                // Container styling for the expanded choices; individual items will be styled in the template
                'attr' => [
                    'class' => 'utensils-expanded-grid',
                ],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $event->setData($this->removeEmptySubmittedSteps($data));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }

    /**
     * Remove ghost collection rows that only contain technical empty fields.
     *
     * A step with pictograms but no text is intentionally kept so NotBlank can
     * report a real validation error instead of silently dropping user input.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function removeEmptySubmittedSteps(array $data): array
    {
        if (!isset($data['steps']) || !is_array($data['steps'])) {
            return $data;
        }

        foreach ($data['steps'] as $index => $stepPayload) {
            if ($this->isEmptySubmittedStep($stepPayload)) {
                unset($data['steps'][$index]);
            }
        }

        return $data;
    }

    private function isEmptySubmittedStep(mixed $stepPayload): bool
    {
        if (!is_array($stepPayload)) {
            return false;
        }

        return $this->isBlankSubmittedValue($stepPayload['content'] ?? null)
            && $this->isBlankSubmittedValue($stepPayload['durationMinutes'] ?? null)
            && $this->isBlankSubmittedValue($stepPayload['pictogramUrl'] ?? null)
            && !$this->hasSubmittedPictogramUrls($stepPayload['pictogramUrls'] ?? null);
    }

    private function isBlankSubmittedValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_scalar($value)) {
            return trim((string) $value) === '';
        }

        return false;
    }

    private function hasSubmittedPictogramUrls(mixed $value): bool
    {
        if (is_array($value)) {
            return $this->arrayContainsNonBlankValue($value);
        }

        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return true;
        }

        if (is_array($decoded)) {
            return $this->arrayContainsNonBlankValue($decoded);
        }

        return !$this->isBlankSubmittedValue($decoded);
    }

    /**
     * @param array<mixed> $values
     */
    private function arrayContainsNonBlankValue(array $values): bool
    {
        foreach ($values as $value) {
            if (!$this->isBlankSubmittedValue($value)) {
                return true;
            }
        }

        return false;
    }
}
