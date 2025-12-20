<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\Services;

use FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface;

/**
 * Availability Service Adapter
 * 
 * Adapter that bridges existing Availability class to new interface.
 * This allows gradual migration without breaking existing code.
 * 
 * NOTE: During migration, this adapter will delegate to the existing
 * Availability class. Once migration is complete, this can be replaced
 * with a pure domain implementation.
 *
 * @package FP\Resv\Infrastructure\Services
 */
final class AvailabilityServiceAdapter implements AvailabilityServiceInterface
{
    private ?\FP\Resv\Domain\Reservations\Availability $availability = null;
    
    /**
     * Constructor
     * 
     * The Availability instance will be lazy-loaded to avoid circular dependencies
     * during the migration period.
     */
    public function __construct()
    {
        // Lazy initialization to avoid dependency issues during migration
    }
    
    /**
     * Get Availability instance (lazy load)
     * 
     * @return \FP\Resv\Domain\Reservations\Availability
     */
    private function getAvailability(): \FP\Resv\Domain\Reservations\Availability
    {
        if ($this->availability === null) {
            // During migration, use existing instantiation
            // This will be refactored to use container dependencies
            // For now, we access it via the existing code path
            // The actual Availability instance should be injected via container
            // during full migration
            
            // Temporary: Access via existing code
            // This is a bridge solution during migration
            $container = \FP\Resv\Kernel\Bootstrap::container();
            
            // Get dependencies from container or create them
            // This is a temporary solution - will be refactored
            $this->availability = $this->createAvailabilityInstance($container);
        }
        
        return $this->availability;
    }
    
    /**
     * Create Availability instance using container dependencies
     * 
     * @param \FP\Resv\Kernel\Container $container
     * @return \FP\Resv\Domain\Reservations\Availability
     */
    private function createAvailabilityInstance(\FP\Resv\Kernel\Container $container): \FP\Resv\Domain\Reservations\Availability
    {
        // Use container to get Availability service (already registered in BusinessServiceProvider)
        if ($container->has(\FP\Resv\Domain\Reservations\Availability::class)) {
            return $container->get(\FP\Resv\Domain\Reservations\Availability::class);
        }
        
        // Fallback: if not registered, create manually (should not happen in normal flow)
        global $wpdb;
        
        $options = $container->get(\FP\Resv\Core\Services\OptionsInterface::class);
        $dataLoader = new \FP\Resv\Domain\Reservations\Availability\DataLoader($wpdb);
        $closureEvaluator = new \FP\Resv\Domain\Reservations\Availability\ClosureEvaluator();
        $tableSuggester = new \FP\Resv\Domain\Reservations\Availability\TableSuggester();
        $scheduleParser = new \FP\Resv\Domain\Reservations\Availability\ScheduleParser();
        $capacityResolver = new \FP\Resv\Domain\Reservations\Availability\CapacityResolver();
        $statusDeterminer = new \FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer();
        $payloadBuilder = new \FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder();
        $reservationFilter = new \FP\Resv\Domain\Reservations\Availability\ReservationFilter();
        
        return new \FP\Resv\Domain\Reservations\Availability(
            $options,
            $wpdb,
            $dataLoader,
            $closureEvaluator,
            $tableSuggester,
            $scheduleParser,
            $capacityResolver,
            $statusDeterminer,
            $payloadBuilder,
            $reservationFilter
        );
    }
    
    /**
     * Find available slots for given criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return array<string, mixed> Availability data with slots
     * @throws \InvalidArgumentException If criteria invalid
     */
    public function findSlots(array $criteria): array
    {
        // Delegate to existing Availability class
        return $this->getAvailability()->findSlots($criteria);
    }
    
    /**
     * Find available days for a date range
     * 
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @return array<string, mixed> Available days data
     */
    public function findAvailableDaysForAllMeals(string $from, string $to): array
    {
        // Delegate to existing Availability class
        return $this->getAvailability()->findAvailableDaysForAllMeals($from, $to);
    }
}

