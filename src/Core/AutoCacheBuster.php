<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_action;
use function get_option;
use function update_option;

/**
 * Auto Cache Buster
 * 
 * Aggiorna automaticamente il timestamp della cache quando la versione del plugin cambia.
 * Questo forza i browser a ricaricare i file JavaScript e CSS senza bisogno di comandi manuali.
 */
final class AutoCacheBuster
{
    /**
     * Inizializza l'auto cache buster
     */
    public static function init(): void
    {
        // Verifica e aggiorna la cache ad ogni caricamento admin
        add_action('admin_init', [self::class, 'checkAndUpdate'], 1);
        
        // Anche sul frontend, ma solo una volta per sessione
        add_action('init', [self::class, 'checkAndUpdate'], 1);
    }
    
    /**
     * Verifica se la versione è cambiata e aggiorna il timestamp della cache
     */
    public static function checkAndUpdate(): void
    {
        $currentVersion = Plugin::VERSION;
        $savedVersion = get_option('fp_resv_current_version', '');
        
        // Se la versione è cambiata, aggiorna il timestamp della cache
        if ($savedVersion !== $currentVersion) {
            Logging::log('cache', 'Rilevato cambio versione - aggiornamento cache automatico', [
                'old_version' => $savedVersion,
                'new_version' => $currentVersion,
            ]);
            
            // Aggiorna il timestamp della cache
            update_option('fp_resv_last_upgrade', time(), false);
            
            // Salva la nuova versione
            update_option('fp_resv_current_version', $currentVersion, false);
            
            // Invalida tutte le cache
            CacheManager::invalidateAll();
            
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
    }
}
