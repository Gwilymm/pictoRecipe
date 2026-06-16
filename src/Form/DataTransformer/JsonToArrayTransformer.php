<?php

namespace App\Form\DataTransformer;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforme une chaîne JSON en tableau et vice-versa
 * Utilisé pour les champs qui stockent des arrays en JSON mais sont soumis comme string
 */
class JsonToArrayTransformer implements DataTransformerInterface
{
	public function __construct(
		#[Autowire(service: 'monolog.logger')]
		private readonly ?LoggerInterface $logger = null,
	) {}

	/**
	 * Transforme un array en string JSON (pour l'affichage dans le formulaire)
	 *
	 * @param array|null $value
	 * @return string
	 */
	public function transform($value): string
	{
		if (null === $value || empty($value)) {
			return '';
		}

		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value);

		if (false === $json) {
			throw new TransformationFailedException(sprintf(
				'Impossible de convertir le tableau en JSON: %s',
				json_last_error_msg()
			));
		}

		return $json;
	}

	/**
	 * Transforme une string JSON en array (depuis le formulaire soumis)
	 *
	 * @param string|null $value
	 * @return array|null
	 */
	public function reverseTransform($value): ?array
	{
		if (null === $value || '' === $value) {
			return null;
		}

		if (is_array($value)) {
			return $value;
		}

		$decoded = json_decode($value, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->logger?->warning('pictogram_urls.transform.invalid_json', [
				'error' => json_last_error_msg(),
				'value_length' => is_string($value) ? strlen($value) : null,
			]);

			throw new TransformationFailedException(sprintf(
				'JSON invalide pour le champ pictogrammes: %s',
				json_last_error_msg()
			));
		}

		if (!is_array($decoded)) {
			$this->logger?->warning('pictogram_urls.transform.invalid_json', [
				'error' => 'decoded_value_is_not_array',
				'decoded_type' => get_debug_type($decoded),
				'value_length' => is_string($value) ? strlen($value) : null,
			]);

			throw new TransformationFailedException('Le champ pictogrammes doit contenir un tableau JSON.');
		}

		return $decoded;
	}
}
