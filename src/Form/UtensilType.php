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
			->add('pictogram', EntityType::class, [
				'class' => Pictogram::class,
				'choice_label' => 'name',
				'placeholder' => 'Choisir un pictogramme',
				'required' => false,
				'attr' => ['class' => 'select select-bordered w-full']
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Utensil::class,
		]);
	}
}
