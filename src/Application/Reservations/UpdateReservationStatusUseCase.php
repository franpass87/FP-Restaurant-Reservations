<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;

/**
 * Update Reservation Status Use Case
 * 
 * Updates the status of an existing reservation.
 *
 * @package FP\Resv\Application\Reservations
 */
final class UpdateReservationStatusUseCase
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'cancelled', 'visited', 'no-show'];

    public function __construct(
        private readonly ReservationServiceInterface $reservationService,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Reservation ID
     * @param string $status New status
     * @return Reservation Updated reservation
     * @throws ValidationException If reservation not found, ID invalid, or status invalid
     */
    public function execute(int $id, string $status): Reservation
    {
        if ($id <= 0) {
            throw new ValidationException('Invalid reservation ID');
        }
        
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new ValidationException('Invalid status: ' . $status);
        }
        
        $this->logger->info('Updating reservation status', [
            'id' => $id,
            'status' => $status,
        ]);
        
        // Use the service's update method
        $reservation = $this->reservationService->update($id, ['status' => $status]);
        
        $this->logger->info('Reservation status updated', [
            'id' => $reservation->getId(),
            'status' => $reservation->getStatus(),
        ]);
        
        return $reservation;
    }
}




