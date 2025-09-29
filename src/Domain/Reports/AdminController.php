<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use FP\Resv\Core\Plugin;
use function __;
use function add_action;
use function add_submenu_page;
use function admin_url;
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
    private const CAPABILITY = 'manage_options';
    private const PAGE_SLUG  = 'fp-resv-reports';

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
            __('Dashboard & Report', 'fp-restaurant-reservations'),
            __('Report', 'fp-restaurant-reservations'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $this->pageHook !== $hook) {
            return;
        }

        $scriptHandle = 'fp-resv-admin-reports';
        $styleHandle  = 'fp-resv-admin-reports-style';

        $scriptUrl = Plugin::$url . 'assets/js/admin/reports-dashboard.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-reports.css';

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], Plugin::VERSION, true);
        wp_enqueue_style($styleHandle, $styleUrl, [], Plugin::VERSION);

        $end   = wp_date('Y-m-d');
        $start = wp_date('Y-m-d', strtotime('-6 days'));

        wp_localize_script($scriptHandle, 'fpResvReportsSettings', [
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'defaultRange' => [
                'start' => $start,
                'end'   => $end,
            ],
            'links' => [
                'agenda'   => esc_url_raw(admin_url('admin.php?page=fp-resv-agenda')),
                'tables'   => esc_url_raw(admin_url('admin.php?page=fp-resv-layout')),
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
            'i18n' => [
                'title'           => __('Dashboard KPI giornaliera', 'fp-restaurant-reservations'),
                'rangeLabel'      => __('Intervallo date', 'fp-restaurant-reservations'),
                'summaryEmpty'    => __('Nessun dato disponibile per l\'intervallo selezionato.', 'fp-restaurant-reservations'),
                'logsTitle'       => __('Log di sistema', 'fp-restaurant-reservations'),
                'logsEmpty'       => __('Nessun evento registrato.', 'fp-restaurant-reservations'),
                'channelMail'     => __('Email', 'fp-restaurant-reservations'),
                'channelBrevo'    => __('Brevo', 'fp-restaurant-reservations'),
                'channelAudit'    => __('Audit', 'fp-restaurant-reservations'),
                'filtersApply'    => __('Aggiorna', 'fp-restaurant-reservations'),
                'exportCsv'       => __('Esporta CSV', 'fp-restaurant-reservations'),
                'exportExcel'     => __('Esporta Excel (;)', 'fp-restaurant-reservations'),
                'downloadReady'   => __('Download pronto', 'fp-restaurant-reservations'),
                'downloadFailed'  => __('Esportazione non riuscita. Riprova.', 'fp-restaurant-reservations'),
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

        echo '<div class="wrap"><h1>' . esc_html__('Dashboard & Report', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
