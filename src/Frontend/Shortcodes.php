<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function add_shortcode;
use function current_user_can;
use function esc_html;
use function error_log;
use function wp_date;
use function wp_get_current_user;

final class Shortcodes
{
    private static ?ShortcodeRenderer $renderer = null;
    private static ?DiagnosticShortcode $diagnostic = null;

    public function __construct(
        ?ShortcodeRenderer $renderer = null,
        ?DiagnosticShortcode $diagnostic = null
    ) {
        if ($renderer !== null) {
            self::$renderer = $renderer;
        }
        if ($diagnostic !== null) {
            self::$diagnostic = $diagnostic;
        }
    }

    public static function register(): void
    {
        error_log('[FP-RESV-SHORTCODE] register() method called');
        add_shortcode('fp_reservations', [self::class, 'render']);
        add_shortcode('fp_resv_debug', [self::class, 'renderDebug']);
        add_shortcode('fp_resv_test', [self::class, 'renderTest']);
        error_log('[FP-RESV-SHORTCODE] Shortcodes registered');
        
        // Verifica che lo shortcode sia effettivamente registrato
        global $shortcode_tags;
        if (isset($shortcode_tags['fp_reservations'])) {
            error_log('[FP-RESV-SHORTCODE] ✅ Shortcode fp_reservations VERIFICATO registrato');
        } else {
            error_log('[FP-RESV-SHORTCODE] ❌ ERRORE: Shortcode fp_reservations NON registrato!');
        }
    }

    private static function getRenderer(): ShortcodeRenderer
    {
        if (self::$renderer === null) {
            self::$renderer = new ShortcodeRenderer();
        }
        return self::$renderer;
    }

    private static function getDiagnostic(): DiagnosticShortcode
    {
        if (self::$diagnostic === null) {
            self::$diagnostic = new DiagnosticShortcode();
        }
        return self::$diagnostic;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = []): string
    {
        return self::getRenderer()->render($atts);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderBlock(array $attributes = [], string $content = ''): string
    {
        unset($content);
        return self::getRenderer()->renderBlock($attributes);
    }

    /**
     * Shortcode di test super semplice: [fp_resv_test]
     */
    public static function renderTest(): string
    {
        error_log('[FP-RESV-TEST] Test shortcode called!');
        
        $timestamp = wp_date('Y-m-d H:i:s');
        $user = wp_get_current_user();
        $isAdmin = current_user_can('manage_options') ? 'SÌ' : 'NO';
        
        return '<div style="background:#e7f5ff;border:2px solid #339af0;padding:20px;margin:20px 0;border-radius:8px;font-family:sans-serif;">' .
               '<h3 style="color:#1971c2;margin-top:0;">✅ Test Shortcode FP Restaurant Reservations</h3>' .
               '<p><strong>Timestamp:</strong> ' . esc_html($timestamp) . '</p>' .
               '<p><strong>Utente:</strong> ' . esc_html($user->user_login ?: 'Non loggato') . '</p>' .
               '<p><strong>Sei amministratore:</strong> ' . $isAdmin . '</p>' .
               '<p style="margin-bottom:0;"><strong>Stato:</strong> <span style="color:#2f9e44;font-weight:bold;">Lo shortcode funziona! ✅</span></p>' .
               '</div>';
    }

    /**
     * Shortcode diagnostico: [fp_resv_debug]
     */
    public static function renderDebug(): string
    {
        return self::getDiagnostic()->render();
    }
}
