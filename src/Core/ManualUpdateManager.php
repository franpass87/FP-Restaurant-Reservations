<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function add_action;
use function current_user_can;
use function check_admin_referer;
use function wp_safe_redirect;
use function admin_url;
use function set_site_transient;
use function delete_site_transient;

/**
 * Gestisce gli aggiornamenti MANUALI del plugin.
 * 
 * Questa classe impedisce aggiornamenti automatici che potrebbero causare
 * problemi sui siti dei clienti in produzione. Gli aggiornamenti devono
 * essere sempre attivati manualmente dall'amministratore.
 */
final class ManualUpdateManager
{
    private const CAPABILITY = 'update_plugins';

    public function register(): void
    {
        // Handler per il check manuale degli aggiornamenti
        add_action('admin_post_fp_resv_check_updates', [$this, 'handleCheckUpdates']);
        
        // Disabilita i check automatici periodici
        add_filter('auto_update_plugin', [$this, 'disableAutoUpdate'], 999, 2);
    }

    /**
     * Gestisce la richiesta di check manuale degli aggiornamenti.
     */
    public function handleCheckUpdates(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_safe_redirect(admin_url('admin.php?page=fp-reservations-settings'));
            exit;
        }

        check_admin_referer('fp_resv_check_updates');

        global $fp_resv_update_checker;

        if ($fp_resv_update_checker !== null) {
            // Forza un check immediato degli aggiornamenti
            // Rimuove la cache temporanea per forzare una nuova richiesta a GitHub
            delete_site_transient('update_plugins');
            
            // Esegue il check degli aggiornamenti
            $fp_resv_update_checker->checkForUpdates();
            
            // Salva un flag per mostrare il messaggio di conferma
            set_site_transient('fp_resv_update_checked', true, 30);
        }

        wp_safe_redirect(admin_url('admin.php?page=fp-reservations-settings&update-checked=1'));
        exit;
    }

    /**
     * Disabilita completamente l'auto-update per questo plugin.
     *
     * @param bool $update Se il plugin dovrebbe essere auto-aggiornato
     * @param object $item L'item del plugin
     * @return bool False per disabilitare l'auto-update
     */
    public function disableAutoUpdate(bool $update, object $item): bool
    {
        if (isset($item->plugin) && $item->plugin === 'fp-restaurant-reservations/fp-restaurant-reservations.php') {
            return false;
        }
        return $update;
    }

    /**
     * Verifica se ci sono aggiornamenti disponibili.
     *
     * @return array{available: bool, current: string, latest: string, url: string}|null
     */
    public static function getUpdateInfo(): ?array
    {
        global $fp_resv_update_checker;

        if ($fp_resv_update_checker === null) {
            return null;
        }

        $update = $fp_resv_update_checker->getUpdate();

        if ($update === null) {
            return [
                'available' => false,
                'current'   => Plugin::version(),
                'latest'    => Plugin::version(),
                'url'       => '',
            ];
        }

        return [
            'available' => true,
            'current'   => Plugin::version(),
            'latest'    => $update->version ?? '',
            'url'       => $update->download_url ?? '',
        ];
    }
}
