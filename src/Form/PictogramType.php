<?php

namespace App\Form;

use App\Entity\Pictogram;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PictogramType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', TextType::class, [
				'attr' => ['class' => 'input input-bordered w-full']
			])
			->add('imageFile', FileType::class, [
				'label' => 'Image (PNG ou SVG)',
				'mapped' => false,
				'required' => $options['require_image'],
				'constraints' => [
					new File([
						'maxSize' => '2M',
						'mimeTypes' => [
							'image/png',
							'image/svg+xml',
						],
						'mimeTypesMessage' => 'Veuillez uploader un fichier PNG ou SVG valide.',
					])
				],
				'attr' => ['class' => 'file-input file-input-bordered w-full max-w-xs']
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Pictogram::class,
			'require_image' => true,
		]);
	}
}
