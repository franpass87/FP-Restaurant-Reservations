<?php

declare(strict_types=1);

namespace FP\Resv\Providers;

use FP\Resv\Kernel\Container;

/**
 * Data Service Provider
 * 
 * Registers repositories, database migrations, and data models.
 * Always loads regardless of context.
 *
 * @package FP\Resv\Providers
 */
final class DataServiceProvider extends ServiceProvider
{
    /**
     * Register data services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function register(Container $container): void
    {
        $this->registerRepositories($container);
        $this->registerUseCases($container);
    }
    
    /**
     * Register all repositories
     */
    private function registerRepositories(Container $container): void
    {
        // Reservations repository (new interface-based)
        $container->singleton(
            \FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface::class,
            \FP\Resv\Infrastructure\Persistence\ReservationRepository::class
        );
        
        // Legacy Reservations repository (for backward compatibility)
        $container->singleton(
            \FP\Resv\Domain\Reservations\Repository::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Reservations\Repository($db);
            }
        );
        
        $container->alias('reservations.repository', \FP\Resv\Domain\Reservations\Repository::class);
        
        // Payments repository
        $container->singleton(
            \FP\Resv\Domain\Payments\Repository::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Payments\Repository($db);
            }
        );
        
        $container->alias('payments.repository', \FP\Resv\Domain\Payments\Repository::class);
        
        // Customers repository
        $container->singleton(
            \FP\Resv\Domain\Customers\Repository::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Customers\Repository($db);
            }
        );
        
        $container->alias('customers.repository', \FP\Resv\Domain\Customers\Repository::class);
        
        // Tables repository
        $container->singleton(
            \FP\Resv\Domain\Tables\Repository::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Tables\Repository($db);
            }
        );
        
        $container->alias('tables.repository', \FP\Resv\Domain\Tables\Repository::class);
        
        // Brevo repository
        $container->singleton(
            \FP\Resv\Domain\Brevo\Repository::class,
            function (Container $container) {
                $db = $container->get(\FP\Resv\Core\Adapters\DatabaseAdapterInterface::class)->getDb();
                return new \FP\Resv\Domain\Brevo\Repository($db);
            }
        );
        
        $container->alias('brevo.repository', \FP\Resv\Domain\Brevo\Repository::class);
        
        // Event repository (interface-based)
        $container->singleton(
            \FP\Resv\Domain\Events\Repositories\EventRepositoryInterface::class,
            \FP\Resv\Infrastructure\Persistence\EventRepository::class
        );
        
        // Closure repository (interface-based)
        $container->singleton(
            \FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface::class,
            \FP\Resv\Infrastructure\Persistence\ClosureRepository::class
        );
    }
    
    /**
     * Register use cases
     */
    private function registerUseCases(Container $container): void
    {
        // Reservations use cases
        $container->singleton(
            \FP\Resv\Application\Reservations\CreateReservationUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\CreateReservationUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Services\ReservationServiceInterface::class),
                    $container->get(\FP\Resv\Core\Services\ValidatorInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\UpdateReservationUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\UpdateReservationUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Services\ReservationServiceInterface::class),
                    $container->get(\FP\Resv\Core\Services\ValidatorInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\DeleteReservationUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\DeleteReservationUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\GetReservationUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\GetReservationUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\ListReservationsUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\ListReservationsUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\CancelReservationUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\CancelReservationUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Services\ReservationServiceInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\UpdateReservationStatusUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Reservations\UpdateReservationStatusUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Services\ReservationServiceInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        $container->singleton(
            \FP\Resv\Application\Reservations\NotifyReservationUseCase::class,
            \FP\Resv\Application\Reservations\NotifyReservationUseCase::class
        );
        
        // Availability use case
        $container->singleton(
            \FP\Resv\Application\Availability\GetAvailabilityUseCase::class,
            function (Container $container) {
                return new \FP\Resv\Application\Availability\GetAvailabilityUseCase(
                    $container->get(\FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface::class),
                    $container->get(\FP\Resv\Core\Services\LoggerInterface::class)
                );
            }
        );
        
        // Event use cases
        $container->singleton(
            \FP\Resv\Application\Events\CreateEventUseCase::class,
            \FP\Resv\Application\Events\CreateEventUseCase::class
        );
        
        $container->singleton(
            \FP\Resv\Application\Events\UpdateEventUseCase::class,
            \FP\Resv\Application\Events\UpdateEventUseCase::class
        );
        
        $container->singleton(
            \FP\Resv\Application\Events\DeleteEventUseCase::class,
            \FP\Resv\Application\Events\DeleteEventUseCase::class
        );
        
        // Closure use cases
        $container->singleton(
            \FP\Resv\Application\Closures\CreateClosureUseCase::class,
            \FP\Resv\Application\Closures\CreateClosureUseCase::class
        );
        
        $container->singleton(
            \FP\Resv\Application\Closures\UpdateClosureUseCase::class,
            \FP\Resv\Application\Closures\UpdateClosureUseCase::class
        );
        
        $container->singleton(
            \FP\Resv\Application\Closures\DeleteClosureUseCase::class,
            \FP\Resv\Application\Closures\DeleteClosureUseCase::class
        );
        
        // Register availability service adapter (bridge to existing Availability class)
        $container->singleton(
            \FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface::class,
            \FP\Resv\Infrastructure\Services\AvailabilityServiceAdapter::class
        );
        
        // Register domain service interface
        $container->singleton(
            \FP\Resv\Domain\Reservations\Services\ReservationServiceInterface::class,
            \FP\Resv\Domain\Reservations\Services\ReservationService::class
        );
    }
    
    /**
     * Boot data services
     * 
     * @param Container $container Service container
     * @return void
     */
    public function boot(Container $container): void
    {
        // Database migrations will be run here in Phase 3
        // For now, we rely on existing migration system
    }
}





