<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;

/**
 * Cancel Reservation Use Case
 * 
 * Cancels an existing reservation by setting its status to 'cancelled'.
 *
 * @package FP\Resv\Application\Reservations
 */
final class CancelReservationUseCase
{
    public function __construct(
        private readonly ReservationServiceInterface $reservationService,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Reservation ID
     * @return Reservation Cancelled reservation
     * @throws ValidationException If reservation not found or ID invalid
     */
    public function execute(int $id): Reservation
    {
        if ($id <= 0) {
            throw new ValidationException('Invalid reservation ID');
        }
        
        $this->logger->info('Cancelling reservation', [
            'id' => $id,
        ]);
        
        // Use the service's cancel method
        $reservation = $this->reservationService->cancel($id);
        
        $this->logger->info('Reservation cancelled', [
            'id' => $reservation->getId(),
        ]);
        
        return $reservation;
    }
}








