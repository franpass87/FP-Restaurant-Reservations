<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

/**
 * Wrapper type-safe per Options che fornisce metodi tipizzati.
 * Riduce casting manuale e migliora type safety.
 */
final class SettingsReader
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Ottiene un campo come valore generico.
     *
     * @param string $group Gruppo opzioni (es: 'fp_resv_general')
     * @param string $field Nome campo
     * @param mixed $default Valore di default
     * @return mixed Valore del campo
     */
    public function get(string $group, string $field, mixed $default = null): mixed
    {
        return $this->options->getField($group, $field, $default);
    }

    /**
     * Ottiene un campo come stringa.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @param string $default Valore di default
     * @return string Valore del campo come stringa
     */
    public function getString(string $group, string $field, string $default = ''): string
    {
        $value = $this->options->getField($group, $field, $default);

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * Ottiene un campo come intero.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @param int $default Valore di default
     * @return int Valore del campo come intero
     */
    public function getInt(string $group, string $field, int $default = 0): int
    {
        $value = $this->options->getField($group, $field, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Ottiene un campo come float.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @param float $default Valore di default
     * @return float Valore del campo come float
     */
    public function getFloat(string $group, string $field, float $default = 0.0): float
    {
        $value = $this->options->getField($group, $field, $default);

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && $value !== '') {
            return (float) str_replace(',', '.', $value);
        }

        return $default;
    }

    /**
     * Ottiene un campo come booleano.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @param bool $default Valore di default
     * @return bool Valore del campo come booleano
     */
    public function getBool(string $group, string $field, bool $default = false): bool
    {
        $value = $this->options->getField($group, $field, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $default;
    }

    /**
     * Ottiene un campo come array.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @param array<int|string, mixed> $default Valore di default
     * @return array<int|string, mixed> Valore del campo come array
     */
    public function getArray(string $group, string $field, array $default = []): array
    {
        $value = $this->options->getField($group, $field, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Ottiene un gruppo completo di opzioni.
     *
     * @param string $group Gruppo opzioni
     * @param array<string, mixed> $defaults Valori di default
     * @return array<string, mixed> Gruppo opzioni
     */
    public function getGroup(string $group, array $defaults = []): array
    {
        return $this->options->getGroup($group, $defaults);
    }

    /**
     * Verifica se un campo esiste e ha un valore non vuoto.
     *
     * @param string $group Gruppo opzioni
     * @param string $field Nome campo
     * @return bool True se il campo esiste e non è vuoto
     */
    public function has(string $group, string $field): bool
    {
        $value = $this->options->getField($group, $field, null);

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
















