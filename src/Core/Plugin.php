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
use FP\Resv\Domain\Brevo\AutomationService as BrevoAutomation;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use FP\Resv\Domain\Brevo\Mapper as BrevoMapper;
use FP\Resv\Domain\Brevo\Repository as BrevoRepository;
use FP\Resv\Domain\Closures\AdminController as ClosuresAdminController;
use FP\Resv\Domain\Closures\REST as ClosuresREST;
use FP\Resv\Domain\Closures\Service as ClosuresService;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Diagnostics\AdminController as DiagnosticsAdminController;
use FP\Resv\Domain\Diagnostics\REST as DiagnosticsREST;
use FP\Resv\Domain\Diagnostics\Service as DiagnosticsService;
use FP\Resv\Domain\Events\CPT as EventsCPT;
use FP\Resv\Domain\Events\REST as EventsREST;
use FP\Resv\Domain\Events\Service as EventsService;
use FP\Resv\Domain\QA\CLI as QASeederCLI;
use FP\Resv\Domain\QA\REST as QASeederREST;
use FP\Resv\Domain\QA\Seeder as QASeeder;
use FP\Resv\Domain\Notifications\Manager as NotificationsManager;
use FP\Resv\Domain\Notifications\Settings as NotificationsSettings;
use FP\Resv\Domain\Notifications\TemplateRenderer as NotificationsTemplateRenderer;
use FP\Resv\Domain\Payments\Repository as PaymentsRepository;
use FP\Resv\Domain\Payments\REST as PaymentsREST;
use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Reservations\AdminController as ReservationsAdminController;
use FP\Resv\Domain\Reservations\AdminREST as ReservationsAdminREST;
use FP\Resv\Domain\Reservations\Availability as AvailabilityService;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\REST as ReservationsREST;
use FP\Resv\Domain\Reservations\Service as ReservationsService;
use FP\Resv\Domain\Reports\AdminController as ReportsAdminController;
use FP\Resv\Domain\Reports\REST as ReportsREST;
use FP\Resv\Domain\Reports\Service as ReportsService;
use FP\Resv\Domain\Settings\AdminPages;
use FP\Resv\Domain\Settings\Language as LanguageSettings;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Settings\Style as StyleSettings;
use FP\Resv\Domain\Tracking\Ads as AdsTracking;
use FP\Resv\Domain\Tracking\Clarity as ClarityTracking;
use FP\Resv\Domain\Tracking\GA4 as GA4Tracking;
use FP\Resv\Domain\Tracking\Manager as TrackingManager;
use FP\Resv\Domain\Tracking\Meta as MetaTracking;
use FP\Resv\Domain\Surveys\REST as SurveysREST;
use FP\Resv\Domain\Tables\AdminController as TablesAdminController;
use FP\Resv\Domain\Tables\LayoutService as TablesLayoutService;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use FP\Resv\Domain\Tables\REST as TablesREST;
use FP\Resv\Frontend\WidgetController;
use FP\Resv\Frontend\ManageController;
use Throwable;
use function sprintf;
use function is_admin;

final class Plugin
{
    /**
     * Current plugin semantic version.
     *
     * Keep this in sync with the plugin header in fp-restaurant-reservations.php.
     */
    // Intentionally omit visibility for compatibility with PHP < 7.1 (which does not support constant visibility).
    const VERSION = '0.1.9';

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
        // In debug mode, always use current timestamp to bust cache
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return self::VERSION . '.' . time();
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
        
        return self::VERSION . '.' . (int) $upgradeTime;
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

        add_action('plugins_loaded', static function (): void {
            self::runBootstrapStage('bootstrap', static function (): void {
                self::onPluginsLoaded();
            });
        });
    }

    public static function onActivate(): void
    {
        ServiceContainer::getInstance();

        Migrations::run();

        // Crea i ruoli personalizzati del plugin
        Roles::create();

        // Set initial upgrade timestamp if not already set
        $upgradeTime = get_option('fp_resv_last_upgrade', false);
        if ($upgradeTime === false || $upgradeTime === 0 || $upgradeTime === '0') {
            update_option('fp_resv_last_upgrade', time(), false);
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
        if (isset($wpdb) && isset($wpdb->options)) {
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

    public static function onPluginsLoaded(): void
    {
        $container = ServiceContainer::getInstance();
        $container->register('plugin.file', self::$file);
        $container->register('plugin.dir', self::$dir);
        $container->register('plugin.url', self::$url);

        // Auto cache buster - aggiorna automaticamente la cache quando cambia la versione
        AutoCacheBuster::init();

        // Garantisce che gli amministratori abbiano sempre le capability necessarie
        Roles::ensureAdminCapabilities();

        // Core adapters (for testing)
        $container->singleton('wp.adapter', static function (): Adapters\WordPressAdapter {
            return new Adapters\WPFunctionsAdapter();
        });

        $options = new Options();
        $container->register(Options::class, $options);
        $container->register('settings.options', $options);

        $languageSettings = new LanguageSettings($options);
        $container->register(LanguageSettings::class, $languageSettings);
        $container->register('settings.language', $languageSettings);

        $notificationsSettings = new NotificationsSettings($options);
        $container->register(NotificationsSettings::class, $notificationsSettings);
        $container->register('notifications.settings', $notificationsSettings);

        $notificationsTemplates = new NotificationsTemplateRenderer($notificationsSettings, $languageSettings);
        $container->register(NotificationsTemplateRenderer::class, $notificationsTemplates);
        $container->register('notifications.templates', $notificationsTemplates);

        Consent::init($options);
        Security::boot();

        $mailer = new Mailer();
        $mailer->registerHooks();
        $container->register(Mailer::class, $mailer);
        $container->register('core.mailer', $mailer);

        // Async mailer (lazy)
        $container->singleton('async.mailer', static function ($c) {
            $asyncMailer = new AsyncMailer($c->get(Mailer::class));
            $asyncMailer->boot();
            return $asyncMailer;
        });

        $styleSettings = new StyleSettings($options);
        $container->register(StyleSettings::class, $styleSettings);
        $container->register('settings.style', $styleSettings);

        global $wpdb;

        $paymentsRepository = new PaymentsRepository($wpdb);
        $container->register(PaymentsRepository::class, $paymentsRepository);
        $container->register('payments.repository', $paymentsRepository);

        $stripe = new StripeService($options, $paymentsRepository);
        $container->register(StripeService::class, $stripe);
        $container->register('payments.stripe', $stripe);

        $availability = new AvailabilityService($options, $wpdb);
        $container->register(AvailabilityService::class, $availability);
        $container->register('reservations.availability', $availability);

        $customersRepository = new CustomersRepository($wpdb);
        $container->register(CustomersRepository::class, $customersRepository);
        $container->register('customers.repository', $customersRepository);

        $reservationsRepository = new ReservationsRepository($wpdb);
        $container->register(ReservationsRepository::class, $reservationsRepository);
        $container->register('reservations.repository', $reservationsRepository);

        $privacy = new Privacy($options, $customersRepository, $reservationsRepository, $wpdb);
        $container->register(Privacy::class, $privacy);
        $container->register('core.privacy', $privacy);

        $googleCalendar = new GoogleCalendarService($options, $reservationsRepository);
        $googleCalendar->boot();
        $container->register(GoogleCalendarService::class, $googleCalendar);
        $container->register('calendar.google', $googleCalendar);

        $tablesRepository = new TablesRepository($wpdb);
        $container->register(TablesRepository::class, $tablesRepository);
        $container->register('tables.repository', $tablesRepository);

        $tablesLayout = new TablesLayoutService($tablesRepository);
        $container->register(TablesLayoutService::class, $tablesLayout);
        $container->register('tables.layout', $tablesLayout);

        $closuresService = new ClosuresService($wpdb, $options);
        $container->register(ClosuresService::class, $closuresService);
        $container->register('closures.service', $closuresService);

        // Creiamo prima il BrevoClient per poterlo iniettare nei servizi che ne hanno bisogno
        $brevoRepository = new BrevoRepository($wpdb);
        $container->register(BrevoRepository::class, $brevoRepository);
        $container->register('brevo.repository', $brevoRepository);

        $brevoClient = new BrevoClient($options);
        $container->register(BrevoClient::class, $brevoClient);
        $container->register('brevo.client', $brevoClient);

        $reservationsService = new ReservationsService(
            $reservationsRepository,
            $options,
            $languageSettings,
            $mailer,
            $customersRepository,
            $stripe,
            $notificationsSettings,
            $notificationsTemplates,
            $googleCalendar,
            $brevoClient,
            $brevoRepository
        );
        $container->register(ReservationsService::class, $reservationsService);
        $container->register('reservations.service', $reservationsService);

        $reportsService = new ReportsService($wpdb, $reservationsRepository, $paymentsRepository);
        $container->register(ReportsService::class, $reportsService);
        $container->register('reports.service', $reportsService);

        $diagnosticsService = new DiagnosticsService($wpdb, $paymentsRepository, $reservationsRepository);
        $container->register(DiagnosticsService::class, $diagnosticsService);
        $container->register('diagnostics.service', $diagnosticsService);

        $qaSeeder = new QASeeder($reservationsRepository, $customersRepository, $paymentsRepository, $wpdb);
        $container->register(QASeeder::class, $qaSeeder);
        $container->register('qa.seeder', $qaSeeder);

        $qaSeederRest = new QASeederREST($qaSeeder);
        $qaSeederRest->register();

        $qaSeederCli = new QASeederCLI($qaSeeder);
        $qaSeederCli->register();

        $brevoMapper = new BrevoMapper($options);
        $container->register(BrevoMapper::class, $brevoMapper);
        $container->register('brevo.mapper', $brevoMapper);

        $brevoAutomation = new BrevoAutomation(
            $options,
            $brevoClient,
            $brevoMapper,
            $brevoRepository,
            $reservationsRepository,
            $mailer,
            $notificationsSettings
        );
        $brevoAutomation->boot();
        $container->register(BrevoAutomation::class, $brevoAutomation);
        $container->register('brevo.automation', $brevoAutomation);

        $notificationsManager = new NotificationsManager(
            $options,
            $notificationsSettings,
            $notificationsTemplates,
            $reservationsRepository,
            $mailer,
            $brevoClient
        );
        $notificationsManager->boot();
        $container->register(NotificationsManager::class, $notificationsManager);
        $container->register('notifications.manager', $notificationsManager);

        $eventsService = new EventsService($wpdb, $reservationsService, $reservationsRepository, $customersRepository, $stripe);
        $container->register(EventsService::class, $eventsService);
        $container->register('events.service', $eventsService);

        $ga4     = new GA4Tracking($options);
        $ads     = new AdsTracking($options);
        $meta    = new MetaTracking($options);
        $clarity = new ClarityTracking($options);

        $container->register(GA4Tracking::class, $ga4);
        $container->register(AdsTracking::class, $ads);
        $container->register(MetaTracking::class, $meta);
        $container->register(ClarityTracking::class, $clarity);

        $trackingManager = new TrackingManager($options, $ga4, $ads, $meta, $clarity);
        $trackingManager->boot();
        $container->register(TrackingManager::class, $trackingManager);
        $container->register('tracking.manager', $trackingManager);

        if (is_admin()) {
            // Ordine logico dei menu:
            // 1. Menu principale + Impostazioni (AdminPages)
            // 2. Agenda (operazioni quotidiane)
            // 3. Sale & Tavoli (configurazione layout)
            // 4. Chiusure (gestione eccezioni)
            // 5. Report & Analytics (analisi dati)
            // 6. Diagnostica (debugging e log)
            
            $adminPages = new AdminPages();
            $adminPages->register();
            $container->register(AdminPages::class, $adminPages);
            $container->register('settings.admin_pages', $adminPages);

            $reservationsAdmin = new ReservationsAdminController();
            $reservationsAdmin->register();
            $container->register(ReservationsAdminController::class, $reservationsAdmin);
            $container->register('reservations.admin_controller', $reservationsAdmin);

            // Feature flag: Sale & Tavoli
            $tablesEnabled = (string) $options->getField('fp_resv_general', 'tables_enabled', '0') === '1';
            if ($tablesEnabled) {
                $tablesAdmin = new TablesAdminController($tablesLayout);
                $tablesAdmin->register();
                $container->register(TablesAdminController::class, $tablesAdmin);
                $container->register('tables.admin_controller', $tablesAdmin);
            }

            $closuresAdmin = new ClosuresAdminController($closuresService);
            $closuresAdmin->register();
            $container->register(ClosuresAdminController::class, $closuresAdmin);
            $container->register('closures.admin_controller', $closuresAdmin);

            $reportsAdmin = new ReportsAdminController($reportsService);
            $reportsAdmin->register();
            $container->register(ReportsAdminController::class, $reportsAdmin);
            $container->register('reports.admin_controller', $reportsAdmin);

            $diagnosticsAdmin = new DiagnosticsAdminController($diagnosticsService);
            $diagnosticsAdmin->register();
            $container->register(DiagnosticsAdminController::class, $diagnosticsAdmin);
            $container->register('diagnostics.admin_controller', $diagnosticsAdmin);
        }

        Migrations::run();

        I18n::init();

        $reservationsRest = new ReservationsREST($availability, $reservationsService, $reservationsRepository);
        $reservationsRest->register();
        $container->register(ReservationsREST::class, $reservationsRest);
        $container->register('reservations.rest', $reservationsRest);

        $eventsRest = new EventsREST($eventsService);
        $eventsRest->register();
        $container->register(EventsREST::class, $eventsRest);
        $container->register('events.rest', $eventsRest);

        $paymentsRest = new PaymentsREST($stripe, $paymentsRepository, $reservationsRepository);
        $paymentsRest->register();
        $container->register(PaymentsREST::class, $paymentsRest);
        $container->register('payments.rest', $paymentsRest);

        $adminRest = new ReservationsAdminREST($reservationsRepository, $reservationsService, $googleCalendar, $tablesLayout);
        $adminRest->register();
        $container->register(ReservationsAdminREST::class, $adminRest);
        $container->register('reservations.admin_rest', $adminRest);

        // Registra le API Sale & Tavoli solo se abilitate
        $tablesEnabled = (string) $options->getField('fp_resv_general', 'tables_enabled', '0') === '1';
        if ($tablesEnabled) {
            $tablesRest = new TablesREST($tablesLayout);
            $tablesRest->register();
            $container->register(TablesREST::class, $tablesRest);
            $container->register('tables.rest', $tablesRest);
        }

        $closuresRest = new ClosuresREST($closuresService);
        $closuresRest->register();
        $container->register(ClosuresREST::class, $closuresRest);
        $container->register('closures.rest', $closuresRest);

        $surveysRest = new SurveysREST($options, $languageSettings, $reservationsRepository, $wpdb);
        $surveysRest->register();
        $container->register(SurveysREST::class, $surveysRest);
        $container->register('surveys.rest', $surveysRest);

        $reportsRest = new ReportsREST($reportsService);
        $reportsRest->register();
        $container->register(ReportsREST::class, $reportsRest);
        $container->register('reports.rest', $reportsRest);

        $diagnosticsRest = new DiagnosticsREST($diagnosticsService);
        $diagnosticsRest->register();
        $container->register(DiagnosticsREST::class, $diagnosticsRest);
        $container->register('diagnostics.rest', $diagnosticsRest);

        $widgets = new WidgetController();
        $widgets->boot();

        $container->register(WidgetController::class, $widgets);
        $container->register('frontend.widgets', $widgets);

        $manage = new ManageController();
        $manage->boot();
        $container->register(ManageController::class, $manage);
        $container->register('frontend.manage', $manage);

        $eventsCpt = new EventsCPT();
        $eventsCpt->register();
        $container->register(EventsCPT::class, $eventsCpt);
        $container->register('events.cpt', $eventsCpt);

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
