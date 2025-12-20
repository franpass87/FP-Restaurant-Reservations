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
 * Update Event Use Case
 * 
 * Orchestrates the update of an existing restaurant event.
 *
 * @package FP\Resv\Application\Events
 */
final class UpdateEventUseCase
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
     * @param int $id Event ID
     * @param array<string, mixed> $data Update data
     * @return Event Updated event
     * @throws ValidationException If validation fails
     */
    public function execute(int $id, array $data): Event
    {
        // Find existing event
        $event = $this->eventRepository->findById($id);
        
        if ($event === null) {
            throw new \RuntimeException("Event with ID {$id} not found");
        }
        
        // Validate update data
        $this->validate($data, $event);
        
        // Log the update attempt
        $this->logger->info('Updating event', [
            'id' => $id,
            'changes' => array_keys($data),
        ]);
        
        // Update fields
        if (isset($data['title'])) {
            // Note: Event model doesn't have a setTitle method in current implementation
            // This would need to be added or we use reflection/fromArray
        }
        
        if (isset($data['start_date'])) {
            $startDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['start_date']);
            if ($startDate) {
                // Update would require a setter method
            }
        }
        
        if (isset($data['end_date'])) {
            $endDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['end_date']);
            if ($endDate) {
                // Update would require a setter method
            }
        }
        
        if (isset($data['max_capacity'])) {
            // Update would require a setter method
        }
        
        if (isset($data['is_active'])) {
            $event->setActive((bool) $data['is_active']);
        }
        
        // For now, we'll recreate from array with merged data
        $currentData = $event->toArray();
        $mergedData = array_merge($currentData, $data);
        $updatedEvent = Event::fromArray($mergedData);
        $updatedEvent->setId($id);
        
        // Save via repository
        $savedEvent = $this->eventRepository->save($updatedEvent);
        
        // Log success
        $this->logger->info('Event updated', [
            'id' => $savedEvent->getId(),
        ]);
        
        return $savedEvent;
    }
    
    /**
     * Validate update data
     * 
     * @param array<string, mixed> $data Update data
     * @param Event $event Existing event
     * @return void
     * @throws ValidationException If validation fails
     */
    private function validate(array $data, Event $event): void
    {
        $errors = [];
        
        // Validate dates if provided
        if (isset($data['start_date']) && !$this->validator->isDate($data['start_date'])) {
            $errors['start_date'] = 'Invalid start date format';
        }
        
        if (isset($data['end_date']) && !$this->validator->isDate($data['end_date'])) {
            $errors['end_date'] = 'Invalid end date format';
        }
        
        // Validate date range
        $startDate = isset($data['start_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['start_date'])
            : $event->getStartDate();
        $endDate = isset($data['end_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['end_date'])
            : $event->getEndDate();
        
        if ($startDate && $endDate && $endDate <= $startDate) {
            $errors['end_date'] = 'End date must be after start date';
        }
        
        if (isset($data['max_capacity']) && (int) $data['max_capacity'] <= 0) {
            $errors['max_capacity'] = 'Max capacity must be greater than 0';
        }
        
        if ($errors !== []) {
            throw new ValidationException('Event update validation failed', $errors);
        }
    }
}










