<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Options;
use function array_filter;
use function array_map;
use function array_values;
use function ctype_digit;
use function explode;
use function preg_replace;
use function strtoupper;
use function trim;

/**
 * Gestisce le liste Brevo e la risoluzione delle liste in base a lingua/prefisso.
 * Estratto da AutomationService.php per migliorare modularità.
 */
final class ListManager
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * @return array<int>
     */
    public function defaultListIds(): array
    {
        $settings = $this->options->getGroup('fp_resv_brevo', []);
        $value    = (string) ($settings['brevo_list_id'] ?? '');
        if ($value === '') {
            return [];
        }

        $ids    = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $id): bool => $id !== ''));
        $result = [];

        foreach ($ids as $id) {
            if ($id === '') {
                continue;
            }

            if (!ctype_digit($id)) {
                $id = preg_replace('/[^0-9]/', '', $id);
                if (!is_string($id) || $id === '') {
                    continue;
                }
            }

            $intId = (int) $id;
            if ($intId > 0) {
                $result[] = $intId;
            }
        }

        return $result;
    }

    public function listIdForKey(string $key): ?int
    {
        $settings   = $this->options->getGroup('fp_resv_brevo', []);
        $key        = strtoupper($key);
        $candidates = [];

        if ($key === 'IT') {
            $candidates[] = (string) ($settings['brevo_list_id_it'] ?? '');
        } elseif ($key === 'EN') {
            $candidates[] = (string) ($settings['brevo_list_id_en'] ?? '');
        } else {
            $candidates[] = (string) ($settings['brevo_list_id_en'] ?? '');
            $candidates[] = (string) ($settings['brevo_list_id_it'] ?? '');
        }

        $candidates[] = (string) ($settings['brevo_list_id'] ?? '');

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (!ctype_digit($candidate)) {
                $candidate = preg_replace('/[^0-9]/', '', $candidate);
                if (!is_string($candidate) || $candidate === '') {
                    continue;
                }
            }

            $listId = (int) $candidate;
            if ($listId > 0) {
                return $listId;
            }
        }

        return null;
    }

    public function resolveListKey(string $forced, string $phoneCountry, string $pageLanguage): string
    {
        if ($forced !== '') {
            return $forced;
        }

        if ($phoneCountry === 'IT' || $phoneCountry === 'EN') {
            return $phoneCountry;
        }

        if ($pageLanguage === 'IT' || $pageLanguage === 'EN') {
            return $pageLanguage;
        }

        return 'INT';
    }
}
















