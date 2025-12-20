<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * List Reservations Use Case
 * 
 * Retrieves a list of reservations with optional filtering.
 *
 * @package FP\Resv\Application\Reservations
 */
final class ListReservationsUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param array<string, mixed> $criteria Filter criteria (date, status, etc.)
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Reservation> List of reservations
     */
    public function execute(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug('Listing reservations', [
            'criteria' => $criteria,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        
        // Delegate to repository for actual querying
        $reservations = $this->repository->findBy($criteria, $limit, $offset);
        
        $this->logger->debug('Reservations listed', [
            'count' => count($reservations),
        ]);
        
        return $reservations;
    }
}

