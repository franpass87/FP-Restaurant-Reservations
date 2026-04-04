<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_action;
use function current_user_can;
use function esc_html__;
use function is_admin;

/**
 * Avviso in admin se FP Marketing Tracking Layer non è caricato (evita deploy senza layer → percorsi legacy).
 */
final class TrackingLayerAdminNotice
{
    /**
     * Registra l'hook admin (idempotente per istanza richiesta).
     */
    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_notices', [self::class, 'render'], 20);
    }

    /**
     * Mostra notice warning per utenti che gestiscono il plugin.
     */
    public static function render(): void
    {
        if (!current_user_can(Roles::MANAGE_RESERVATIONS) && !current_user_can('manage_options')) {
            return;
        }

        if (\function_exists('fp_tracking_get_brevo_settings')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'FP Restaurant Reservations: il plugin FP Marketing Tracking Layer non risulta attivo. Senza di esso non sono disponibili il tracking centralizzato, GTM dal layer e le integrazioni Brevo delegate; in produzione può attivarsi un comportamento legacy. Mantieni il layer attivo sugli ambienti che usano lo stack FP marketing.',
            'fp-restaurant-reservations'
        );
        echo '</p></div>';
    }
}
