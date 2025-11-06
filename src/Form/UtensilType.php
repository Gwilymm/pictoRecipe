<?php

namespace App\Form;

use App\Entity\Utensil;
use App\Entity\Pictogram;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UtensilType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', TextType::class, [
				'label' => 'Nom de l\'ustensile',
				'attr' => ['class' => 'input input-bordered w-full', 'placeholder' => 'Ex: Fouet, Saladier...'],
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
			'data_class' => Utensil::class,
		]);
	}
}
