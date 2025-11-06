<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforme une chaîne JSON en tableau et vice-versa
 * Utilisé pour les champs qui stockent des arrays en JSON mais sont soumis comme string
 */
class JsonToArrayTransformer implements DataTransformerInterface
{
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

		return json_encode($value);
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
			// Si ce n'est pas du JSON valide, retourner null
			return null;
		}

		return $decoded;
	}
}
