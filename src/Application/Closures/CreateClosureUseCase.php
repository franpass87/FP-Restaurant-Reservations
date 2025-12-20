<?php

declare(strict_types=1);

namespace FP\Resv\Application\Closures;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Closures\Models\Closure;
use FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface;
use DateTimeImmutable;

/**
 * Create Closure Use Case
 * 
 * Orchestrates the creation of a new restaurant closure.
 *
 * @package FP\Resv\Application\Closures
 */
final class CreateClosureUseCase
{
    public function __construct(
        private readonly ClosureRepositoryInterface $closureRepository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param array<string, mixed> $data Closure data
     * @return Closure Created closure
     * @throws ValidationException If validation fails
     */
    public function execute(array $data): Closure
    {
        // Validate input
        $this->validate($data);
        
        // Log the creation attempt
        $this->logger->info('Creating closure', [
            'title' => $data['title'] ?? 'unknown',
            'start_date' => $data['start_date'] ?? 'unknown',
        ]);
        
        // Parse dates
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['start_date']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['end_date']);
        
        if (!$startDate || !$endDate) {
            throw new ValidationException('Invalid date format', ['date' => 'Dates must be in Y-m-d format']);
        }
        
        // Create domain model
        $closure = new Closure(
            $data['title'],
            $startDate,
            $endDate,
            $data['scope'] ?? 'all'
        );
        
        // Set optional fields
        if (isset($data['room_id'])) {
            $closure->setRoomId((int) $data['room_id']);
        }
        
        if (isset($data['table_id'])) {
            $closure->setTableId((int) $data['table_id']);
        }
        
        if (isset($data['is_recurring']) && $data['is_recurring']) {
            $closure->setRecurring(true, $data['recurrence_rule'] ?? null);
        }
        
        if (isset($data['is_active'])) {
            $closure->setActive((bool) $data['is_active']);
        }
        
        // Save via repository
        $savedClosure = $this->closureRepository->save($closure);
        
        // Log success
        $this->logger->info('Closure created', [
            'id' => $savedClosure->getId(),
            'title' => $savedClosure->getTitle(),
        ]);
        
        return $savedClosure;
    }
    
    /**
     * Validate closure data
     * 
     * @param array<string, mixed> $data Closure data
     * @return void
     * @throws ValidationException If validation fails
     */
    private function validate(array $data): void
    {
        $errors = [];
        
        // Required fields
        if (!$this->validator->isRequired($data['title'] ?? null)) {
            $errors['title'] = 'Title is required';
        }
        
        if (!$this->validator->isRequired($data['start_date'] ?? null)) {
            $errors['start_date'] = 'Start date is required';
        } elseif (!$this->validator->isDate($data['start_date'])) {
            $errors['start_date'] = 'Invalid start date format';
        }
        
        if (!$this->validator->isRequired($data['end_date'] ?? null)) {
            $errors['end_date'] = 'End date is required';
        } elseif (!$this->validator->isDate($data['end_date'])) {
            $errors['end_date'] = 'Invalid end date format';
        }
        
        // Validate date range
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $data['start_date']);
            $end = DateTimeImmutable::createFromFormat('Y-m-d', $data['end_date']);
            
            if ($start && $end && $end < $start) {
                $errors['end_date'] = 'End date must be on or after start date';
            }
        }
        
        // Validate scope
        $validScopes = ['all', 'room', 'table'];
        if (isset($data['scope']) && !in_array($data['scope'], $validScopes, true)) {
            $errors['scope'] = 'Scope must be one of: ' . implode(', ', $validScopes);
        }
        
        // Validate room_id if scope is 'room'
        if (($data['scope'] ?? '') === 'room' && !isset($data['room_id'])) {
            $errors['room_id'] = 'Room ID is required when scope is "room"';
        }
        
        // Validate table_id if scope is 'table'
        if (($data['scope'] ?? '') === 'table' && !isset($data['table_id'])) {
            $errors['table_id'] = 'Table ID is required when scope is "table"';
        }
        
        if ($errors !== []) {
            throw new ValidationException('Closure validation failed', $errors);
        }
    }
}
