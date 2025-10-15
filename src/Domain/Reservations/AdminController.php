<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\Roles;
use FP\Resv\Core\ErrorLogger;
use function __;
use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function function_exists;
use function get_current_screen;
use function get_option;
use function plugin_basename;
use function remove_action;
use function remove_all_actions;
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
        add_action('admin_head', [$this, 'removeOtherPluginNotices']);
        add_action('admin_head', [$this, 'addMobileViewportMeta']);
    }

    public function registerMenu(): void
    {
        // Assicura che gli amministratori abbiano sempre la capability necessaria
        Roles::ensureAdminCapabilities();
        
        // Determina la capability appropriata
        // Priorità: manage_options (admin) > manage_fp_reservations (Restaurant Manager) > view_fp_reservations_manager (Reservations Viewer)
        if (current_user_can('manage_options') && !current_user_can(self::CAPABILITY)) {
            $capability = 'manage_options';
        } elseif (current_user_can(self::CAPABILITY)) {
            $capability = self::CAPABILITY;
        } else {
            $capability = Roles::VIEW_RESERVATIONS_MANAGER;
        }
        
        // Se l'utente ha solo la capability VIEW_RESERVATIONS_MANAGER, crea un menu principale
        // Altrimenti aggiunge come submenu
        if (current_user_can(Roles::VIEW_RESERVATIONS_MANAGER) && !current_user_can(self::CAPABILITY) && !current_user_can('manage_options')) {
            // Crea un menu principale per gli utenti con accesso limitato
            $this->pageHook = add_menu_page(
                __('Prenotazioni', 'fp-restaurant-reservations'),
                __('Prenotazioni', 'fp-restaurant-reservations'),
                $capability,
                self::PAGE_SLUG,
                [$this, 'renderPage'],
                'dashicons-clipboard',
                56
            ) ?: null;
        } else {
            // Aggiungi come submenu per gli utenti con accesso completo
            $this->pageHook = add_submenu_page(
                'fp-resv-settings',
                __('Manager Prenotazioni', 'fp-restaurant-reservations'),
                __('Manager', 'fp-restaurant-reservations'),
                $capability,
                self::PAGE_SLUG,
                [$this, 'renderPage']
            ) ?: null;
        }
    }

    public function addMobileViewportMeta(): void
    {
        // Verifica se siamo nella pagina del manager
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->id !== $this->pageHook) {
            return;
        }

        // Aggiunge meta viewport per supporto mobile ottimale
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">' . "\n";
        
        // Aggiunge stili inline per ottimizzazioni mobile-first
        echo '<style>
            @media (max-width: 768px) {
                #wpcontent {
                    padding-left: 0;
                }
                .auto-fold #wpcontent {
                    padding-left: 0;
                }
                #wpbody-content {
                    padding-bottom: 0;
                }
            }
        </style>' . "\n";
    }

    public function removeOtherPluginNotices(): void
    {
        // Verifica se siamo nella pagina del manager
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->id !== $this->pageHook) {
            return;
        }

        // Rimuovi tutti gli admin notices tranne quelli critici del nostro plugin
        global $wp_filter;
        
        if (isset($wp_filter['admin_notices'])) {
            $ourPluginPath = plugin_basename(Plugin::$dir);
            
            foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $key => $callback) {
                    // Mantieni solo i notice del nostro plugin (Requirements e BootstrapGuard)
                    if (is_array($callback['function']) && count($callback['function']) === 2) {
                        $class = is_object($callback['function'][0]) 
                            ? get_class($callback['function'][0]) 
                            : $callback['function'][0];
                        
                        // Mantieni solo i notice delle classi Requirements e BootstrapGuard
                        if (strpos($class, 'FP\\Resv\\Core\\Requirements') !== false || 
                            strpos($class, 'FP\\Resv\\Core\\BootstrapGuard') !== false) {
                            continue;
                        }
                    }
                    
                    // Rimuovi tutti gli altri notice
                    remove_action('admin_notices', $callback['function'], $priority);
                }
            }
        }
        
        // Rimuovi anche user_admin_notices e network_admin_notices per completezza
        if (isset($wp_filter['user_admin_notices'])) {
            remove_all_actions('user_admin_notices');
        }
        if (isset($wp_filter['network_admin_notices'])) {
            remove_all_actions('network_admin_notices');
        }
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
        $meals = [];
        try {
            $generalOptions = get_option('fp_resv_general', []);
            $mealsDefinition = is_array($generalOptions) && isset($generalOptions['frontend_meals']) 
                ? $generalOptions['frontend_meals'] 
                : '';
            
            if (is_string($mealsDefinition) && $mealsDefinition !== '') {
                $meals = \FP\Resv\Domain\Settings\MealPlan::parse($mealsDefinition);
            }
        } catch (\Exception $e) {
            // Fallback silenzioso a array vuoto
            $meals = [];
        }

        // Leggi opzione debug mode (opzionale, default false)
        $debugOptions = get_option('fp_resv_debug', []);
        $debugMode = is_array($debugOptions) && isset($debugOptions['manager_debug_panel']) 
            ? (bool) $debugOptions['manager_debug_panel'] 
            : false;
        
        // Fallback: attiva automaticamente se WP_DEBUG è true
        if (!$debugMode && defined('WP_DEBUG') && WP_DEBUG) {
            $debugMode = true;
        }
        
        // Ottieni errori recenti se debug mode è attivo
        $recentErrors = [];
        if ($debugMode) {
            $recentErrors = ErrorLogger::getRecentErrors(10);
        }
        
        wp_localize_script($scriptHandle, 'fpResvManagerSettings', [
            'restRoot'  => esc_url_raw(rest_url('fp-resv/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'publicNonce' => wp_create_nonce('fp_resv_submit'), // Nonce per l'endpoint pubblico
            'meals'     => $meals,
            'debugMode' => $debugMode, // Attiva/disattiva debug panel
            'errors'    => $recentErrors, // Ultimi errori del plugin
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
