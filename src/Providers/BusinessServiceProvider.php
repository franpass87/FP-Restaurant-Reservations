<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Business Service Provider
 * 
 * Registers business logic services, domain services, and related components.
 * Always loads regardless of context.
 *
 * @package FP\Resv\Providers
 */
final class BusinessServiceProvider extends ServiceProvider
{
    /**
     * Register business services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerPrivacy($container);
        $this->registerSettings($container);
        $this->registerAvailability($container);
        $this->registerReservations($container);
        $this->registerPayments($container);
        $this->registerCalendar($container);
        $this->registerTables($container);
        $this->registerClosures($container);
        $this->registerBrevo($container);
        $this->registerEmail($container);
        $this->registerNotifications($container);
        $this->registerEvents($container);
        $this->registerReports($container);
        $this->registerDiagnostics($container);
        $this->registerTracking($container);
        $this->registerQA($container);
    }
    
    /**
     * Boot business services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Boot services that need initialization
        if ($container->has(\FP\Resv\Domain\Calendar\GoogleCalendarService::class)) {
            $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarService::class)->boot();
        }
        
        if ($container->has(\FP\Resv\Domain\Brevo\AutomationService::class)) {
            $container->get(\FP\Resv\Domain\Brevo\AutomationService::class)->boot();
        }
        
        if ($container->has(\FP\Resv\Domain\Notifications\Manager::class)) {
            $container->get(\FP\Resv\Domain\Notifications\Manager::class)->boot();
        }
        
        if ($container->has(\FP\Resv\Domain\Tracking\Manager::class)) {
            $container->get(\FP\Resv\Domain\Tracking\Manager::class)->boot();
        }
    }
    
    /**
     * Register settings services
     */
    private function registerSettings(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Settings\Language::class,
            function (Container $container) {
                // Language requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Settings\Language($options);
            }
        );
        
        $container->alias('settings.language', \FP\Resv\Domain\Settings\Language::class);
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\Settings::class,
            function (Container $container) {
                // Notifications Settings requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Notifications\Settings($options);
            }
        );
        
        $container->alias('notifications.settings', \FP\Resv\Domain\Notifications\Settings::class);
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\TemplateRenderer::class,
            function (Container $container) {
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                return new \FP\Resv\Domain\Notifications\TemplateRenderer($notificationsSettings, $languageSettings);
            }
        );
        
        $container->alias('notifications.templates', \FP\Resv\Domain\Notifications\TemplateRenderer::class);
        
        // Style Settings
        $container->singleton(
            \FP\Resv\Domain\Settings\Style\ColorCalculator::class,
            \FP\Resv\Domain\Settings\Style\ColorCalculator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\Style\StyleTokenBuilder::class,
            function (Container $container) {
                $colorCalculator = $container->get(\FP\Resv\Domain\Settings\Style\ColorCalculator::class);
                return new \FP\Resv\Domain\Settings\Style\StyleTokenBuilder($colorCalculator);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\Style\StyleCssGenerator::class,
            \FP\Resv\Domain\Settings\Style\StyleCssGenerator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\Style\ContrastReporter::class,
            function (Container $container) {
                $colorCalculator = $container->get(\FP\Resv\Domain\Settings\Style\ColorCalculator::class);
                return new \FP\Resv\Domain\Settings\Style\ContrastReporter($colorCalculator);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Settings\Style::class,
            function (Container $container) {
                // Style requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $colorCalculator = $container->get(\FP\Resv\Domain\Settings\Style\ColorCalculator::class);
                $tokenBuilder = $container->get(\FP\Resv\Domain\Settings\Style\StyleTokenBuilder::class);
                $cssGenerator = $container->get(\FP\Resv\Domain\Settings\Style\StyleCssGenerator::class);
                $contrastReporter = $container->get(\FP\Resv\Domain\Settings\Style\ContrastReporter::class);
                
                return new \FP\Resv\Domain\Settings\Style(
                    $options,
                    $colorCalculator,
                    $tokenBuilder,
                    $cssGenerator,
                    $contrastReporter
                );
            }
        );
        
        $container->alias('settings.style', \FP\Resv\Domain\Settings\Style::class);
    }
    
    /**
     * Register Privacy service
     */
    private function registerPrivacy(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Core\Privacy::class,
            function (Container $container) {
                // Privacy requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $customersRepository = $container->get(\FP\Resv\Domain\Customers\Repository::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Core\Privacy($options, $customersRepository, $reservationsRepository, $db);
            }
        );
        
        $container->alias('core.privacy', \FP\Resv\Core\Privacy::class);
    }
    
    /**
     * Register availability service
     */
    private function registerAvailability(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\DataLoader::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Reservations\Availability\DataLoader($db);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\ClosureEvaluator::class,
            \FP\Resv\Domain\Reservations\Availability\ClosureEvaluator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\TableSuggester::class,
            \FP\Resv\Domain\Reservations\Availability\TableSuggester::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\ScheduleParser::class,
            \FP\Resv\Domain\Reservations\Availability\ScheduleParser::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\CapacityResolver::class,
            \FP\Resv\Domain\Reservations\Availability\CapacityResolver::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer::class,
            \FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder::class,
            \FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability\ReservationFilter::class,
            \FP\Resv\Domain\Reservations\Availability\ReservationFilter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Availability::class,
            function (Container $container) {
                // Availability requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                $dataLoader = $container->get(\FP\Resv\Domain\Reservations\Availability\DataLoader::class);
                $closureEvaluator = $container->get(\FP\Resv\Domain\Reservations\Availability\ClosureEvaluator::class);
                $tableSuggester = $container->get(\FP\Resv\Domain\Reservations\Availability\TableSuggester::class);
                $scheduleParser = $container->get(\FP\Resv\Domain\Reservations\Availability\ScheduleParser::class);
                $capacityResolver = $container->get(\FP\Resv\Domain\Reservations\Availability\CapacityResolver::class);
                $slotStatusDeterminer = $container->get(\FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer::class);
                $slotPayloadBuilder = $container->get(\FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder::class);
                $reservationFilter = $container->get(\FP\Resv\Domain\Reservations\Availability\ReservationFilter::class);
                
                return new \FP\Resv\Domain\Reservations\Availability(
                    $options,
                    $db,
                    $dataLoader,
                    $closureEvaluator,
                    $tableSuggester,
                    $scheduleParser,
                    $capacityResolver,
                    $slotStatusDeterminer,
                    $slotPayloadBuilder,
                    $reservationFilter
                );
            }
        );
        
        $container->alias('reservations.availability', \FP\Resv\Domain\Reservations\Availability::class);
    }
    
    /**
     * Register reservations service
     */
    private function registerReservations(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Reservations\ReservationPayloadSanitizer::class,
            function (Container $container) {
                // ReservationPayloadSanitizer requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                return new \FP\Resv\Domain\Reservations\ReservationPayloadSanitizer($options, $languageSettings);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\SettingsResolver::class,
            function (Container $container) {
                // SettingsResolver requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Reservations\SettingsResolver($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\BrevoConfirmationEventSender::class,
            function (Container $container) {
                $brevoClient = $container->get(\FP\Resv\Domain\Brevo\Client::class);
                $brevoRepository = $container->get(\FP\Resv\Domain\Brevo\Repository::class);
                return new \FP\Resv\Domain\Reservations\BrevoConfirmationEventSender($brevoClient, $brevoRepository);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\ManageUrlGenerator::class,
            \FP\Resv\Domain\Notifications\ManageUrlGenerator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\AvailabilityGuard::class,
            function (Container $container) {
                $availability = $container->get(\FP\Resv\Domain\Reservations\Availability::class);
                $googleCalendar = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarService::class);
                return new \FP\Resv\Domain\Reservations\AvailabilityGuard($availability, $googleCalendar);
            }
        );
        
        $container->alias('reservations.availability_guard', \FP\Resv\Domain\Reservations\AvailabilityGuard::class);
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Service::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $availability = $container->get(\FP\Resv\Domain\Reservations\Availability::class);
                // Service requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $emailService = $container->get(\FP\Resv\Domain\Reservations\EmailService::class);
                $availabilityGuard = $container->get(\FP\Resv\Domain\Reservations\AvailabilityGuard::class);
                $paymentService = $container->get(\FP\Resv\Domain\Reservations\PaymentService::class);
                $customersRepository = $container->get(\FP\Resv\Domain\Customers\Repository::class);
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                $reservationPayloadSanitizer = $container->get(\FP\Resv\Domain\Reservations\ReservationPayloadSanitizer::class);
                $settingsResolver = $container->get(\FP\Resv\Domain\Reservations\SettingsResolver::class);
                $brevoConfirmationEventSender = $container->get(\FP\Resv\Domain\Reservations\BrevoConfirmationEventSender::class);
                $manageUrlGenerator = $container->get(\FP\Resv\Domain\Notifications\ManageUrlGenerator::class);
                $googleCalendar = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarService::class);
                $brevoClient = $container->get(\FP\Resv\Domain\Brevo\Client::class);
                $brevoRepository = $container->get(\FP\Resv\Domain\Brevo\Repository::class);
                
                return new \FP\Resv\Domain\Reservations\Service(
                    $reservationsRepository,
                    $availability,
                    $options,
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
                    $brevoRepository
                );
            }
        );
        
        $container->alias('reservations.service', \FP\Resv\Domain\Reservations\Service::class);
    }
    
    /**
     * Register payments service
     */
    private function registerPayments(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Payments\StripeApiClient::class,
            function (Container $container) {
                // StripeApiClient requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Payments\StripeApiClient($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Payments\StripeAmountCalculator::class,
            function (Container $container) {
                // StripeAmountCalculator requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Payments\StripeAmountCalculator($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Payments\StripeStatusMapper::class,
            \FP\Resv\Domain\Payments\StripeStatusMapper::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Payments\StripePaymentFormatter::class,
            \FP\Resv\Domain\Payments\StripePaymentFormatter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Payments\StripeIntentBuilder::class,
            function (Container $container) {
                // StripeIntentBuilder requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $amountCalculator = $container->get(\FP\Resv\Domain\Payments\StripeAmountCalculator::class);
                return new \FP\Resv\Domain\Payments\StripeIntentBuilder($options, $amountCalculator);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Payments\StripeService::class,
            function (Container $container) {
                // StripeService requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $paymentsRepository = $container->get(\FP\Resv\Domain\Payments\Repository::class);
                $stripeApiClient = $container->get(\FP\Resv\Domain\Payments\StripeApiClient::class);
                $stripeAmountCalculator = $container->get(\FP\Resv\Domain\Payments\StripeAmountCalculator::class);
                $stripeIntentBuilder = $container->get(\FP\Resv\Domain\Payments\StripeIntentBuilder::class);
                $stripeStatusMapper = $container->get(\FP\Resv\Domain\Payments\StripeStatusMapper::class);
                $stripePaymentFormatter = $container->get(\FP\Resv\Domain\Payments\StripePaymentFormatter::class);
                
                return new \FP\Resv\Domain\Payments\StripeService(
                    $options,
                    $paymentsRepository,
                    $stripeApiClient,
                    $stripeAmountCalculator,
                    $stripeIntentBuilder,
                    $stripeStatusMapper,
                    $stripePaymentFormatter
                );
            }
        );
        
        $container->alias('payments.stripe', \FP\Resv\Domain\Payments\StripeService::class);
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\PaymentService::class,
            function (Container $container) {
                $stripe = $container->get(\FP\Resv\Domain\Payments\StripeService::class);
                return new \FP\Resv\Domain\Reservations\PaymentService($stripe);
            }
        );
        
        $container->alias('reservations.payment', \FP\Resv\Domain\Reservations\PaymentService::class);
    }
    
    /**
     * Register calendar service
     */
    private function registerCalendar(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Calendar\GoogleCalendarApiClient::class,
            function (Container $container) {
                // GoogleCalendarApiClient requires FP\Resv\Domain\Settings\Options (final class)
                // which uses get_option() directly, so we create it directly
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Calendar\GoogleCalendarApiClient($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Calendar\GoogleCalendarEventBuilder::class,
            function (Container $container) {
                // GoogleCalendarEventBuilder requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Calendar\GoogleCalendarEventBuilder($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Calendar\GoogleCalendarWindowBuilder::class,
            \FP\Resv\Domain\Calendar\GoogleCalendarWindowBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Calendar\GoogleCalendarBusyChecker::class,
            function (Container $container) {
                // GoogleCalendarBusyChecker requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $apiClient = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarApiClient::class);
                return new \FP\Resv\Domain\Calendar\GoogleCalendarBusyChecker($options, $apiClient);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Calendar\GoogleCalendarService::class,
            function (Container $container) {
                // GoogleCalendarService requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $apiClient = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarApiClient::class);
                $eventBuilder = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarEventBuilder::class);
                $windowBuilder = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarWindowBuilder::class);
                $busyChecker = $container->get(\FP\Resv\Domain\Calendar\GoogleCalendarBusyChecker::class);
                
                return new \FP\Resv\Domain\Calendar\GoogleCalendarService(
                    $options,
                    $reservationsRepository,
                    $apiClient,
                    $eventBuilder,
                    $windowBuilder,
                    $busyChecker
                );
            }
        );
        
        $container->alias('calendar.google', \FP\Resv\Domain\Calendar\GoogleCalendarService::class);
    }
    
    /**
     * Register tables service
     */
    private function registerTables(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Tables\RoomTableNormalizer::class,
            \FP\Resv\Domain\Tables\RoomTableNormalizer::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tables\CapacityCalculator::class,
            \FP\Resv\Domain\Tables\CapacityCalculator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tables\TableSuggestionEngine::class,
            function (Container $container) {
                $capacityCalculator = $container->get(\FP\Resv\Domain\Tables\CapacityCalculator::class);
                return new \FP\Resv\Domain\Tables\TableSuggestionEngine($capacityCalculator);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tables\LayoutService::class,
            function (Container $container) {
                $tablesRepository = $container->get(\FP\Resv\Domain\Tables\Repository::class);
                $normalizer = $container->get(\FP\Resv\Domain\Tables\RoomTableNormalizer::class);
                $capacityCalculator = $container->get(\FP\Resv\Domain\Tables\CapacityCalculator::class);
                $suggestionEngine = $container->get(\FP\Resv\Domain\Tables\TableSuggestionEngine::class);
                
                return new \FP\Resv\Domain\Tables\LayoutService(
                    $tablesRepository,
                    $normalizer,
                    $capacityCalculator,
                    $suggestionEngine
                );
            }
        );
        
        $container->alias('tables.layout', \FP\Resv\Domain\Tables\LayoutService::class);
    }
    
    /**
     * Register closures service
     */
    private function registerClosures(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Closures\RecurrenceHandler::class,
            \FP\Resv\Domain\Closures\RecurrenceHandler::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\PreviewGenerator::class,
            function (Container $container) {
                $recurrenceHandler = $container->get(\FP\Resv\Domain\Closures\RecurrenceHandler::class);
                return new \FP\Resv\Domain\Closures\PreviewGenerator($recurrenceHandler);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\PayloadNormalizer::class,
            function (Container $container) {
                // PayloadNormalizer requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Closures\PayloadNormalizer($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Closures\Service::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                // Closures Service requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $payloadNormalizer = $container->get(\FP\Resv\Domain\Closures\PayloadNormalizer::class);
                $recurrenceHandler = $container->get(\FP\Resv\Domain\Closures\RecurrenceHandler::class);
                $previewGenerator = $container->get(\FP\Resv\Domain\Closures\PreviewGenerator::class);
                
                return new \FP\Resv\Domain\Closures\Service(
                    $db,
                    $options,
                    $payloadNormalizer,
                    $recurrenceHandler,
                    $previewGenerator
                );
            }
        );
        
        $container->alias('closures.service', \FP\Resv\Domain\Closures\Service::class);
    }
    
    /**
     * Register Brevo services
     */
    private function registerBrevo(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Brevo\Client::class,
            function (Container $container) {
                // Brevo Client requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Brevo\Client($options);
            }
        );
        
        $container->alias('brevo.client', \FP\Resv\Domain\Brevo\Client::class);
        
        $container->singleton(
            \FP\Resv\Domain\Brevo\Mapper::class,
            function (Container $container) {
                // Brevo Mapper requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Brevo\Mapper($options);
            }
        );
        
        $container->alias('brevo.mapper', \FP\Resv\Domain\Brevo\Mapper::class);
        
        $container->singleton(
            \FP\Resv\Domain\Brevo\ListManager::class,
            function (Container $container) {
                // Brevo ListManager requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Brevo\ListManager($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Brevo\PhoneCountryParser::class,
            function (Container $container) {
                // Brevo PhoneCountryParser requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Brevo\PhoneCountryParser($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Brevo\EventDispatcher::class,
            function (Container $container) {
                $brevoClient = $container->get(\FP\Resv\Domain\Brevo\Client::class);
                $brevoRepository = $container->get(\FP\Resv\Domain\Brevo\Repository::class);
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                // Brevo EventDispatcher requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                
                return new \FP\Resv\Domain\Brevo\EventDispatcher(
                    $brevoClient,
                    $brevoRepository,
                    $languageSettings,
                    $options
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Brevo\AutomationService::class,
            function (Container $container) {
                // Brevo AutomationService requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $brevoClient = $container->get(\FP\Resv\Domain\Brevo\Client::class);
                $brevoMapper = $container->get(\FP\Resv\Domain\Brevo\Mapper::class);
                $brevoRepository = $container->get(\FP\Resv\Domain\Brevo\Repository::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $mailer = $container->get(\FP\Resv\Core\Mailer::class);
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $brevoListManager = $container->get(\FP\Resv\Domain\Brevo\ListManager::class);
                $brevoPhoneParser = $container->get(\FP\Resv\Domain\Brevo\PhoneCountryParser::class);
                $brevoEventDispatcher = $container->get(\FP\Resv\Domain\Brevo\EventDispatcher::class);
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                
                return new \FP\Resv\Domain\Brevo\AutomationService(
                    $options,
                    $brevoClient,
                    $brevoMapper,
                    $brevoRepository,
                    $reservationsRepository,
                    $mailer,
                    $languageSettings,
                    $brevoListManager,
                    $brevoPhoneParser,
                    $brevoEventDispatcher,
                    $notificationsSettings
                );
            }
        );
        
        $container->alias('brevo.automation', \FP\Resv\Domain\Brevo\AutomationService::class);
    }
    
    /**
     * Register email service
     */
    private function registerEmail(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Reservations\Email\EmailContextBuilder::class,
            function (Container $container) {
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                return new \FP\Resv\Domain\Reservations\Email\EmailContextBuilder($languageSettings, $notificationsSettings);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Email\EmailHeadersBuilder::class,
            \FP\Resv\Domain\Reservations\Email\EmailHeadersBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Email\ICSGenerator::class,
            \FP\Resv\Domain\Reservations\Email\ICSGenerator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\Email\FallbackMessageBuilder::class,
            \FP\Resv\Domain\Reservations\Email\FallbackMessageBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reservations\EmailService::class,
            function (Container $container) {
                $mailer = $container->get(\FP\Resv\Core\Mailer::class);
                // EmailService requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $languageSettings = $container->get(\FP\Resv\Domain\Settings\Language::class);
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                $notificationsTemplates = $container->get(\FP\Resv\Domain\Notifications\TemplateRenderer::class);
                $emailContextBuilder = $container->get(\FP\Resv\Domain\Reservations\Email\EmailContextBuilder::class);
                $emailHeadersBuilder = $container->get(\FP\Resv\Domain\Reservations\Email\EmailHeadersBuilder::class);
                $icsGenerator = $container->get(\FP\Resv\Domain\Reservations\Email\ICSGenerator::class);
                $fallbackBuilder = $container->get(\FP\Resv\Domain\Reservations\Email\FallbackMessageBuilder::class);
                
                return new \FP\Resv\Domain\Reservations\EmailService(
                    $mailer,
                    $options,
                    $languageSettings,
                    $notificationsSettings,
                    $notificationsTemplates,
                    $emailContextBuilder,
                    $emailHeadersBuilder,
                    $icsGenerator,
                    $fallbackBuilder
                );
            }
        );
        
        $container->alias('reservations.email', \FP\Resv\Domain\Reservations\EmailService::class);
    }
    
    /**
     * Register notifications service
     */
    private function registerNotifications(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Notifications\TimestampCalculator::class,
            function (Container $container) {
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                // TimestampCalculator requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Notifications\TimestampCalculator($notificationsSettings, $options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\NotificationContextBuilder::class,
            function (Container $container) {
                // NotificationContextBuilder requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Notifications\NotificationContextBuilder($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\EmailHeadersBuilder::class,
            function (Container $container) {
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                return new \FP\Resv\Domain\Notifications\EmailHeadersBuilder($notificationsSettings);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\BrevoEventSender::class,
            function (Container $container) {
                $brevoClient = $container->get(\FP\Resv\Domain\Brevo\Client::class);
                $manageUrlGenerator = $container->get(\FP\Resv\Domain\Notifications\ManageUrlGenerator::class);
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                return new \FP\Resv\Domain\Notifications\BrevoEventSender($brevoClient, $manageUrlGenerator, $notificationsSettings);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\NotificationScheduler::class,
            function (Container $container) {
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                $timestampCalculator = $container->get(\FP\Resv\Domain\Notifications\TimestampCalculator::class);
                return new \FP\Resv\Domain\Notifications\NotificationScheduler($notificationsSettings, $timestampCalculator);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Notifications\Manager::class,
            function (Container $container) {
                // Notifications Manager requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $notificationsSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
                $notificationsTemplates = $container->get(\FP\Resv\Domain\Notifications\TemplateRenderer::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $mailer = $container->get(\FP\Resv\Core\Mailer::class);
                $notificationScheduler = $container->get(\FP\Resv\Domain\Notifications\NotificationScheduler::class);
                $notificationContextBuilder = $container->get(\FP\Resv\Domain\Notifications\NotificationContextBuilder::class);
                $notificationHeadersBuilder = $container->get(\FP\Resv\Domain\Notifications\EmailHeadersBuilder::class);
                $manageUrlGenerator = $container->get(\FP\Resv\Domain\Notifications\ManageUrlGenerator::class);
                $brevoEventSender = $container->get(\FP\Resv\Domain\Notifications\BrevoEventSender::class);
                
                return new \FP\Resv\Domain\Notifications\Manager(
                    $options,
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
            }
        );
        
        $container->alias('notifications.manager', \FP\Resv\Domain\Notifications\Manager::class);
    }
    
    /**
     * Register events service
     */
    private function registerEvents(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Events\TicketCounter::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Events\TicketCounter($db);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\EventPermalinkResolver::class,
            \FP\Resv\Domain\Events\EventPermalinkResolver::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\EventFormatter::class,
            function (Container $container) {
                $ticketCounter = $container->get(\FP\Resv\Domain\Events\TicketCounter::class);
                $permalinkResolver = $container->get(\FP\Resv\Domain\Events\EventPermalinkResolver::class);
                return new \FP\Resv\Domain\Events\EventFormatter($ticketCounter, $permalinkResolver);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\TicketCreator::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Events\TicketCreator($db);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\TicketLister::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $customersRepository = $container->get(\FP\Resv\Domain\Customers\Repository::class);
                return new \FP\Resv\Domain\Events\TicketLister($db, $reservationsRepository, $customersRepository);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\TicketCsvExporter::class,
            function (Container $container) {
                $ticketLister = $container->get(\FP\Resv\Domain\Events\TicketLister::class);
                return new \FP\Resv\Domain\Events\TicketCsvExporter($ticketLister);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\BookingPayloadSanitizer::class,
            \FP\Resv\Domain\Events\BookingPayloadSanitizer::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\BookingPayloadValidator::class,
            \FP\Resv\Domain\Events\BookingPayloadValidator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\EventNotesBuilder::class,
            \FP\Resv\Domain\Events\EventNotesBuilder::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Events\Service::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                $reservationsService = $container->get(\FP\Resv\Domain\Reservations\Service::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $customersRepository = $container->get(\FP\Resv\Domain\Customers\Repository::class);
                $stripe = $container->get(\FP\Resv\Domain\Payments\StripeService::class);
                $eventFormatter = $container->get(\FP\Resv\Domain\Events\EventFormatter::class);
                $ticketCreator = $container->get(\FP\Resv\Domain\Events\TicketCreator::class);
                $ticketCounter = $container->get(\FP\Resv\Domain\Events\TicketCounter::class);
                $ticketLister = $container->get(\FP\Resv\Domain\Events\TicketLister::class);
                $ticketCsvExporter = $container->get(\FP\Resv\Domain\Events\TicketCsvExporter::class);
                $bookingPayloadSanitizer = $container->get(\FP\Resv\Domain\Events\BookingPayloadSanitizer::class);
                $bookingPayloadValidator = $container->get(\FP\Resv\Domain\Events\BookingPayloadValidator::class);
                $eventNotesBuilder = $container->get(\FP\Resv\Domain\Events\EventNotesBuilder::class);
                
                return new \FP\Resv\Domain\Events\Service(
                    $db,
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
            }
        );
        
        $container->alias('events.service', \FP\Resv\Domain\Events\Service::class);
    }
    
    /**
     * Register reports service
     */
    private function registerReports(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Reports\CsvExporter::class,
            \FP\Resv\Domain\Reports\CsvExporter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reports\DateRangeResolver::class,
            \FP\Resv\Domain\Reports\DateRangeResolver::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reports\ChannelClassifier::class,
            \FP\Resv\Domain\Reports\ChannelClassifier::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reports\DataNormalizer::class,
            \FP\Resv\Domain\Reports\DataNormalizer::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Reports\Service::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $paymentsRepository = $container->get(\FP\Resv\Domain\Payments\Repository::class);
                $csvExporter = $container->get(\FP\Resv\Domain\Reports\CsvExporter::class);
                $dateRangeResolver = $container->get(\FP\Resv\Domain\Reports\DateRangeResolver::class);
                $channelClassifier = $container->get(\FP\Resv\Domain\Reports\ChannelClassifier::class);
                $dataNormalizer = $container->get(\FP\Resv\Domain\Reports\DataNormalizer::class);
                
                return new \FP\Resv\Domain\Reports\Service(
                    $db,
                    $reservationsRepository,
                    $paymentsRepository,
                    $csvExporter,
                    $dateRangeResolver,
                    $channelClassifier,
                    $dataNormalizer
                );
            }
        );
        
        $container->alias('reports.service', \FP\Resv\Domain\Reports\Service::class);
    }
    
    /**
     * Register diagnostics service
     */
    private function registerDiagnostics(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Diagnostics\LogExporter::class,
            \FP\Resv\Domain\Diagnostics\LogExporter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Diagnostics\LogFormatter::class,
            \FP\Resv\Domain\Diagnostics\LogFormatter::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Diagnostics\Service::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                $paymentsRepository = $container->get(\FP\Resv\Domain\Payments\Repository::class);
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $logExporter = $container->get(\FP\Resv\Domain\Diagnostics\LogExporter::class);
                $logFormatter = $container->get(\FP\Resv\Domain\Diagnostics\LogFormatter::class);
                
                return new \FP\Resv\Domain\Diagnostics\Service(
                    $db,
                    $paymentsRepository,
                    $reservationsRepository,
                    $logExporter,
                    $logFormatter
                );
            }
        );
        
        $container->alias('diagnostics.service', \FP\Resv\Domain\Diagnostics\Service::class);
    }
    
    /**
     * Register tracking service
     */
    private function registerTracking(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\Tracking\GA4::class,
            function (Container $container) {
                // Tracking GA4 requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Tracking\GA4($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\Ads::class,
            function (Container $container) {
                // Tracking Ads requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Tracking\Ads($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\Meta::class,
            function (Container $container) {
                // Tracking Meta requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Tracking\Meta($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\Clarity::class,
            function (Container $container) {
                // Tracking Clarity requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                return new \FP\Resv\Domain\Tracking\Clarity($options);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\UTMAttributionHandler::class,
            \FP\Resv\Domain\Tracking\UTMAttributionHandler::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\TrackingScriptGenerator::class,
            \FP\Resv\Domain\Tracking\TrackingScriptGenerator::class
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\ReservationEventBuilder::class,
            function (Container $container) {
                $ads = $container->get(\FP\Resv\Domain\Tracking\Ads::class);
                $meta = $container->get(\FP\Resv\Domain\Tracking\Meta::class);
                return new \FP\Resv\Domain\Tracking\ReservationEventBuilder($ads, $meta);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\ServerSideEventDispatcher::class,
            function (Container $container) {
                $ga4 = $container->get(\FP\Resv\Domain\Tracking\GA4::class);
                $meta = $container->get(\FP\Resv\Domain\Tracking\Meta::class);
                return new \FP\Resv\Domain\Tracking\ServerSideEventDispatcher($ga4, $meta);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\Tracking\Manager::class,
            function (Container $container) {
                // Tracking Manager requires FP\Resv\Domain\Settings\Options (final class)
                $options = new \FP\Resv\Domain\Settings\Options();
                $ga4 = $container->get(\FP\Resv\Domain\Tracking\GA4::class);
                $ads = $container->get(\FP\Resv\Domain\Tracking\Ads::class);
                $meta = $container->get(\FP\Resv\Domain\Tracking\Meta::class);
                $clarity = $container->get(\FP\Resv\Domain\Tracking\Clarity::class);
                $utmHandler = $container->get(\FP\Resv\Domain\Tracking\UTMAttributionHandler::class);
                $scriptGenerator = $container->get(\FP\Resv\Domain\Tracking\TrackingScriptGenerator::class);
                $eventBuilder = $container->get(\FP\Resv\Domain\Tracking\ReservationEventBuilder::class);
                $serverSideDispatcher = $container->get(\FP\Resv\Domain\Tracking\ServerSideEventDispatcher::class);
                
                return new \FP\Resv\Domain\Tracking\Manager(
                    $options,
                    $ga4,
                    $ads,
                    $meta,
                    $clarity,
                    $utmHandler,
                    $scriptGenerator,
                    $eventBuilder,
                    $serverSideDispatcher
                );
            }
        );
        
        $container->alias('tracking.manager', \FP\Resv\Domain\Tracking\Manager::class);
    }
    
    /**
     * Register QA seeder
     */
    private function registerQA(Container $container): void
    {
        $container->singleton(
            \FP\Resv\Domain\QA\Seeder::class,
            function (Container $container) {
                $reservationsRepository = $container->get(\FP\Resv\Domain\Reservations\Repository::class);
                $customersRepository = $container->get(\FP\Resv\Domain\Customers\Repository::class);
                $paymentsRepository = $container->get(\FP\Resv\Domain\Payments\Repository::class);
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                
                return new \FP\Resv\Domain\QA\Seeder(
                    $reservationsRepository,
                    $customersRepository,
                    $paymentsRepository,
                    $db
                );
            }
        );
        
        $container->alias('qa.seeder', \FP\Resv\Domain\QA\Seeder::class);
        
        // Register QA REST and CLI handlers
        $container->singleton(
            \FP\Resv\Domain\QA\REST::class,
            function (Container $container) {
                $seeder = $container->get(\FP\Resv\Domain\QA\Seeder::class);
                return new \FP\Resv\Domain\QA\REST($seeder);
            }
        );
        
        $container->singleton(
            \FP\Resv\Domain\QA\CLI::class,
            function (Container $container) {
                $seeder = $container->get(\FP\Resv\Domain\QA\Seeder::class);
                return new \FP\Resv\Domain\QA\CLI($seeder);
            }
        );
    }
}

