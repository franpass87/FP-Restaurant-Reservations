<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Core\Adapters;
use FP\Resv\Core\AsyncMailer;
use FP\Resv\Core\Consent;
use FP\Resv\Core\Privacy;
use FP\Resv\Core\Roles;
use FP\Resv\Core\Scheduler;
use FP\Resv\Core\Security;
use FP\Resv\Core\ServiceRegistry;
use FP\Resv\Domain\Brevo\AutomationService as BrevoAutomation;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use FP\Resv\Domain\Brevo\EventDispatcher as BrevoEventDispatcher;
use FP\Resv\Domain\Brevo\ListManager as BrevoListManager;
use FP\Resv\Domain\Brevo\Mapper as BrevoMapper;
use FP\Resv\Domain\Brevo\PhoneCountryParser as BrevoPhoneParser;
use FP\Resv\Domain\Brevo\Repository as BrevoRepository;
use FP\Resv\Domain\Closures\AdminController as ClosuresAdminController;
use FP\Resv\Domain\Closures\AjaxHandler as ClosuresAjaxHandler;
use FP\Resv\Domain\Closures\REST as ClosuresREST;
use FP\Resv\Domain\Closures\PayloadNormalizer as ClosuresPayloadNormalizer;
use FP\Resv\Domain\Closures\PreviewGenerator as ClosuresPreviewGenerator;
use FP\Resv\Domain\Closures\RecurrenceHandler as ClosuresRecurrenceHandler;
use FP\Resv\Domain\Closures\Service as ClosuresService;
use FP\Resv\Domain\Closures\ClosuresDateRangeResolver;
use FP\Resv\Domain\Closures\ClosuresPayloadCollector;
use FP\Resv\Domain\Closures\ClosuresModelExporter;
use FP\Resv\Domain\Closures\ClosuresResponseBuilder;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Diagnostics\AdminController as DiagnosticsAdminController;
use FP\Resv\Domain\Diagnostics\REST as DiagnosticsREST;
use FP\Resv\Domain\Diagnostics\LogExporter as DiagnosticsLogExporter;
use FP\Resv\Domain\Diagnostics\LogFormatter as DiagnosticsLogFormatter;
use FP\Resv\Domain\Diagnostics\Service as DiagnosticsService;
use FP\Resv\Domain\Events\CPT as EventsCPT;
use FP\Resv\Domain\Events\REST as EventsREST;
use FP\Resv\Domain\Events\Service as EventsService;
use FP\Resv\Domain\Events\BookingPayloadSanitizer;
use FP\Resv\Domain\Events\BookingPayloadValidator;
use FP\Resv\Domain\Events\EventFormatter;
use FP\Resv\Domain\Events\EventNotesBuilder;
use FP\Resv\Domain\Events\EventPermalinkResolver;
use FP\Resv\Domain\Events\TicketCounter;
use FP\Resv\Domain\Events\TicketCreator;
use FP\Resv\Domain\Events\TicketCsvExporter;
use FP\Resv\Domain\Events\TicketLister;
use FP\Resv\Domain\QA\CLI as QASeederCLI;
use FP\Resv\Domain\QA\REST as QASeederREST;
use FP\Resv\Domain\QA\Seeder as QASeeder;
use FP\Resv\Domain\Notifications\Manager as NotificationsManager;
use FP\Resv\Domain\Notifications\Settings as NotificationsSettings;
use FP\Resv\Domain\Notifications\TemplateRenderer as NotificationsTemplateRenderer;
use FP\Resv\Domain\Notifications\BrevoEventSender;
use FP\Resv\Domain\Notifications\EmailHeadersBuilder;
use FP\Resv\Domain\Notifications\ManageUrlGenerator;
use FP\Resv\Domain\Notifications\NotificationContextBuilder;
use FP\Resv\Domain\Notifications\NotificationScheduler;
use FP\Resv\Domain\Notifications\TimestampCalculator;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Payments\REST as PaymentsREST;
use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Reservations\AdminController as ReservationsAdminController;
use FP\Resv\Domain\Reservations\AdminREST as ReservationsAdminREST;
use FP\Resv\Domain\Reservations\Admin\AgendaHandler;
use FP\Resv\Domain\Reservations\Admin\StatsHandler;
use FP\Resv\Domain\Reservations\Admin\ArrivalsHandler;
use FP\Resv\Domain\Reservations\Admin\OverviewHandler;
use FP\Resv\Domain\Reservations\Admin\ReservationPayloadExtractor;
use FP\Resv\Domain\Reservations\Availability as AvailabilityService;
use FP\Resv\Domain\Reservations\Availability\ClosureEvaluator;
use FP\Resv\Domain\Reservations\Availability\DataLoader;
use FP\Resv\Domain\Reservations\Availability\ScheduleParser;
use FP\Resv\Domain\Reservations\Availability\TableSuggester;
use FP\Resv\Domain\Reservations\Availability\CapacityResolver;
use FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer;
use FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder;
use FP\Resv\Domain\Reservations\Availability\ReservationFilter;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\REST as ReservationsREST;
use FP\Resv\Domain\Reservations\REST\AvailabilityHandler;
use FP\Resv\Domain\Reservations\REST\ReservationHandler;
use FP\Resv\Domain\Reservations\Service as ReservationsService;
use FP\Resv\Domain\Reservations\ReservationPayloadSanitizer;
use FP\Resv\Domain\Reservations\SettingsResolver;
use FP\Resv\Domain\Reservations\BrevoConfirmationEventSender;
use FP\Resv\Domain\Reports\AdminController as ReportsAdminController;
use FP\Resv\Domain\Reports\REST as ReportsREST;
use FP\Resv\Domain\Reports\ChannelClassifier as ReportsChannelClassifier;
use FP\Resv\Domain\Reports\CsvExporter as ReportsCsvExporter;
use FP\Resv\Domain\Reports\DataNormalizer as ReportsDataNormalizer;
use FP\Resv\Domain\Reports\DateRangeResolver as ReportsDateRangeResolver;
use FP\Resv\Domain\Reports\Service as ReportsService;
use FP\Resv\Domain\Settings\Admin\SettingsSanitizer;
use FP\Resv\Domain\Settings\Admin\SettingsValidator;
use FP\Resv\Domain\Settings\AdminPages;
use FP\Resv\Domain\Settings\Language as LanguageSettings;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style as StyleSettings;
use FP\Resv\Domain\Tracking\Ads as AdsTracking;
use FP\Resv\Domain\Tracking\Clarity as ClarityTracking;
use FP\Resv\Domain\Tracking\GA4 as GA4Tracking;
use FP\Resv\Domain\Tracking\Manager as TrackingManager;
use FP\Resv\Domain\Tracking\Meta as MetaTracking;
use FP\Resv\Domain\Tracking\ReservationEventBuilder;
use FP\Resv\Domain\Tracking\ServerSideEventDispatcher;
use FP\Resv\Domain\Tracking\TrackingScriptGenerator;
use FP\Resv\Domain\Tracking\UTMAttributionHandler;
use FP\Resv\Domain\Surveys\REST as SurveysREST;
use FP\Resv\Domain\Tables\AdminController as TablesAdminController;
use FP\Resv\Domain\Tables\CapacityCalculator as TablesCapacityCalculator;
use FP\Resv\Domain\Tables\LayoutService as TablesLayoutService;
use FP\Resv\Domain\Tables\RoomTableNormalizer as TablesRoomTableNormalizer;
use FP\Resv\Domain\Tables\TableSuggestionEngine as TablesTableSuggestionEngine;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use FP\Resv\Domain\Tables\REST as TablesREST;
use FP\Resv\Frontend\WidgetController;
use FP\Resv\Frontend\ManageController;
use FP\Resv\Frontend\AssetManager;
use FP\Resv\Frontend\CriticalCssManager;
use FP\Resv\Frontend\PageBuilderCompatibility;
use FP\Resv\Frontend\ContentFilter;
use Throwable;
use function sprintf;
use function is_admin;
use function did_action;

final class Plugin
{
    /**
     * Current plugin semantic version.
     *
     * Keep this in sync with the plugin header in fp-restaurant-reservations.php.
     */
    // Intentionally omit visibility for compatibility with PHP < 7.1 (which does not support constant visibility).
    const VERSION = '0.9.0-rc10.3';

    /**
     * @var string|null
     */
    public static $file = null;

    /**
     * @var string|null
     */
    public static $dir = null;

    /**
     * @var string|null
     */
    public static $url = null;

    /**
     * @var string|null Cache for asset version in current request
     */
    private static $assetVersionCache = null;

    /**
     * Get the asset version string with cache busting timestamp.
     * This ensures browser caches are invalidated when the plugin is updated.
     * 
     * In development mode (WP_DEBUG), uses current timestamp to force cache invalidation.
     * In production, uses last upgrade timestamp for stable caching.
     * 
     * @return string Version string like "0.1.6.1234567890"
     */
    public static function assetVersion(): string
    {
        // Return cached version if already calculated in this request
        if (self::$assetVersionCache !== null) {
            return self::$assetVersionCache;
        }
        
        // In debug mode, use file modification time to auto-detect changes
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Use the latest modification time of key frontend files
            $files = [
                self::$dir . 'templates/frontend/form.php',
                self::$dir . 'assets/css/form-thefork.css',
                self::$dir . 'assets/css/form.css',
                self::$dir . 'assets/dist/fe/onepage.esm.js',
                self::$dir . 'assets/dist/fe/onepage.iife.js',
                self::$dir . 'assets/js/admin/reports-dashboard.js',
            ];
            
            $latestTime = time();
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $mtime = filemtime($file);
                    if ($mtime !== false && $mtime > $latestTime) {
                        $latestTime = $mtime;
                    }
                }
            }
            
            self::$assetVersionCache = self::VERSION . '.' . $latestTime;
            return self::$assetVersionCache;
        }
        
        // In production, use upgrade timestamp for stable caching
        if (!function_exists('get_option')) {
            // Fallback if WordPress not fully loaded yet (shouldn't happen in normal flow)
            return self::VERSION . '.' . time();
        }
        
        $upgradeTime = get_option('fp_resv_last_upgrade', false);
        if ($upgradeTime === false || $upgradeTime === 0 || $upgradeTime === '0') {
            // If never set, use current time as fallback
            $upgradeTime = time();
            if (function_exists('update_option')) {
                update_option('fp_resv_last_upgrade', $upgradeTime, false);
            }
        }
        
        self::$assetVersionCache = self::VERSION . '.' . (int) $upgradeTime;

        $adminAnalyticsScript = self::$dir . 'assets/js/admin/reports-dashboard.js';
        if (file_exists($adminAnalyticsScript)) {
            $mtime = filemtime($adminAnalyticsScript);
            if ($mtime !== false) {
                self::$assetVersionCache .= '.' . $mtime;
            }
        }

        return self::$assetVersionCache;
    }

    /**
     * Force refresh the asset cache by updating the upgrade timestamp.
     * This is useful when deploying updates in production without going through the WP upgrader.
     * 
     * @return void
     */
    public static function forceRefreshAssets(): void
    {
        $timestamp = time();
        update_option('fp_resv_last_upgrade', $timestamp, false);
        
        // Also clear all caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        CacheManager::invalidateAll();
        
        Logging::log('plugin', 'Asset cache manually refreshed', [
            'version' => self::VERSION,
            'timestamp' => $timestamp,
        ]);
    }

    public static function boot(string $file): void
    {
        self::$file = $file;
        self::$dir  = plugin_dir_path($file);
        self::$url  = plugin_dir_url($file);

        require_once __DIR__ . '/Autoloader.php';
        Autoloader::register();

        register_activation_hook($file, static function (): void {
            self::runBootstrapStage('activation', static function (): void {
                self::onActivate();
            });
        });

        register_deactivation_hook($file, static function (): void {
            self::runBootstrapStage('deactivation', static function (): void {
                self::onDeactivate();
            });
        });

        // Clear all caches when plugin is upgraded
        add_action('upgrader_process_complete', static function ($upgrader_object, $options) use ($file): void {
            if ($options['action'] === 'update' && $options['type'] === 'plugin') {
                if (isset($options['plugins'])) {
                    foreach ($options['plugins'] as $plugin) {
                        if ($plugin === plugin_basename($file)) {
                            self::runBootstrapStage('upgrade', static function (): void {
                                self::onUpgrade();
                            });
                            break;
                        }
                    }
                }
            }
        }, 10, 2);

        // Check if already initialized to prevent double initialization
        static $initialized = false;
        if ($initialized) {
            return;
        }
        
        if (did_action('plugins_loaded')) {
            $initialized = true;
            self::runBootstrapStage('bootstrap', static function (): void {
                self::onPluginsLoaded();
            });
        } else {
            add_action('plugins_loaded', static function () use (&$initialized): void {
                if ($initialized) {
                    return;
                }
                $initialized = true;
                self::runBootstrapStage('bootstrap', static function (): void {
                    self::onPluginsLoaded();
                });
            });
        }
    }

    public static function onActivate(): void
    {
        // Container will be initialized by Bootstrap if not already done
        // For activation, we just need to ensure migrations run
        if (class_exists('\FP\Resv\Kernel\LegacyBridge')) {
            try {
                \FP\Resv\Kernel\LegacyBridge::getContainer();
            } catch (\RuntimeException $e) {
                // Container not initialized yet, that's ok for activation
            }
        }

        Migrations::run();

        // Crea i ruoli personalizzati del plugin
        Roles::create();

        // Set initial installation timestamp (solo prima installazione)
        if (get_option('fp_resv_installed_at', false) === false) {
            update_option('fp_resv_installed_at', current_time('mysql'), false);
        }
        
        // Set initial upgrade timestamp if not already set
        $upgradeTime = get_option('fp_resv_last_upgrade', false);
        if ($upgradeTime === false || $upgradeTime === 0 || $upgradeTime === '0') {
            update_option('fp_resv_last_upgrade', time(), false);
        }
        
        // Imposta l'opzione predefinita per mantenere i dati alla disinstallazione
        // Solo se non è già impostata (per rispettare le preferenze esistenti)
        if (get_option('fp_resv_keep_data_on_uninstall', null) === null) {
            update_option('fp_resv_keep_data_on_uninstall', '1', false);
        }
    }

    /**
     * Called when the plugin is deactivated.
     */
    public static function onDeactivate(): void
    {
        // Nota: non rimuoviamo il ruolo in quanto gli utenti assegnati perderebbero i permessi
        // Il ruolo viene mantenuto anche dopo la disattivazione
        Logging::log('plugin', 'Plugin deactivated', [
            'version' => self::VERSION,
        ]);
    }

    /**
     * Called when the plugin is upgraded.
     * Clears all caches to ensure new files are loaded.
     */
    public static function onUpgrade(): void
    {
        $timestamp = time();
        
        // Clear all WordPress caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear transients that might be caching old data
        global $wpdb;
        if ($wpdb instanceof \wpdb && isset($wpdb->options)) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like('_transient_') . '%fp_resv%',
                    $wpdb->esc_like('_transient_timeout_') . '%fp_resv%'
                )
            );
        }

        // Clear plugin-specific caches
        CacheManager::invalidateAll();

        // Ricrea i ruoli personalizzati per aggiornare le capabilities
        Roles::create();

        // Update last upgrade timestamp option
        if (function_exists('update_option')) {
            update_option('fp_resv_last_upgrade', $timestamp, false);
        }

        Logging::log('plugin', 'Plugin upgraded and caches cleared', [
            'version' => self::VERSION,
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * @deprecated 0.9.0-rc11 This method is deprecated. Initialization is now handled by Kernel\Bootstrap.
     *             This method is kept for backward compatibility but should not be called directly.
     *             If Bootstrap has already run, this method will do nothing to prevent double initialization.
     */
    public static function onPluginsLoaded(): void
    {
        // Prevent double initialization
        static $initialized = false;
        if ($initialized) {
            return;
        }
        
        // Check if Bootstrap has already initialized everything
        // If so, skip legacy initialization to avoid conflicts
        if (class_exists('\FP\Resv\Kernel\Bootstrap')) {
            try {
                \FP\Resv\Kernel\Bootstrap::container();
                // Bootstrap already ran, skip legacy initialization
                $initialized = true;
                return;
            } catch (\RuntimeException $e) {
                // Bootstrap not initialized yet, continue with legacy initialization
            }
        }
        
        $initialized = true;
        
        // Try to get container (new or legacy)
        $container = null;
        if (class_exists('\FP\Resv\Kernel\LegacyBridge')) {
            try {
                $container = \FP\Resv\Kernel\LegacyBridge::getContainer();
            } catch (\RuntimeException $e) {
                // Fallback to legacy container
                $container = ServiceContainer::getInstance();
            }
        } else {
            $container = ServiceContainer::getInstance();
        }
        
        // Register plugin paths if container supports it
        if (method_exists($container, 'register')) {
            $container->register('plugin.file', self::$file);
            $container->register('plugin.dir', self::$dir);
            $container->register('plugin.url', self::$url);
        } elseif (method_exists($container, 'singleton')) {
            $container->singleton('plugin.file', fn() => self::$file);
            $container->singleton('plugin.dir', fn() => self::$dir);
            $container->singleton('plugin.url', fn() => self::$url);
        }

        // Auto cache buster - aggiorna automaticamente la cache quando cambia la versione
        AutoCacheBuster::init();

        // Garantisce che gli amministratori abbiano sempre le capability necessarie
        Roles::ensureAdminCapabilities();

        global $wpdb;
        $options = new Options();
        
        // Registra tutti i servizi tramite ServiceRegistry (DEPRECATED - use Service Providers instead)
        $registry = new ServiceRegistry($container, $options, $wpdb);
        $registry->registerAll();

        // Migrations run only once at plugin load (idempotent check inside)
        Migrations::run();

        I18n::init();
        
        // Proteggi gli endpoint REST da redirect di altri plugin
        add_filter('redirect_canonical', static function ($redirect_url, $requested_url) {
            // Non redirigere le chiamate REST API
            if (strpos($requested_url, '/wp-json/fp-resv/') !== false) {
                return false;
            }
            return $redirect_url;
        }, 10, 2);
        
        // Proteggi da plugin 404 redirect
        add_filter('wp_redirect', static function ($location, $status) {
            // Non redirigere le API REST
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return false;
            }
            return $location;
        }, 1, 2);

        // Componenti non gestiti da ServiceRegistry (now handled by FrontendServiceProvider)
        $manage = new ManageController();
        $manage->boot();
        $container->register(ManageController::class, $manage);
        $container->register('frontend.manage', $manage);

        Scheduler::init();

        REST::init();
    }

    /**
     * @param callable():void $callback
     */
    private static function runBootstrapStage(string $stage, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            self::logBootstrapFailure($stage, $exception);

            throw $exception;
        }
    }

    private static function logBootstrapFailure(string $stage, Throwable $exception): void
    {
        Logging::log(
            'bootstrap',
            sprintf('Plugin stage "%s" failed', $stage),
            self::exceptionContext($stage, $exception)
        );
    }

    private static function exceptionContext(string $stage, Throwable $exception): array
    {
        $previous = $exception->getPrevious();

        return [
            'stage'           => $stage,
            'plugin_version'  => self::VERSION,
            'plugin_file'     => self::$file ?? null,
            'plugin_dir'      => self::$dir ?? null,
            'plugin_url'      => self::$url ?? null,
            'exception'       => $exception::class,
            'message'         => $exception->getMessage(),
            'code'            => $exception->getCode(),
            'file'            => $exception->getFile(),
            'line'            => $exception->getLine(),
            'trace'           => $exception->getTraceAsString(),
            'previous'        => $previous ? $previous::class : null,
            'previous_message' => $previous ? $previous->getMessage() : null,
            'previous_code'    => $previous ? $previous->getCode() : null,
        ];
    }
}
