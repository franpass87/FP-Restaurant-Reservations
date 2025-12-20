<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\Admin\Controllers;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Application\Reservations\DeleteReservationUseCase;
use FP\Resv\Application\Reservations\UpdateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;

/**
 * Admin Reservations Controller
 * 
 * Thin controller for admin reservation operations.
 * Delegates to Use Cases for business logic.
 *
 * @package FP\Resv\Presentation\Admin\Controllers
 */
final class ReservationsController
{
    public function __construct(
        private readonly CreateReservationUseCase $createUseCase,
        private readonly UpdateReservationUseCase $updateUseCase,
        private readonly DeleteReservationUseCase $deleteUseCase,
        private readonly ReservationRepositoryInterface $repository,
        private readonly SanitizerInterface $sanitizer,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Create reservation (for admin)
     * 
     * @param array<string, mixed> $data Reservation data
     * @return array<string, mixed> Result with reservation or error
     */
    public function create(array $data): array
    {
        try {
            // Sanitize input
            $sanitized = $this->sanitizeData($data);
            
            // Execute use case
            $reservation = $this->createUseCase->execute($sanitized);
            
            return [
                'success' => true,
                'reservation' => $reservation->toArray(),
            ];
        } catch (ValidationException $e) {
            $this->logger->warning('Admin reservation creation failed', [
                'errors' => $e->getErrors(),
            ]);
            
            return [
                'success' => false,
                'errors' => $e->getErrors(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Admin reservation creation error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => 'An error occurred creating the reservation.',
            ];
        }
    }
    
    /**
     * Update reservation (for admin)
     * 
     * @param int $id Reservation ID
     * @param array<string, mixed> $data Update data
     * @return array<string, mixed> Result with reservation or error
     */
    public function update(int $id, array $data): array
    {
        try {
            // Sanitize input
            $sanitized = $this->sanitizeData($data);
            
            // Execute use case
            $reservation = $this->updateUseCase->execute($id, $sanitized);
            
            return [
                'success' => true,
                'reservation' => $reservation->toArray(),
            ];
        } catch (ValidationException $e) {
            $this->logger->warning('Admin reservation update failed', [
                'id' => $id,
                'errors' => $e->getErrors(),
            ]);
            
            return [
                'success' => false,
                'errors' => $e->getErrors(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Admin reservation update error', [
                'id' => $id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => 'An error occurred updating the reservation.',
            ];
        }
    }
    
    /**
     * Delete reservation (for admin)
     * 
     * @param int $id Reservation ID
     * @return array<string, mixed> Result
     */
    public function delete(int $id): array
    {
        try {
            $success = $this->deleteUseCase->execute($id);
            
            return [
                'success' => $success,
                'message' => $success ? 'Reservation deleted successfully' : 'Reservation not found',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Admin reservation deletion error', [
                'id' => $id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => 'An error occurred deleting the reservation.',
            ];
        }
    }
    
    /**
     * Get reservation (for admin)
     * 
     * @param int $id Reservation ID
     * @return array<string, mixed> Reservation data or null
     */
    public function get(int $id): ?array
    {
        $reservation = $this->repository->findById($id);
        
        return $reservation?->toArray();
    }
    
    /**
     * List reservations (for admin)
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array<string, mixed> List of reservations
     */
    public function list(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        $reservations = $this->repository->findBy($criteria, $limit, $offset);
        
        return array_map(
            fn($reservation) => $reservation->toArray(),
            $reservations
        );
    }
    
    /**
     * Sanitize data
     * 
     * @param array<string, mixed> $data Raw data
     * @return array<string, mixed> Sanitized data
     */
    private function sanitizeData(array $data): array
    {
        return $this->sanitizer->array($data);
    }
}










