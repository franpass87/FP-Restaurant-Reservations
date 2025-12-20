<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Services;

use FP\Resv\Domain\Reservations\Models\Reservation;

/**
 * Reservation Service Interface
 * 
 * Defines business logic operations for reservations.
 * This interface is WordPress-agnostic.
 *
 * @package FP\Resv\Domain\Reservations\Services
 */
interface ReservationServiceInterface
{
    /**
     * Create a new reservation
     * 
     * @param array<string, mixed> $data Reservation data
     * @return Reservation Created reservation
     * @throws \FP\Resv\Core\Exceptions\ValidationException If validation fails
     */
    public function create(array $data): Reservation;
    
    /**
     * Update an existing reservation
     * 
     * @param int $id Reservation ID
     * @param array<string, mixed> $data Update data
     * @return Reservation Updated reservation
     * @throws \FP\Resv\Core\Exceptions\ValidationException If validation fails
     */
    public function update(int $id, array $data): Reservation;
    
    /**
     * Cancel a reservation
     * 
     * @param int $id Reservation ID
     * @return Reservation Cancelled reservation
     */
    public function cancel(int $id): Reservation;
    
    /**
     * Confirm a reservation
     * 
     * @param int $id Reservation ID
     * @return Reservation Confirmed reservation
     */
    public function confirm(int $id): Reservation;
}










