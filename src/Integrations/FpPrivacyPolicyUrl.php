<?php

declare(strict_types=1);

namespace FP\Resv\Integrations;

use Throwable;

use function class_exists;
use function defined;
use function esc_url_raw;
use function get_locale;
use function get_option;
use function get_permalink;
use function is_string;

/**
 * Recupera l'URL della privacy policy da FP Privacy & Cookie Policy (stessa logica del footer del plugin).
 */
final class FpPrivacyPolicyUrl
{
    /**
     * Restituisce il permalink della pagina privacy per la lingua indicata (o {@see get_locale()}).
     *
     * Ordine: pagina FP Privacy per lingua normalizzata → fallback pagina privacy nativa di WordPress.
     */
    public static function resolveUrl(?string $locale = null): string
    {
        if (!defined('FP_PRIVACY_VERSION') || !class_exists(\FP\Privacy\Utils\Options::class)) {
            return '';
        }

        try {
            $options = \FP\Privacy\Utils\Options::instance();
        } catch (Throwable $e) {
            return '';
        }

        $rawLocale = $locale !== null && $locale !== '' ? $locale : get_locale();
        $norm      = $options->normalize_language((string) $rawLocale);

        $privacyPageId = $options->get_page_id('privacy_policy', $norm);
        if ($privacyPageId <= 0) {
            $privacyPageId = (int) get_option('wp_page_for_privacy_policy', 0);
        }

        if ($privacyPageId <= 0) {
            return '';
        }

        $permalink = get_permalink($privacyPageId);
        if (!is_string($permalink) || $permalink === '') {
            return '';
        }

        return esc_url_raw($permalink);
    }
}
