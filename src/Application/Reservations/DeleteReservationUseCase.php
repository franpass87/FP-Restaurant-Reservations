<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * Delete Reservation Use Case
 * 
 * Orchestrates the deletion of a reservation.
 *
 * @package FP\Resv\Application\Reservations
 */
final class DeleteReservationUseCase
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
     * @return bool Success status
     */
    public function execute(int $id): bool
    {
        // Log the deletion attempt
        $this->logger->info('Deleting reservation', [
            'id' => $id,
        ]);
        
        // Check if reservation exists
        $reservation = $this->repository->findById($id);
        if ($reservation === null) {
            $this->logger->warning('Reservation not found for deletion', [
                'id' => $id,
            ]);
            return false;
        }
        
        // Delete from repository
        $success = $this->repository->delete($id);
        
        if ($success) {
            $this->logger->info('Reservation deleted', [
                'id' => $id,
            ]);
        } else {
            $this->logger->error('Failed to delete reservation', [
                'id' => $id,
            ]);
        }
        
        return $success;
    }
}










