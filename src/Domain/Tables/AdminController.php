<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

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
    private const PAGE_SLUG = 'fp-resv-layout';

    private ?string $pageHook = null;

    public function __construct(private readonly LayoutService $layout)
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
            __('Sale & Tavoli', 'fp-restaurant-reservations'),
            __('Sale & Tavoli', 'fp-restaurant-reservations'),
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

        $scriptHandle = 'fp-resv-admin-tables';
        $styleHandle  = 'fp-resv-admin-tables-style';
        $baseHandle   = 'fp-resv-admin-shell';

        $scriptUrl = Plugin::$url . 'assets/js/admin/tables-layout.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-tables.css';

        wp_enqueue_script($scriptHandle, $scriptUrl, ['wp-api-fetch'], Plugin::VERSION, true);

        wp_enqueue_style($baseHandle, Plugin::$url . 'assets/css/admin-shell.css', [], Plugin::VERSION);

        if (file_exists(Plugin::$dir . 'assets/css/admin-tables.css')) {
            wp_enqueue_style($styleHandle, $styleUrl, [$baseHandle], Plugin::VERSION);
        }

        wp_localize_script($scriptHandle, 'fpResvTablesSettings', [
            'restRoot'     => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'        => wp_create_nonce('wp_rest'),
            'initialState' => $this->layout->getOverview(),
            'links'        => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
            ],
            'strings'      => [
                'headline'      => __('Layout sale & tavoli', 'fp-restaurant-reservations'),
                'description'   => __('Disegna la mappa dei tavoli con drag & drop, merge/split e suggerimenti automatici.', 'fp-restaurant-reservations'),
                'empty'         => __('Aggiungi una sala per iniziare a progettare il layout.', 'fp-restaurant-reservations'),
                'createRoomCta' => __('Crea sala', 'fp-restaurant-reservations'),
                'newRoomTitle'  => __('Nuova sala', 'fp-restaurant-reservations'),
                'newRoomName'   => __('Nome sala', 'fp-restaurant-reservations'),
                'newRoomCapacity' => __('Capienza stimata', 'fp-restaurant-reservations'),
                'newRoomColor'  => __('Colore identificativo', 'fp-restaurant-reservations'),
                'modalCancel'   => __('Annulla', 'fp-restaurant-reservations'),
                'modalCreate'   => __('Crea sala', 'fp-restaurant-reservations'),
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/tables.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Sale & Tavoli', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
