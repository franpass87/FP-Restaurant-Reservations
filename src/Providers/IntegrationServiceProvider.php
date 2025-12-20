<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Integration Service Provider
 * 
 * Registers external API integrations and related services.
 *
 * @package FP\Resv\Providers
 */
final class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register integration services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        // Register email provider (Brevo)
        $container->singleton(
            \FP\Resv\Domain\Integrations\EmailProviderInterface::class,
            function (Container $container) {
                $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
                $apiKey = $options->get('brevo_api_key', '');
                
                if (empty($apiKey)) {
                    // Return a no-op implementation if API key not configured
                    return new \FP\Resv\Infrastructure\External\NoOpEmailProvider();
                }
                
                return new \FP\Resv\Infrastructure\External\Brevo\BrevoEmailProvider(
                    $container->get(\FP\Resv\Core\Services\HttpClientInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class),
                    $apiKey
                );
            }
        );
        
        // Register calendar provider (Google Calendar)
        $container->singleton(
            \FP\Resv\Domain\Integrations\CalendarProviderInterface::class,
            function (Container $container) {
                $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
                $accessToken = $options->get('google_calendar_access_token', '');
                $calendarId = $options->get('google_calendar_id', 'primary');
                
                if (empty($accessToken)) {
                    // Return a no-op implementation if not configured
                    return new \FP\Resv\Infrastructure\External\NoOpCalendarProvider();
                }
                
                return new \FP\Resv\Infrastructure\External\GoogleCalendar\GoogleCalendarProvider(
                    $container->get(\FP\Resv\Core\Services\HttpClientInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class),
                    $accessToken,
                    $calendarId
                );
            }
        );
    }
    
    /**
     * Boot integration services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Integration hooks will be registered here if needed
        // Brevo and Google Calendar are booted by BusinessServiceProvider
    }
}










