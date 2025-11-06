<?php

namespace App\Form;

use App\Entity\Ingredient;
use App\Entity\Pictogram;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'input input-bordered w-full', 'placeholder' => 'Nom de l\'ingrédient'],
            ])
            ->add('amount', NumberType::class, [
                'scale' => 2,
                'attr' => ['class' => 'input input-bordered w-28', 'step' => '0.01'],
            ])
            ->add('unit', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-32', 'placeholder' => 'g, ml, pcs...'],
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['class' => 'position-field'],
            ])
            ->add('pictogramUrl', HiddenType::class, [
                'required' => false,
            ])
            // pictogram relation intentionally not rendered as a form field here.
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ingredient::class,
        ]);
    }
}
