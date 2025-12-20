<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function do_shortcode;
use function error_log;
use function in_the_loop;
use function is_admin;
use function is_main_query;
use function strpos;

/**
 * Gestisce i filtri sul contenuto per forzare l'esecuzione degli shortcode.
 * Estratto da WidgetController per migliorare la manutenibilità.
 */
final class ContentFilter
{
    /**
     * Forza l'esecuzione dello shortcode anche se il tema non chiama the_content() correttamente.
     */
    public function forceShortcodeExecution(string $content): string
    {
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        // Se il contenuto contiene lo shortcode ma non è stato processato, forzalo
        if (strpos($content, '[fp_reservations') !== false && strpos($content, 'fp-resv-widget') === false) {
            error_log('[FP-RESV] Forcing shortcode execution in content filter');
            $content = do_shortcode($content);
        }
        
        return $content;
    }
}















