<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\Roles;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function rest_url;
use function strtotime;
use function wp_create_nonce;
use function wp_date;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

final class AdminController
{
    private const CAPABILITY = Roles::MANAGE_RESERVATIONS;
    private const PAGE_SLUG  = 'fp-resv-analytics';

    private ?string $pageHook = null;

    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        // Assicura che gli amministratori abbiano sempre la capability necessaria
        Roles::ensureAdminCapabilities();
        
        // Determina la capability appropriata: usa manage_options per admin se manage_fp_reservations non è disponibile
        $capability = current_user_can('manage_options') && !current_user_can(self::CAPABILITY) 
            ? 'manage_options' 
            : self::CAPABILITY;
        
        $this->pageHook = add_submenu_page(
            'fp-resv-settings',
            __('Report & Analytics', 'fp-restaurant-reservations'),
            __('Report & Analytics', 'fp-restaurant-reservations'),
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        ) ?: null;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $this->pageHook !== $hook) {
            return;
        }

        $scriptHandle = 'fp-resv-admin-analytics';
        $styleHandle  = 'fp-resv-admin-analytics-style';
        $baseHandle   = 'fp-resv-admin-shell';
        $chartHandle  = 'fp-resv-chart';

        $scriptUrl = Plugin::$url . 'assets/js/admin/reports-dashboard.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-reports.css';
        $version   = Plugin::assetVersion();

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], $version);

        if (file_exists(Plugin::$dir . 'assets/css/admin-reports.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [$baseHandle], $version);
        }

        wp_enqueue_script(
            $chartHandle,
            Plugin::$url . 'assets/vendor/chart.umd.min.js',
            [],
            $version,
            true
        );

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch', $chartHandle], $version, true);

        $end   = wp_date('Y-m-d');
        $start = wp_date('Y-m-d', strtotime('-29 days'));

        wp_localize_script($scriptHandle, 'fpResvAnalyticsSettings', [
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'defaultRange' => [
                'start' => $start,
                'end'   => $end,
            ],
            'locations' => $this->service->listLocations(),
            'links' => [
                'manager'  => esc_url_raw(admin_url('admin.php?page=fp-resv-manager')),
                'tables'   => esc_url_raw(admin_url('admin.php?page=fp-resv-layout')),
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
            'i18n' => [
                'title'             => __('Analytics di prenotazione', 'fp-restaurant-reservations'),
                'rangeLabel'        => __('Intervallo date', 'fp-restaurant-reservations'),
                'locationLabel'     => __('Sede', 'fp-restaurant-reservations'),
                'allLocations'      => __('Tutte le sedi', 'fp-restaurant-reservations'),
                'applyFilters'      => __('Aggiorna', 'fp-restaurant-reservations'),
                'exportCsv'         => __('Esporta CSV', 'fp-restaurant-reservations'),
                'loading'           => __('Caricamento analytics…', 'fp-restaurant-reservations'),
                'empty'             => __('Nessun dato disponibile per i filtri selezionati.', 'fp-restaurant-reservations'),
                'channelsTitle'     => __('Canali principali', 'fp-restaurant-reservations'),
                'trendTitle'        => __('Trend giornaliero', 'fp-restaurant-reservations'),
                'tableTitle'        => __('Sorgenti top', 'fp-restaurant-reservations'),
                'tableEmpty'        => __('Nessuna sorgente tracciata.', 'fp-restaurant-reservations'),
                'downloadReady'     => __('Esportazione pronta.', 'fp-restaurant-reservations'),
                'downloadFailed'    => __('Esportazione non riuscita. Riprova.', 'fp-restaurant-reservations'),
                'reservationsLabel' => __('Prenotazioni', 'fp-restaurant-reservations'),
                'coversLabel'       => __('Coperti', 'fp-restaurant-reservations'),
                'revenueLabel'      => __('Valore', 'fp-restaurant-reservations'),
                'avgPartyLabel'     => __('Party medio', 'fp-restaurant-reservations'),
                'avgTicketLabel'    => __('Ticket medio', 'fp-restaurant-reservations'),
                'channelLabels'     => [
                    'google_ads' => __('Google Ads', 'fp-restaurant-reservations'),
                    'meta_ads'   => __('Meta Ads', 'fp-restaurant-reservations'),
                    'organic'    => __('Traffico organico', 'fp-restaurant-reservations'),
                    'direct'     => __('Accesso diretto', 'fp-restaurant-reservations'),
                    'referral'   => __('Referral', 'fp-restaurant-reservations'),
                    'email'      => __('Email / Newsletter', 'fp-restaurant-reservations'),
                    'other'      => __('Altro', 'fp-restaurant-reservations'),
                ],
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/reports.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Report & Analytics', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
