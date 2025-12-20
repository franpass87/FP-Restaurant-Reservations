<?php

declare(strict_types=1);

namespace FP\Resv\Application\Events;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Events\Repositories\EventRepositoryInterface;

/**
 * Delete Event Use Case
 * 
 * Orchestrates the deletion of a restaurant event.
 *
 * @package FP\Resv\Application\Events
 */
final class DeleteEventUseCase
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Event ID
     * @return bool Success status
     */
    public function execute(int $id): bool
    {
        // Check if event exists
        $event = $this->eventRepository->findById($id);
        
        if ($event === null) {
            $this->logger->warning('Attempted to delete non-existent event', ['id' => $id]);
            return false;
        }
        
        // Log the deletion attempt
        $this->logger->info('Deleting event', [
            'id' => $id,
            'title' => $event->getTitle(),
        ]);
        
        // Delete via repository
        $deleted = $this->eventRepository->delete($id);
        
        if ($deleted) {
            $this->logger->info('Event deleted', ['id' => $id]);
        } else {
            $this->logger->error('Failed to delete event', ['id' => $id]);
        }
        
        return $deleted;
    }
}

