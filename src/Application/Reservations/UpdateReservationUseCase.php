<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;

/**
 * Update Reservation Use Case
 * 
 * Orchestrates the update of an existing reservation.
 *
 * @package FP\Resv\Application\Reservations
 */
final class UpdateReservationUseCase
{
    public function __construct(
        private readonly ReservationServiceInterface $reservationService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Reservation ID
     * @param array<string, mixed> $data Update data
     * @return Reservation Updated reservation
     * @throws ValidationException If validation fails
     */
    public function execute(int $id, array $data): Reservation
    {
        // Validate input (only validate provided fields)
        $this->validate($data);
        
        // Log the update attempt
        $this->logger->info('Updating reservation', [
            'id' => $id,
            'fields' => array_keys($data),
        ]);
        
        // Delegate to domain service
        $reservation = $this->reservationService->update($id, $data);
        
        // Log success
        $this->logger->info('Reservation updated', [
            'id' => $reservation->getId(),
        ]);
        
        return $reservation;
    }
    
    /**
     * Validate update data
     * 
     * @param array<string, mixed> $data Update data
     * @return void
     * @throws ValidationException If validation fails
     */
    private function validate(array $data): void
    {
        $errors = [];
        
        // Only validate fields that are being updated
        if (isset($data['date']) && !$this->validator->isDate($data['date'])) {
            $errors['date'] = 'Invalid date format';
        }
        
        if (isset($data['time']) && !$this->validator->isTime($data['time'])) {
            $errors['time'] = 'Invalid time format';
        }
        
        if (isset($data['party']) && (int) $data['party'] <= 0) {
            $errors['party'] = 'Party size must be greater than 0';
        }
        
        if (isset($data['email']) && !$this->validator->isEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if ($errors !== []) {
            throw new ValidationException('Reservation update validation failed', $errors);
        }
    }
}










