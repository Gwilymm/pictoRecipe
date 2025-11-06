<?php

namespace App\Form;

use App\Entity\Recipe;
use App\Entity\Utensil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
