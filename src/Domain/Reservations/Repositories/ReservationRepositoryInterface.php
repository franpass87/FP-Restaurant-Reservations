<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Repositories;

use FP\Resv\Domain\Reservations\Models\Reservation;

/**
 * Reservation Repository Interface
 * 
 * Defines the contract for reservation data access.
 * This interface is WordPress-agnostic and can be implemented
 * by any persistence layer (WordPress, external API, etc.).
 *
 * @package FP\Resv\Domain\Reservations\Repositories
 */
interface ReservationRepositoryInterface
{
    /**
     * Find a reservation by ID
     * 
     * @param int $id Reservation ID
     * @return Reservation|null
     */
    public function findById(int $id): ?Reservation;
    
    /**
     * Find reservations by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Reservation>
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array;
    
    /**
     * Save a reservation (create or update)
     * 
     * @param Reservation $reservation Reservation to save
     * @return Reservation Saved reservation with ID
     */
    public function save(Reservation $reservation): Reservation;
    
    /**
     * Delete a reservation
     * 
     * @param int $id Reservation ID
     * @return bool Success status
     */
    public function delete(int $id): bool;
    
    /**
     * Count reservations matching criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return int Count
     */
    public function count(array $criteria): int;
}










