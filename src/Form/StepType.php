<?php

namespace App\Form;

use App\Entity\Step;
use App\Entity\Pictogram;
use App\Form\DataTransformer\JsonToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'attr' => ['class' => 'textarea textarea-bordered w-full', 'rows' => 3],
            ])
            ->add('durationMinutes', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'input input-bordered w-28', 'placeholder' => 'min'],
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['class' => 'position-field'],
            ])
            ->add('pictogramUrl', HiddenType::class, [
                'required' => false,
            ])
            ->add('pictogramUrls', HiddenType::class, [
                'required' => false,
                'attr' => ['data-pictograms' => 'multiple'],
            ])
            // pictogram relation intentionally not rendered as a form field here.
        ;

        // Ajouter le transformer pour convertir JSON <-> Array
        $builder->get('pictogramUrls')
            ->addModelTransformer(new JsonToArrayTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Step::class,
        ]);
    }
}
