<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Core\Adapters;
use FP\Resv\Core\AsyncMailer;
use FP\Resv\Core\Consent;
use FP\Resv\Core\Mailer;
use FP\Resv\Core\Privacy;
use FP\Resv\Core\Roles;
use FP\Resv\Core\Security;
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
use FP\Resv\Domain\Calendar\GoogleCalendarApiClient;
use FP\Resv\Domain\Calendar\GoogleCalendarBusyChecker;
use FP\Resv\Domain\Calendar\GoogleCalendarEventBuilder;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Calendar\GoogleCalendarWindowBuilder;
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
use FP\Resv\Domain\Payments\StripeApiClient;
use FP\Resv\Domain\Payments\StripeAmountCalculator;
use FP\Resv\Domain\Payments\StripeIntentBuilder;
use FP\Resv\Domain\Payments\StripeStatusMapper;
use FP\Resv\Domain\Payments\StripePaymentFormatter;
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
use FP\Resv\Domain\Tables\AdminController as TablesAdminController;
use FP\Resv\Domain\Tables\LayoutService as TablesLayoutService;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use FP\Resv\Domain\Tables\RoomTableNormalizer as TablesRoomTableNormalizer;
use FP\Resv\Domain\Tables\CapacityCalculator as TablesCapacityCalculator;
use FP\Resv\Domain\Tables\TableSuggestionEngine as TablesTableSuggestionEngine;
use FP\Resv\Domain\Tables\REST as TablesREST;
use FP\Resv\Domain\Tracking\Ads as AdsTracking;
use FP\Resv\Domain\Tracking\Clarity as ClarityTracking;
use FP\Resv\Domain\Tracking\GA4 as GA4Tracking;
use FP\Resv\Domain\Tracking\Manager as TrackingManager;
use FP\Resv\Domain\Tracking\Meta as MetaTracking;
use FP\Resv\Domain\Tracking\ReservationEventBuilder;
use FP\Resv\Domain\Tracking\ServerSideEventDispatcher;
use FP\Resv\Domain\Tracking\TrackingScriptGenerator;
use FP\Resv\Domain\Tracking\UTMAttributionHandler;
use FP\Resv\Domain\Reservations\Email\EmailContextBuilder;
use FP\Resv\Domain\Reservations\Email\EmailHeadersBuilder as ReservationsEmailHeadersBuilder;
use FP\Resv\Domain\Reservations\Email\FallbackMessageBuilder;
use FP\Resv\Domain\Reservations\Email\ICSGenerator;
use FP\Resv\Domain\Reservations\EmailService;
use FP\Resv\Frontend\AssetManager;
use FP\Resv\Frontend\ContentFilter;
use FP\Resv\Frontend\CriticalCssManager;
use FP\Resv\Frontend\PageBuilderCompatibility;
use FP\Resv\Frontend\Shortcodes;
use FP\Resv\Frontend\WidgetController;
use FP\Resv\Frontend\ShortcodeRenderer;
use FP\Resv\Frontend\DiagnosticShortcode;
use FP\Resv\Domain\Surveys\REST as SurveysREST;
use wpdb;
use function is_admin;

/**
 * Registra tutti i servizi nel container di dependency injection.
 * Estratto da Plugin per migliorare la manutenibilità.
 * 
 * @deprecated 0.9.0-rc11 Service registrations have been moved to Service Providers.
 *             This class is kept for backward compatibility but should not be used in new code.
 *             Use Kernel\Container with Service Providers instead.
 */
final class ServiceRegistry
{
    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Options $options,
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * Registra tutti i servizi nel container.
     */
    public function registerAll(): void
    {
        $this->registerCore();
        $this->registerSettings();
        $this->registerRepositories();
        $this->registerServices();
        $this->registerAdmin();
        $this->registerREST();
        $this->registerFrontend();
    }

    /**
     * Registra i servizi core.
     */
    private function registerCore(): void
    {
        // Core adapters (for testing)
        $this->container->singleton('wp.adapter', static function (): Adapters\WordPressAdapter {
            return new Adapters\WPFunctionsAdapter();
        });

        Consent::init($this->options);
        Security::boot();

        $mailer = new Mailer();
        $mailer->registerHooks();
        $this->container->register(Mailer::class, $mailer);
        $this->container->register('core.mailer', $mailer);

        // Async mailer (lazy)
        $this->container->singleton('async.mailer', static function ($c) {
            $asyncMailer = new AsyncMailer($c->get(Mailer::class));
            $asyncMailer->boot();
            return $asyncMailer;
        });
    }

    /**
     * Registra i servizi di settings.
     */
    private function registerSettings(): void
    {
        $this->container->register(Options::class, $this->options);
        $this->container->register('settings.options', $this->options);

        $languageSettings = new LanguageSettings($this->options);
        $this->container->register(LanguageSettings::class, $languageSettings);
        $this->container->register('settings.language', $languageSettings);

        $notificationsSettings = new NotificationsSettings($this->options);
        $this->container->register(NotificationsSettings::class, $notificationsSettings);
        $this->container->register('notifications.settings', $notificationsSettings);

        $notificationsTemplates = new NotificationsTemplateRenderer($notificationsSettings, $languageSettings);
        $this->container->register(NotificationsTemplateRenderer::class, $notificationsTemplates);
        $this->container->register('notifications.templates', $notificationsTemplates);

        // Style Settings con dipendenze estratte
        $styleColorCalculator = new \FP\Resv\Domain\Settings\Style\ColorCalculator();
        $styleTokenBuilder = new \FP\Resv\Domain\Settings\Style\StyleTokenBuilder($styleColorCalculator);
        $styleCssGenerator = new \FP\Resv\Domain\Settings\Style\StyleCssGenerator();
        $styleContrastReporter = new \FP\Resv\Domain\Settings\Style\ContrastReporter($styleColorCalculator);
        
        $styleSettings = new StyleSettings(
            $this->options,
            $styleColorCalculator,
            $styleTokenBuilder,
            $styleCssGenerator,
            $styleContrastReporter
        );
        $this->container->register(StyleSettings::class, $styleSettings);
        $this->container->register('settings.style', $styleSettings);
    }

    /**
     * Registra i repository.
     */
    private function registerRepositories(): void
    {
        $paymentsRepository = new PaymentsRepository($this->wpdb);
        $this->container->register(PaymentsRepository::class, $paymentsRepository);
        $this->container->register('payments.repository', $paymentsRepository);

        $customersRepository = new CustomersRepository($this->wpdb);
        $this->container->register(CustomersRepository::class, $customersRepository);
        $this->container->register('customers.repository', $customersRepository);

        $reservationsRepository = new ReservationsRepository($this->wpdb);
        $this->container->register(ReservationsRepository::class, $reservationsRepository);
        $this->container->register('reservations.repository', $reservationsRepository);

        $tablesRepository = new TablesRepository($this->wpdb);
        $this->container->register(TablesRepository::class, $tablesRepository);
        $this->container->register('tables.repository', $tablesRepository);

        $brevoRepository = new BrevoRepository($this->wpdb);
        $this->container->register(BrevoRepository::class, $brevoRepository);
        $this->container->register('brevo.repository', $brevoRepository);
    }

    /**
     * Registra i servizi di business logic.
     */
    private function registerServices(): void
    {
        $languageSettings = $this->container->get(LanguageSettings::class);
        $notificationsSettings = $this->container->get(NotificationsSettings::class);
        $notificationsTemplates = $this->container->get(NotificationsTemplateRenderer::class);
        $mailer = $this->container->get(Mailer::class);
        $reservationsRepository = $this->container->get(ReservationsRepository::class);
        $customersRepository = $this->container->get(CustomersRepository::class);
        $paymentsRepository = $this->container->get(PaymentsRepository::class);
        $tablesRepository = $this->container->get(TablesRepository::class);

        // Payments - StripeService requires 7 dependencies
        // StripeApiClient requires Options
        $stripeApiClient = new StripeApiClient($this->options);
        $stripeAmountCalculator = new StripeAmountCalculator($this->options);
        $stripeStatusMapper = new StripeStatusMapper();
        $stripePaymentFormatter = new StripePaymentFormatter();
        $stripeIntentBuilder = new StripeIntentBuilder($this->options, $stripeAmountCalculator);
        $stripe = new StripeService(
            $this->options,
            $paymentsRepository,
            $stripeApiClient,
            $stripeAmountCalculator,
            $stripeIntentBuilder,
            $stripeStatusMapper,
            $stripePaymentFormatter
        );
        $this->container->register(StripeService::class, $stripe);
        $this->container->register('payments.stripe', $stripe);

        // Availability
        $dataLoader = new DataLoader($this->wpdb);
        $closureEvaluator = new ClosureEvaluator();
        $tableSuggester = new TableSuggester();
        $scheduleParser = new ScheduleParser();
        $capacityResolver = new CapacityResolver();
        $slotStatusDeterminer = new SlotStatusDeterminer();
        $slotPayloadBuilder = new SlotPayloadBuilder();
        $reservationFilter = new ReservationFilter();
        $availability = new AvailabilityService(
            $this->options,
            $this->wpdb,
            $dataLoader,
            $closureEvaluator,
            $tableSuggester,
            $scheduleParser,
            $capacityResolver,
            $slotStatusDeterminer,
            $slotPayloadBuilder,
            $reservationFilter
        );
        $this->container->register(AvailabilityService::class, $availability);
        $this->container->register('reservations.availability', $availability);

        // Privacy
        $privacy = new Privacy($this->options, $customersRepository, $reservationsRepository, $this->wpdb);
        $this->container->register(Privacy::class, $privacy);
        $this->container->register('core.privacy', $privacy);

        // Google Calendar
        $googleCalendarApiClient = new GoogleCalendarApiClient($this->options);
        $googleCalendarEventBuilder = new GoogleCalendarEventBuilder($this->options);
        $googleCalendarWindowBuilder = new GoogleCalendarWindowBuilder();
        $googleCalendarBusyChecker = new GoogleCalendarBusyChecker($this->options, $googleCalendarApiClient);
        
        $googleCalendar = new GoogleCalendarService(
            $this->options,
            $reservationsRepository,
            $googleCalendarApiClient,
            $googleCalendarEventBuilder,
            $googleCalendarWindowBuilder,
            $googleCalendarBusyChecker
        );
        $googleCalendar->boot();
        $this->container->register(GoogleCalendarService::class, $googleCalendar);
        $this->container->register('calendar.google', $googleCalendar);

        // Tables Layout
        $tablesNormalizer = new TablesRoomTableNormalizer();
        $tablesCapacityCalculator = new TablesCapacityCalculator();
        $tablesSuggestionEngine = new TablesTableSuggestionEngine($tablesCapacityCalculator);
        $tablesLayout = new TablesLayoutService($tablesRepository, $tablesNormalizer, $tablesCapacityCalculator, $tablesSuggestionEngine);
        $this->container->register(TablesLayoutService::class, $tablesLayout);
        $this->container->register('tables.layout', $tablesLayout);

        // Closures
        $closuresRecurrenceHandler = new ClosuresRecurrenceHandler();
        $closuresPreviewGenerator = new ClosuresPreviewGenerator($closuresRecurrenceHandler);
        $closuresPayloadNormalizer = new ClosuresPayloadNormalizer($this->options);
        $closuresService = new ClosuresService($this->wpdb, $this->options, $closuresPayloadNormalizer, $closuresRecurrenceHandler, $closuresPreviewGenerator);
        $this->container->register(ClosuresService::class, $closuresService);
        $this->container->register('closures.service', $closuresService);

        // Brevo
        $brevoClient = new BrevoClient($this->options);
        $this->container->register(BrevoClient::class, $brevoClient);
        $this->container->register('brevo.client', $brevoClient);

        $brevoMapper = new BrevoMapper($this->options);
        $this->container->register(BrevoMapper::class, $brevoMapper);
        $this->container->register('brevo.mapper', $brevoMapper);

        $brevoListManager = new BrevoListManager($this->options);
        $brevoPhoneParser = new BrevoPhoneParser($this->options);
        $brevoEventDispatcher = new BrevoEventDispatcher($brevoClient, $this->container->get(BrevoRepository::class), $languageSettings, $this->options);
        
        $brevoAutomation = new BrevoAutomation(
            $this->options,
            $brevoClient,
            $brevoMapper,
            $this->container->get(BrevoRepository::class),
            $reservationsRepository,
            $mailer,
            $languageSettings,
            $brevoListManager,
            $brevoPhoneParser,
            $brevoEventDispatcher,
            $notificationsSettings
        );
        $brevoAutomation->boot();
        $this->container->register(BrevoAutomation::class, $brevoAutomation);
        $this->container->register('brevo.automation', $brevoAutomation);

        // Email Service
        $emailContextBuilder = new EmailContextBuilder($languageSettings, $notificationsSettings);
        $emailHeadersBuilder = new ReservationsEmailHeadersBuilder();
        $icsGenerator = new ICSGenerator();
        $fallbackBuilder = new FallbackMessageBuilder();
        
        $emailService = new EmailService(
            $mailer,
            $this->options,
            $languageSettings,
            $notificationsSettings,
            $notificationsTemplates,
            $emailContextBuilder,
            $emailHeadersBuilder,
            $icsGenerator,
            $fallbackBuilder
        );
        $this->container->register(EmailService::class, $emailService);
        $this->container->register('reservations.email', $emailService);

        // Availability Guard
        $availabilityGuard = new \FP\Resv\Domain\Reservations\AvailabilityGuard(
            $availability,
            $googleCalendar
        );
        $this->container->register(\FP\Resv\Domain\Reservations\AvailabilityGuard::class, $availabilityGuard);
        $this->container->register('reservations.availability_guard', $availabilityGuard);

        // Payment Service
        $paymentService = new \FP\Resv\Domain\Reservations\PaymentService($stripe);
        $this->container->register(\FP\Resv\Domain\Reservations\PaymentService::class, $paymentService);
        $this->container->register('reservations.payment', $paymentService);

        // Reservations Service
        $reservationPayloadSanitizer = new ReservationPayloadSanitizer($this->options, $languageSettings);
        $settingsResolver = new SettingsResolver($this->options);
        $brevoConfirmationEventSender = new BrevoConfirmationEventSender($brevoClient, $this->container->get(BrevoRepository::class));
        $manageUrlGenerator = new ManageUrlGenerator();

        $reservationsService = new ReservationsService(
            $reservationsRepository,
            $availability,
            $this->options,
            $languageSettings,
            $emailService,
            $availabilityGuard,
            $paymentService,
            $customersRepository,
            $notificationsSettings,
            $reservationPayloadSanitizer,
            $settingsResolver,
            $brevoConfirmationEventSender,
            $manageUrlGenerator,
            $googleCalendar,
            $brevoClient,
            $this->container->get(BrevoRepository::class)
        );
        $this->container->register(ReservationsService::class, $reservationsService);
        $this->container->register('reservations.service', $reservationsService);

        // Reports Service
        $reportsCsvExporter = new ReportsCsvExporter();
        $reportsDateRangeResolver = new ReportsDateRangeResolver();
        $reportsChannelClassifier = new ReportsChannelClassifier();
        $reportsDataNormalizer = new ReportsDataNormalizer();
        $reportsService = new ReportsService($this->wpdb, $reservationsRepository, $paymentsRepository, $reportsCsvExporter, $reportsDateRangeResolver, $reportsChannelClassifier, $reportsDataNormalizer);
        $this->container->register(ReportsService::class, $reportsService);
        $this->container->register('reports.service', $reportsService);

        // Diagnostics Service
        $diagnosticsLogExporter = new DiagnosticsLogExporter();
        $diagnosticsLogFormatter = new DiagnosticsLogFormatter();
        $diagnosticsService = new DiagnosticsService($this->wpdb, $paymentsRepository, $reservationsRepository, $diagnosticsLogExporter, $diagnosticsLogFormatter);
        $this->container->register(DiagnosticsService::class, $diagnosticsService);
        $this->container->register('diagnostics.service', $diagnosticsService);

        // QA Seeder
        $qaSeeder = new QASeeder($reservationsRepository, $customersRepository, $paymentsRepository, $this->wpdb);
        $this->container->register(QASeeder::class, $qaSeeder);
        $this->container->register('qa.seeder', $qaSeeder);

        $qaSeederRest = new QASeederREST($qaSeeder);
        $qaSeederRest->register();

        $qaSeederCli = new QASeederCLI($qaSeeder);
        $qaSeederCli->register();

        // Notifications Manager
        $notificationTimestampCalculator = new TimestampCalculator($notificationsSettings, $this->options);
        $notificationContextBuilder = new NotificationContextBuilder($this->options);
        $notificationHeadersBuilder = new EmailHeadersBuilder($notificationsSettings);
        $brevoEventSender = new BrevoEventSender($brevoClient, $manageUrlGenerator, $notificationsSettings);
        $notificationScheduler = new NotificationScheduler($notificationsSettings, $notificationTimestampCalculator);
        
        $notificationsManager = new NotificationsManager(
            $this->options,
            $notificationsSettings,
            $notificationsTemplates,
            $reservationsRepository,
            $mailer,
            $notificationScheduler,
            $notificationContextBuilder,
            $notificationHeadersBuilder,
            $manageUrlGenerator,
            $brevoEventSender
        );
        $notificationsManager->boot();
        $this->container->register(NotificationsManager::class, $notificationsManager);
        $this->container->register('notifications.manager', $notificationsManager);

        // Events Service
        $ticketCounter = new TicketCounter($this->wpdb);
        $eventPermalinkResolver = new EventPermalinkResolver();
        $eventFormatter = new EventFormatter($ticketCounter, $eventPermalinkResolver);
        $ticketCreator = new TicketCreator($this->wpdb);
        $ticketLister = new TicketLister($this->wpdb, $reservationsRepository, $customersRepository);
        $ticketCsvExporter = new TicketCsvExporter($ticketLister);
        $bookingPayloadSanitizer = new BookingPayloadSanitizer();
        $bookingPayloadValidator = new BookingPayloadValidator();
        $eventNotesBuilder = new EventNotesBuilder();
        
        $eventsService = new EventsService(
            $this->wpdb,
            $reservationsService,
            $reservationsRepository,
            $customersRepository,
            $stripe,
            $eventFormatter,
            $ticketCreator,
            $ticketCounter,
            $ticketLister,
            $ticketCsvExporter,
            $bookingPayloadSanitizer,
            $bookingPayloadValidator,
            $eventNotesBuilder
        );
        $this->container->register(EventsService::class, $eventsService);
        $this->container->register('events.service', $eventsService);

        // Tracking
        $ga4     = new GA4Tracking($this->options);
        $ads     = new AdsTracking($this->options);
        $meta    = new MetaTracking($this->options);
        $clarity = new ClarityTracking($this->options);

        $this->container->register(GA4Tracking::class, $ga4);
        $this->container->register(AdsTracking::class, $ads);
        $this->container->register(MetaTracking::class, $meta);
        $this->container->register(ClarityTracking::class, $clarity);

        $utmHandler = new UTMAttributionHandler();
        $scriptGenerator = new TrackingScriptGenerator();
        $eventBuilder = new ReservationEventBuilder($ads, $meta);
        $serverSideDispatcher = new ServerSideEventDispatcher($ga4, $meta);

        $trackingManager = new TrackingManager($this->options, $ga4, $ads, $meta, $clarity, $utmHandler, $scriptGenerator, $eventBuilder, $serverSideDispatcher);
        $trackingManager->boot();
        $this->container->register(TrackingManager::class, $trackingManager);
        $this->container->register('tracking.manager', $trackingManager);
    }

    /**
     * Registra i controller admin.
     */
    private function registerAdmin(): void
    {
        if (!is_admin()) {
            return;
        }

        $settingsSanitizer = new SettingsSanitizer();
        $settingsValidator = new SettingsValidator($settingsSanitizer);
        $adminPages = new AdminPages($settingsSanitizer, $settingsValidator);
        $adminPages->register();
        $this->container->register(AdminPages::class, $adminPages);
        $this->container->register('settings.admin_pages', $adminPages);

        $reservationsAdmin = new ReservationsAdminController();
        $reservationsAdmin->register();
        $this->container->register(ReservationsAdminController::class, $reservationsAdmin);
        $this->container->register('reservations.admin_controller', $reservationsAdmin);

        // Feature flag: Sale & Tavoli
        $tablesEnabled = (string) $this->options->getField('fp_resv_general', 'tables_enabled', '0') === '1';
        if ($tablesEnabled) {
            $tablesLayout = $this->container->get(TablesLayoutService::class);
            $tablesAdmin = new TablesAdminController($tablesLayout);
            $tablesAdmin->register();
            $this->container->register(TablesAdminController::class, $tablesAdmin);
            $this->container->register('tables.admin_controller', $tablesAdmin);
        }

        $closuresService = $this->container->get(ClosuresService::class);
        $closuresAdmin = new ClosuresAdminController($closuresService);
        $closuresAdmin->register();
        $this->container->register(ClosuresAdminController::class, $closuresAdmin);
        $this->container->register('closures.admin_controller', $closuresAdmin);

        $reportsService = $this->container->get(ReportsService::class);
        $reportsAdmin = new ReportsAdminController($reportsService);
        $reportsAdmin->register();
        $this->container->register(ReportsAdminController::class, $reportsAdmin);
        $this->container->register('reports.admin_controller', $reportsAdmin);

        $diagnosticsService = $this->container->get(DiagnosticsService::class);
        $diagnosticsAdmin = new DiagnosticsAdminController($diagnosticsService);
        $diagnosticsAdmin->register();
        $this->container->register(DiagnosticsAdminController::class, $diagnosticsAdmin);
        $this->container->register('diagnostics.admin_controller', $diagnosticsAdmin);
        
        // Store for later use (avoid recalculation)
        $this->container->register('feature.tables_enabled', $tablesEnabled);
    }

    /**
     * Registra gli endpoint REST.
     */
    private function registerREST(): void
    {
        $availability = $this->container->get(AvailabilityService::class);
        $reservationsService = $this->container->get(ReservationsService::class);
        $reservationsRepository = $this->container->get(ReservationsRepository::class);
        $stripe = $this->container->get(StripeService::class);
        $paymentsRepository = $this->container->get(PaymentsRepository::class);
        $eventsService = $this->container->get(EventsService::class);
        $googleCalendar = $this->container->get(GoogleCalendarService::class);
        $tablesLayout = $this->container->get(TablesLayoutService::class);
        $closuresService = $this->container->get(ClosuresService::class);
        $reportsService = $this->container->get(ReportsService::class);
        $diagnosticsService = $this->container->get(DiagnosticsService::class);
        $languageSettings = $this->container->get(LanguageSettings::class);

        // Reservations REST
        $availabilityHandler = new AvailabilityHandler($availability);
        $reservationHandler = new ReservationHandler($reservationsService, $reservationsRepository);
        $reservationsRest = new ReservationsREST($availability, $reservationsService, $reservationsRepository, $availabilityHandler, $reservationHandler);
        $reservationsRest->register();
        $this->container->register(ReservationsREST::class, $reservationsRest);
        $this->container->register('reservations.rest', $reservationsRest);

        // Endpoint diretto che bypassa WordPress REST per evitare interferenze
        $directEndpoint = new \FP\Resv\Domain\Reservations\DirectEndpoint($reservationsRest);
        $directEndpoint->register();
        $this->container->register(\FP\Resv\Domain\Reservations\DirectEndpoint::class, $directEndpoint);

        // Events REST
        $eventsRest = new EventsREST($eventsService);
        $eventsRest->register();
        $this->container->register(EventsREST::class, $eventsRest);
        $this->container->register('events.rest', $eventsRest);

        // Payments REST
        $paymentsRest = new PaymentsREST($stripe, $paymentsRepository, $reservationsRepository);
        $paymentsRest->register();
        $this->container->register(PaymentsREST::class, $paymentsRest);
        $this->container->register('payments.rest', $paymentsRest);

        // Admin REST
        $agendaHandler = new AgendaHandler($reservationsRepository);
        $statsHandler = new StatsHandler();
        $arrivalsHandler = new ArrivalsHandler($reservationsRepository);
        $overviewHandler = new OverviewHandler($reservationsRepository, $agendaHandler, $statsHandler);
        $payloadExtractor = new ReservationPayloadExtractor();
        
        $adminRest = new ReservationsAdminREST(
            $reservationsRepository,
            $reservationsService,
            $agendaHandler,
            $statsHandler,
            $arrivalsHandler,
            $overviewHandler,
            $payloadExtractor,
            $googleCalendar,
            $tablesLayout
        );
        $adminRest->register();
        $this->container->register(ReservationsAdminREST::class, $adminRest);
        $this->container->register('reservations.admin_rest', $adminRest);

        // Tables REST (solo se abilitato)
        $tablesEnabled = $this->container->has('feature.tables_enabled')
            ? $this->container->get('feature.tables_enabled')
            : (string) $this->options->getField('fp_resv_general', 'tables_enabled', '0') === '1';
            
        if ($tablesEnabled) {
            $tablesRest = new TablesREST($tablesLayout);
            $tablesRest->register();
            $this->container->register(TablesREST::class, $tablesRest);
            $this->container->register('tables.rest', $tablesRest);
        }

        // Closures REST
        $closuresDateRangeResolver = new ClosuresDateRangeResolver();
        $closuresPayloadCollector = new ClosuresPayloadCollector();
        $closuresModelExporter = new ClosuresModelExporter();
        $closuresResponseBuilder = new ClosuresResponseBuilder();
        
        $closuresRest = new ClosuresREST(
            $closuresService,
            $closuresDateRangeResolver,
            $closuresPayloadCollector,
            $closuresModelExporter,
            $closuresResponseBuilder
        );
        $closuresRest->register();
        $this->container->register(ClosuresREST::class, $closuresRest);
        $this->container->register('closures.rest', $closuresRest);
        
        // AJAX handler per Closures (più robusto di REST)
        // Registra gli hook AJAX PRIMA di altri plugin per evitare che errori fatali
        // in altri plugin impediscano l'esecuzione (es. FP-Performance)
        try {
            $closuresAjax = new ClosuresAjaxHandler($closuresService);
            $closuresAjax->register();
            $this->container->register(ClosuresAjaxHandler::class, $closuresAjax);
        } catch (\Throwable $e) {
            // Log error but don't break plugin initialization
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Restaurant Reservations] Errore registrazione ClosuresAjaxHandler: ' . $e->getMessage());
            }
        }
        
        // Debug hook per AJAX requests
        try {
            $ajaxDebug = new \FP\Resv\Domain\Closures\AjaxDebug();
            $ajaxDebug->register();
        } catch (\Throwable $e) {
            // Log error but don't break plugin initialization
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Restaurant Reservations] Errore registrazione AjaxDebug: ' . $e->getMessage());
            }
        }

        // Surveys REST
        $surveysRest = new SurveysREST($this->options, $languageSettings, $reservationsRepository, $this->wpdb);
        $surveysRest->register();
        $this->container->register(SurveysREST::class, $surveysRest);
        $this->container->register('surveys.rest', $surveysRest);

        // Reports REST
        $reportsRest = new ReportsREST($reportsService);
        $reportsRest->register();
        $this->container->register(ReportsREST::class, $reportsRest);
        $this->container->register('reports.rest', $reportsRest);

        // Diagnostics REST
        $diagnosticsRest = new DiagnosticsREST($diagnosticsService);
        $diagnosticsRest->register();
        $this->container->register(DiagnosticsREST::class, $diagnosticsRest);
        $this->container->register('diagnostics.rest', $diagnosticsRest);
    }

    /**
     * Registra i componenti frontend.
     */
    private function registerFrontend(): void
    {
        $assetManager = new AssetManager();
        $cssManager = new CriticalCssManager();
        $pageBuilderCompat = new PageBuilderCompatibility();
        $contentFilter = new ContentFilter();
        
        $widgets = new WidgetController($assetManager, $cssManager, $pageBuilderCompat, $contentFilter);
        $widgets->boot();

        $this->container->register(WidgetController::class, $widgets);
        $this->container->register('frontend.widgets', $widgets);
        
        $shortcodeRenderer = new ShortcodeRenderer($this->container->get(ReservationsService::class), $this->container->get(AvailabilityService::class));
        $diagnosticShortcode = new DiagnosticShortcode($this->container->get(DiagnosticsService::class));
        $shortcodes = new Shortcodes($shortcodeRenderer, $diagnosticShortcode);
        $shortcodes->register();
        $this->container->register(Shortcodes::class, $shortcodes);
        $this->container->register('frontend.shortcodes', $shortcodes);

        // Events CPT
        $eventsCpt = new EventsCPT();
        $eventsCpt->register();
        $this->container->register(EventsCPT::class, $eventsCpt);
    }
}

