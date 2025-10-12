<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

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
use function sanitize_key;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

final class AdminController
{
    private const CAPABILITY = Roles::MANAGE_RESERVATIONS;
    private const PAGE_SLUG = 'fp-resv-manager';

    private ?string $pageHook = null;

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
            __('Manager Prenotazioni', 'fp-restaurant-reservations'),
            __('Manager', 'fp-restaurant-reservations'),
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        ) ?: null;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->pageHook === null || $hook !== $this->pageHook) {
            return;
        }

        $scriptHandle = 'fp-resv-admin-manager';
        $styleHandle  = 'fp-resv-admin-manager-style';

        $scriptUrl = Plugin::$url . 'assets/js/admin/manager-app.js';
        $styleUrl  = Plugin::$url . 'assets/css/admin-manager.css';
        $version   = Plugin::assetVersion();

        wp_enqueue_script($scriptHandle, $scriptUrl, [], $version, true);
        wp_enqueue_style($styleHandle, $styleUrl, [], $version);

        // Carica meal plans configurati
        $container = Plugin::container();
        $options = $container->get(\FP\Resv\Domain\Settings\Options::class);
        if ($options instanceof \FP\Resv\Domain\Settings\Options) {
            $mealsDefinition = $options->getField('fp_resv_frontend', 'frontend_meals', '');
            $meals = \FP\Resv\Domain\Settings\MealPlan::parse(is_string($mealsDefinition) ? $mealsDefinition : '');
        } else {
            $meals = [];
        }

        wp_localize_script($scriptHandle, 'fpResvManagerSettings', [
            'restRoot'  => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'meals'     => $meals, // Aggiunto!
            'links'     => [
                'settings' => esc_url_raw(admin_url('admin.php?page=fp-resv-settings')),
                'manager'  => esc_url_raw(admin_url('admin.php?page=fp-resv-manager')),
            ],
            'strings'   => [
                'loading'           => __('Caricamento...', 'fp-restaurant-reservations'),
                'error'             => __('Errore nel caricamento dei dati', 'fp-restaurant-reservations'),
                'noReservations'    => __('Nessuna prenotazione trovata', 'fp-restaurant-reservations'),
                'confirmDelete'     => __('Sei sicuro di voler eliminare questa prenotazione?', 'fp-restaurant-reservations'),
                'saveSuccess'       => __('Modifiche salvate con successo', 'fp-restaurant-reservations'),
                'saveError'         => __('Errore nel salvataggio delle modifiche', 'fp-restaurant-reservations'),
                'today'             => __('Oggi', 'fp-restaurant-reservations'),
                'confirmed'         => __('Confermato', 'fp-restaurant-reservations'),
                'pending'           => __('In attesa', 'fp-restaurant-reservations'),
                'visited'           => __('Visitato', 'fp-restaurant-reservations'),
                'noShow'            => __('No-show', 'fp-restaurant-reservations'),
                'cancelled'         => __('Cancellato', 'fp-restaurant-reservations'),
            ],
        ]);
    }

    public function renderPage(): void
    {
        $view = Plugin::$dir . 'src/Admin/Views/manager.php';
        if (file_exists($view)) {
            /** @psalm-suppress UnresolvableInclude */
            require $view;

            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Manager Prenotazioni', 'fp-restaurant-reservations') . '</h1></div>';
    }
}
