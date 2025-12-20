<?php

declare(strict_types=1);

namespace FP\Resv\Application\Reservations;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;

/**
 * Create Reservation Use Case
 * 
 * Orchestrates the creation of a new reservation.
 * This is a thin orchestration layer that coordinates domain services.
 *
 * @package FP\Resv\Application\Reservations
 */
final class CreateReservationUseCase
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
     * @param array<string, mixed> $data Reservation data
     * @return Reservation Created reservation
     * @throws ValidationException If validation fails
     */
    public function execute(array $data): Reservation
    {
        // Validate input
        $this->validate($data);
        
        // Log the creation attempt
        $this->logger->info('Creating reservation', [
            'email' => $data['email'] ?? 'unknown',
            'date' => $data['date'] ?? 'unknown',
            'time' => $data['time'] ?? 'unknown',
        ]);
        
        // Delegate to domain service
        $reservation = $this->reservationService->create($data);
        
        // Log success
        $this->logger->info('Reservation created', [
            'id' => $reservation->getId(),
        ]);
        
        return $reservation;
    }
    
    /**
     * Validate reservation data
     * 
     * @param array<string, mixed> $data Reservation data
     * @return void
     * @throws ValidationException If validation fails
     */
    private function validate(array $data): void
    {
        $errors = [];
        
        // Required fields
        if (!$this->validator->isRequired($data['date'] ?? null)) {
            $errors['date'] = 'Date is required';
        } elseif (!$this->validator->isDate($data['date'])) {
            $errors['date'] = 'Invalid date format';
        }
        
        if (!$this->validator->isRequired($data['time'] ?? null)) {
            $errors['time'] = 'Time is required';
        } elseif (!$this->validator->isTime($data['time'])) {
            $errors['time'] = 'Invalid time format';
        }
        
        if (!$this->validator->isRequired($data['party'] ?? null)) {
            $errors['party'] = 'Party size is required';
        } elseif ((int) ($data['party'] ?? 0) <= 0) {
            $errors['party'] = 'Party size must be greater than 0';
        }
        
        // Check if partial contact is allowed (for admin/backend operations)
        $allowPartialContact = !empty($data['allow_partial_contact'] ?? false);
        
        if ($allowPartialContact) {
            // For admin/backend: at least one of first_name or last_name is required
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            
            if ($firstName === '' && $lastName === '') {
                $errors['first_name'] = 'At least first name or last name is required';
            }
            
            // Email is optional but if provided must be valid
            if (isset($data['email']) && !empty(trim((string) $data['email']))) {
                if (!$this->validator->isEmail($data['email'])) {
                    $errors['email'] = 'Invalid email format';
                }
            }
        } else {
            // For frontend: all customer fields are required
            if (!$this->validator->isRequired($data['email'] ?? null)) {
                $errors['email'] = 'Email is required';
            } elseif (!$this->validator->isEmail($data['email'])) {
                $errors['email'] = 'Invalid email format';
            }
            
            if (!$this->validator->isRequired($data['first_name'] ?? null)) {
                $errors['first_name'] = 'First name is required';
            }
            
            if (!$this->validator->isRequired($data['last_name'] ?? null)) {
                $errors['last_name'] = 'Last name is required';
            }
        }
        
        if ($errors !== []) {
            throw new ValidationException('Reservation validation failed', $errors);
        }
    }
}










