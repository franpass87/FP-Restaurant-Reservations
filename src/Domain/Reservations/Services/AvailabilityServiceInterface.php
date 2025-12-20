<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Services;

/**
 * Availability Service Interface
 * 
 * Defines business logic operations for availability.
 * This interface is WordPress-agnostic.
 *
 * @package FP\Resv\Domain\Reservations\Services
 */
interface AvailabilityServiceInterface
{
    /**
     * Find available slots for given criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return array<string, mixed> Availability data with slots
     * @throws \InvalidArgumentException If criteria invalid
     */
    public function findSlots(array $criteria): array;
    
    /**
     * Find available days for a date range
     * 
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @return array<string, mixed> Available days data
     */
    public function findAvailableDaysForAllMeals(string $from, string $to): array;
}










