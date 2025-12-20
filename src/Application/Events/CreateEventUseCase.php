<?php

declare(strict_types=1);

namespace FP\Resv\Application\Events;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Events\Models\Event;
use FP\Resv\Domain\Events\Repositories\EventRepositoryInterface;
use DateTimeImmutable;

/**
 * Create Event Use Case
 * 
 * Orchestrates the creation of a new restaurant event.
 *
 * @package FP\Resv\Application\Events
 */
final class CreateEventUseCase
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param array<string, mixed> $data Event data
     * @return Event Created event
     * @throws ValidationException If validation fails
     */
    public function execute(array $data): Event
    {
        // Validate input
        $this->validate($data);
        
        // Log the creation attempt
        $this->logger->info('Creating event', [
            'title' => $data['title'] ?? 'unknown',
            'start_date' => $data['start_date'] ?? 'unknown',
        ]);
        
        // Parse dates
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['start_date']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['end_date']);
        
        if (!$startDate || !$endDate) {
            throw new ValidationException('Invalid date format', ['date' => 'Dates must be in Y-m-d H:i:s format']);
        }
        
        // Create domain model
        $event = new Event(
            $data['title'],
            $data['description'] ?? '',
            $startDate,
            $endDate,
            (int) ($data['max_capacity'] ?? 0)
        );
        
        // Save via repository
        $savedEvent = $this->eventRepository->save($event);
        
        // Log success
        $this->logger->info('Event created', [
            'id' => $savedEvent->getId(),
            'title' => $savedEvent->getTitle(),
        ]);
        
        return $savedEvent;
    }
    
    /**
     * Validate event data
     * 
     * @param array<string, mixed> $data Event data
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
        
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['start_date']);
            $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['end_date']);
            
            if ($start && $end && $end <= $start) {
                $errors['end_date'] = 'End date must be after start date';
            }
        }
        
        if (!isset($data['max_capacity']) || (int) ($data['max_capacity'] ?? 0) <= 0) {
            $errors['max_capacity'] = 'Max capacity must be greater than 0';
        }
        
        if ($errors !== []) {
            throw new ValidationException('Event validation failed', $errors);
        }
    }
}










