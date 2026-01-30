<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Services;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * Reservation Service
 * 
 * Implements business logic for reservations.
 * This is a pure domain service with no WordPress dependencies.
 *
 * @package FP\Resv\Domain\Reservations\Services
 */
final class ReservationService implements ReservationServiceInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $repository
    ) {
    }
    
    /**
     * Create a new reservation
     * 
     * @param array<string, mixed> $data Reservation data
     * @return Reservation Created reservation
     * @throws ValidationException If validation fails
     */
    public function create(array $data): Reservation
    {
        // Create domain model - allow empty strings for customer data when allow_partial_contact is true
        $reservation = new Reservation(
            $data['date'],
            $data['time'],
            (int) $data['party'],
            $data['meal'] ?? 'dinner',
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['status'] ?? 'pending'
        );
        
        // Set optional fields
        if (isset($data['notes'])) {
            $reservation->setNotes($data['notes']);
        }
        
        if (isset($data['allergies'])) {
            $reservation->setAllergies($data['allergies']);
        }
        
        // Prepare additional customer data (marketing_consent, profiling_consent, lang)
        // These fields are not part of the Reservation model but need to be saved to the customers table
        $additionalCustomerData = [];
        if (isset($data['marketing_consent'])) {
            $additionalCustomerData['marketing_consent'] = $data['marketing_consent'];
        }
        if (isset($data['profiling_consent'])) {
            $additionalCustomerData['profiling_consent'] = $data['profiling_consent'];
        }
        if (isset($data['customer_lang']) || isset($data['lang'])) {
            $additionalCustomerData['customer_lang'] = $data['customer_lang'] ?? $data['lang'] ?? null;
        }
        
        // Save to repository with additional customer data
        return $this->repository->save($reservation, $additionalCustomerData);
    }
    
    /**
     * Update an existing reservation
     * 
     * @param int $id Reservation ID
     * @param array<string, mixed> $data Update data
     * @return Reservation Updated reservation
     * @throws ValidationException If validation fails
     */
    public function update(int $id, array $data): Reservation
    {
        // Find existing reservation
        $reservation = $this->repository->findById($id);
        
        if ($reservation === null) {
            throw new ValidationException("Reservation with ID {$id} not found");
        }
        
        // Update fields
        if (isset($data['date'])) {
            // Note: In a real implementation, we'd need setters for all fields
            // For now, we'll recreate the reservation with updated data
            $currentData = $reservation->toArray();
            $updatedData = array_merge($currentData, $data);
            $reservation = Reservation::fromArray($updatedData);
        } else {
            // Update individual fields
            if (isset($data['status'])) {
                $reservation->setStatus($data['status']);
            }
            
            if (isset($data['notes'])) {
                $reservation->setNotes($data['notes']);
            }
            
            if (isset($data['allergies'])) {
                $reservation->setAllergies($data['allergies']);
            }
            
            // Update customer data
            if (isset($data['first_name'])) {
                $reservation->setFirstName($data['first_name']);
            }
            
            if (isset($data['last_name'])) {
                $reservation->setLastName($data['last_name']);
            }
            
            if (isset($data['email'])) {
                $reservation->setEmail($data['email']);
            }
            
            if (isset($data['phone'])) {
                $reservation->setPhone($data['phone']);
            }
            
            if (isset($data['party'])) {
                $newParty = (int) $data['party'];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[FP ReservationService] Setting party from ' . $reservation->getParty() . ' to ' . $newParty);
                }
                $reservation->setParty($newParty);
            }
        }
        
        // Log before save
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP ReservationService] Saving reservation with party=' . $reservation->getParty());
        }
        
        // Save to repository
        return $this->repository->save($reservation);
    }
    
    /**
     * Cancel a reservation
     * 
     * @param int $id Reservation ID
     * @return Reservation Cancelled reservation
     */
    public function cancel(int $id): Reservation
    {
        return $this->update($id, ['status' => 'cancelled']);
    }
    
    /**
     * Confirm a reservation
     * 
     * @param int $id Reservation ID
     * @return Reservation Confirmed reservation
     */
    public function confirm(int $id): Reservation
    {
        return $this->update($id, ['status' => 'confirmed']);
    }
}










