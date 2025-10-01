<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\Security;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function sanitize_key;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_set_script_translations;

final class AdminController
{
    private const PAGE_SLUG = 'fp-resv-agenda';

    private ?string $pageHook = null;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        $this->pageHook = add_submenu_page(
            'fp-resv-settings',
            __('Agenda prenotazioni', 'fp-restaurant-reservations'),
            __('Agenda', 'fp-restaurant-reservations'),
            Security::managementCapability(),
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $hook !== $this->pageHook) {
            return;
        }

        $scriptHandle = 'fp-resv-admin-agenda';
        $styleHandle  = 'fp-resv-admin-agenda-style';
        $baseHandle   = 'fp-resv-admin-shell';

        $scriptUrl = Plugin::$url . 'assets/js/admin/agenda-app.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-agenda.css';

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], Plugin::VERSION, true);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($scriptHandle, 'fp-restaurant-reservations', Plugin::$dir . 'languages');
        }

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], Plugin::VERSION);

        if (file_exists(Plugin::$dir . 'assets/css/admin-agenda.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [$baseHandle], Plugin::VERSION);
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
        if ($tab === '') {
            $tab = 'agenda';
        }

        wp_localize_script($scriptHandle, 'fpResvAgendaSettings', [
            'restRoot'  => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'activeTab' => $tab,
            'links'     => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
                'agenda'   => esc_url_raw(admin_url('admin.php?page=fp-resv-agenda')),
            ],
            'strings'   => [
                'headline'          => __('Agenda in arrivo', 'fp-restaurant-reservations'),
                'description'       => __('La SPA dell’agenda verrà completata nelle prossime fasi. Nel frattempo sono disponibili le API REST per integrare dati reali.', 'fp-restaurant-reservations'),
                'cta'               => __('Utilizza le API per alimentare dashboard personalizzate oppure attendi i prossimi rilasci per il drag & drop.', 'fp-restaurant-reservations'),
                'arrivalsTitle'     => __('Prenotazioni in arrivo', 'fp-restaurant-reservations'),
                'arrivalsEmpty'     => __('Nessuna prenotazione imminente per l’intervallo selezionato.', 'fp-restaurant-reservations'),
                'arrivalsLoading'   => __('Caricamento arrivi…', 'fp-restaurant-reservations'),
                'arrivalsReload'    => __('Aggiorna elenco', 'fp-restaurant-reservations'),
                'arrivalsError'     => __('Impossibile caricare le prenotazioni in arrivo. Riprova.', 'fp-restaurant-reservations'),
                'actionConfirm'     => __('Conferma', 'fp-restaurant-reservations'),
                'actionVisited'     => __('Check-in', 'fp-restaurant-reservations'),
                'actionNoShow'      => __('No-show', 'fp-restaurant-reservations'),
                'actionMove'        => __('Sposta', 'fp-restaurant-reservations'),
                'drawerPlaceholder' => __('La funzione di spostamento aprirà un drawer dedicato nelle prossime versioni.', 'fp-restaurant-reservations'),
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/agenda.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Agenda prenotazioni', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
