<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events\Repositories;

use FP\Resv\Domain\Events\Models\Event;

/**
 * Event Repository Interface
 * 
 * Defines the contract for event data access.
 *
 * @package FP\Resv\Domain\Events\Repositories
 */
interface EventRepositoryInterface
{
    /**
     * Find an event by ID
     * 
     * @param int $id Event ID
     * @return Event|null
     */
    public function findById(int $id): ?Event;
    
    /**
     * Find events by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Event>
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array;
    
    /**
     * Save an event (create or update)
     * 
     * @param Event $event Event to save
     * @return Event Saved event with ID
     */
    public function save(Event $event): Event;
    
    /**
     * Delete an event
     * 
     * @param int $id Event ID
     * @return bool Success status
     */
    public function delete(int $id): bool;
}










