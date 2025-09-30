<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use FP\Resv\Core\Plugin;
use InvalidArgumentException;
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
use function wp_timezone;

final class AdminController
{
    private const CAPABILITY = 'manage_options';
    private const PAGE_SLUG = 'fp-resv-closures-app';

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
        $this->pageHook = add_submenu_page(
            'fp-resv-settings',
            __('Chiusure & orari speciali', 'fp-restaurant-reservations'),
            __('Chiusure', 'fp-restaurant-reservations'),
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

        $scriptHandle = 'fp-resv-admin-closures';
        $styleHandle  = 'fp-resv-admin-closures-style';
        $baseHandle   = 'fp-resv-admin-shell';

        $scriptUrl = Plugin::$url . 'assets/js/admin/closures-app.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-closures.css';

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], Plugin::VERSION, true);

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], Plugin::VERSION);

        if (file_exists(Plugin::$dir . 'assets/css/admin-closures.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [$baseHandle], Plugin::VERSION);
        }

        $timezone = wp_timezone();
        $start    = new DateTimeImmutable('today', $timezone);
        $end      = $start->add(new DateInterval('P30D'));

        try {
            $preview = $this->service->preview($start, $end);
        } catch (InvalidArgumentException $exception) {
            $preview = [
                'range'   => [
                    'start' => $start->format(DateTimeInterface::ATOM),
                    'end'   => $end->format(DateTimeInterface::ATOM),
                ],
                'events'  => [],
                'summary' => [
                    'total_events'       => 0,
                    'blocked_hours'      => 0,
                    'capacity_reduction' => ['count' => 0, 'min' => null, 'max' => null],
                    'special_hours'      => 0,
                    'impacted_scopes'    => [],
                ],
                'error'   => $exception->getMessage(),
            ];
        }

        wp_localize_script($scriptHandle, 'fpResvClosuresSettings', [
            'restRoot' => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'preview'  => $preview,
            'strings'  => [
                'headline'    => __('Chiusure & periodi speciali', 'fp-restaurant-reservations'),
                'description' => __('Definisci chiusure straordinarie, riduzioni di capienza e orari speciali con anteprima immediata sull\'agenda.', 'fp-restaurant-reservations'),
                'createCta'   => __('Aggiungi chiusura', 'fp-restaurant-reservations'),
                'empty'       => __('Nessuna chiusura programmata nel periodo selezionato.', 'fp-restaurant-reservations'),
            ],
            'links'    => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/closures.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Chiusure', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
