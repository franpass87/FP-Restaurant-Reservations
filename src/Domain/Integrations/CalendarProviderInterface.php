<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Integrations;

use DateTimeImmutable;

/**
 * Calendar Provider Interface
 * 
 * Defines the contract for calendar integration services.
 * This interface is WordPress-agnostic and can be implemented
 * by any calendar service (Google Calendar, Outlook, etc.).
 *
 * @package FP\Resv\Domain\Integrations
 */
interface CalendarProviderInterface
{
    /**
     * Create a calendar event
     * 
     * @param string $title Event title
     * @param string $description Event description
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @param array<string, mixed> $options Additional options (location, attendees, etc.)
     * @return string|null Event ID if created, null on failure
     */
    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $options = []
    ): ?string;
    
    /**
     * Update a calendar event
     * 
     * @param string $eventId Event ID
     * @param array<string, mixed> $data Update data
     * @return bool Success status
     */
    public function updateEvent(string $eventId, array $data): bool;
    
    /**
     * Delete a calendar event
     * 
     * @param string $eventId Event ID
     * @return bool Success status
     */
    public function deleteEvent(string $eventId): bool;
    
    /**
     * Check if time slot is busy
     * 
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @return bool True if busy
     */
    public function isBusy(DateTimeImmutable $start, DateTimeImmutable $end): bool;
}










