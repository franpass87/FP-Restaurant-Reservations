<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Core\Consent;
use FP\Resv\Core\DataLayer;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Gestisce la cattura e il salvataggio dei parametri di attribuzione UTM.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class UTMAttributionHandler
{
    private const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'fbclid',
        'msclkid',
        'ttclid',
    ];

    /**
     * Cattura i parametri UTM dalla query string e li salva.
     *
     * @param int $cookieDays Giorni di validità del cookie
     */
    public function capture(int $cookieDays): void
    {
        $params = $this->extractParams();
        
        if ($params === []) {
            return;
        }

        if (!Consent::has('ads') && !Consent::has('analytics')) {
            return;
        }

        DataLayer::storeAttribution($params, $cookieDays);
    }

    /**
     * Estrae i parametri UTM dalla query string.
     *
     * @return array<string, string>
     */
    private function extractParams(): array
    {
        $params = [];

        foreach (self::UTM_KEYS as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            if ($value === '') {
                continue;
            }

            $params[$key] = $value;
        }

        return $params;
    }
}















