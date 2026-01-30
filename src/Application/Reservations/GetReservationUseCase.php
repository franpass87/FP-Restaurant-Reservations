<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * Get Reservation Use Case
 * 
 * Retrieves a single reservation by ID.
 *
 * @package FP\Resv\Application\Reservations
 */
final class GetReservationUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Reservation ID
     * @return Reservation Reservation model
     * @throws ValidationException If reservation not found or ID invalid
     */
    public function execute(int $id): Reservation
    {
        if ($id <= 0) {
            throw new ValidationException('Invalid reservation ID');
        }
        
        $this->logger->debug('Getting reservation', [
            'id' => $id,
        ]);
        
        $reservation = $this->repository->findById($id);
        
        if ($reservation === null) {
            $this->logger->warning('Reservation not found', [
                'id' => $id,
            ]);
            throw new ValidationException('Reservation not found');
        }
        
        return $reservation;
    }
}








