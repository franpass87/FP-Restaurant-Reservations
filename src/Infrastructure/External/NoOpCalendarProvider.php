<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\External;

use FP\Resv\Domain\Integrations\CalendarProviderInterface;
use DateTimeImmutable;

/**
 * No-Op Calendar Provider
 * 
 * Null implementation that does nothing.
 * Used when calendar provider is not configured.
 *
 * @package FP\Resv\Infrastructure\External
 */
final class NoOpCalendarProvider implements CalendarProviderInterface
{
    /**
     * Create a calendar event (no-op)
     * 
     * @param string $title Event title
     * @param string $description Event description
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @param array<string, mixed> $options Additional options
     * @return string|null Always returns null (no-op)
     */
    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $options = []
    ): ?string {
        // No-op: calendar provider not configured
        return null;
    }
    
    /**
     * Update a calendar event (no-op)
     * 
     * @param string $eventId Event ID
     * @param array<string, mixed> $data Update data
     * @return bool Always returns true (no-op)
     */
    public function updateEvent(string $eventId, array $data): bool
    {
        // No-op: calendar provider not configured
        return true;
    }
    
    /**
     * Delete a calendar event (no-op)
     * 
     * @param string $eventId Event ID
     * @return bool Always returns true (no-op)
     */
    public function deleteEvent(string $eventId): bool
    {
        // No-op: calendar provider not configured
        return true;
    }
    
    /**
     * Check if time slot is busy (no-op)
     * 
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @return bool Always returns false (not busy)
     */
    public function isBusy(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        // No-op: calendar provider not configured, assume not busy
        return false;
    }
}










