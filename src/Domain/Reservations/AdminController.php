<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Plugin;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

final class AdminController
{
    private const CAPABILITY = 'manage_options';
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
            self::CAPABILITY,
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

        $scriptUrl = Plugin::$url . 'assets/js/admin/agenda-app.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-agenda.css';

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], Plugin::VERSION, true);

        if (file_exists(Plugin::$dir . 'assets/css/admin-agenda.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [], Plugin::VERSION);
        }

        wp_localize_script($scriptHandle, 'fpResvAgendaSettings', [
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'links'    => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
            'strings'  => [
                'headline'    => __('Agenda in arrivo', 'fp-restaurant-reservations'),
                'description' => __('La SPA dell\'agenda verrà completata nelle prossime fasi. Nel frattempo sono disponibili le API REST per integrare dati reali.', 'fp-restaurant-reservations'),
                'cta'         => __('Utilizza le API per alimentare dashboard personalizzate oppure attendi i prossimi rilasci per il drag & drop.', 'fp-restaurant-reservations'),
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
