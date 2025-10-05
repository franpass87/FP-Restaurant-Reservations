<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Validator
{
	/**
	 * Sanitize recursively all scalar string values inside the provided array.
	 * - Strings are sanitized with sanitize_text_field
	 * - Numeric/booleans/null are returned as-is
	 * - Arrays are traversed recursively
	 *
	 * @param array<string|int, mixed> $data
	 * @return array<string|int, mixed>
	 */
	public static function sanitize(array $data): array
	{
		$sanitized = [];
		foreach ($data as $key => $value) {
			$sanitized[$key] = self::sanitizeValue($value);
		}

		return $sanitized;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function sanitizeValue(mixed $value): mixed
	{
		if (is_array($value)) {
			$clean = [];
			foreach ($value as $k => $v) {
				$clean[$k] = self::sanitizeValue($v);
			}

			return $clean;
		}

		if (is_string($value)) {
			return \sanitize_text_field($value);
		}

		// Preserve numbers, booleans and nulls as-is
		return $value;
	}
}
