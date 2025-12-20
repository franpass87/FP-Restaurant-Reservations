<?php

declare(strict_types=1);

namespace FP\Resv\Infrastructure\External\GoogleCalendar;

use FP\Resv\Core\Services\HttpClientInterface;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Integrations\CalendarProviderInterface;
use DateTimeImmutable;
use function json_decode;
use function urlencode;
use function http_build_query;

/**
 * Google Calendar Provider
 * 
 * Implements CalendarProviderInterface using Google Calendar API.
 * This is the Infrastructure layer implementation.
 *
 * @package FP\Resv\Infrastructure\External\GoogleCalendar
 */
final class GoogleCalendarProvider implements CalendarProviderInterface
{
    private const API_BASE_URL = 'https://www.googleapis.com/calendar/v3';
    
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $accessToken,
        private readonly string $calendarId
    ) {
    }
    
    /**
     * Create a calendar event
     * 
     * @param string $title Event title
     * @param string $description Event description
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @param array<string, mixed> $options Additional options
     * @return string|null Event ID if created, null on failure
     */
    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $options = []
    ): ?string {
        $payload = [
            'summary' => $title,
            'description' => $description,
            'start' => [
                'dateTime' => $start->format(\DateTimeInterface::RFC3339),
                'timeZone' => $options['timezone'] ?? 'Europe/Rome',
            ],
            'end' => [
                'dateTime' => $end->format(\DateTimeInterface::RFC3339),
                'timeZone' => $options['timezone'] ?? 'Europe/Rome',
            ],
        ];
        
        if (isset($options['location'])) {
            $payload['location'] = $options['location'];
        }
        
        $response = $this->httpClient->post(
            self::API_BASE_URL . '/calendars/' . urlencode($this->calendarId) . '/events',
            $payload,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        
        if (isset($response['error']) || ($response['response']['code'] ?? 0) !== 200) {
            $this->logger->error('Google Calendar event creation failed', [
                'title' => $title,
                'response' => $response,
            ]);
            return null;
        }
        
        $eventData = json_decode($response['body'], true);
        $eventId = $eventData['id'] ?? null;
        
        $this->logger->info('Google Calendar event created', [
            'event_id' => $eventId,
            'title' => $title,
        ]);
        
        return $eventId;
    }
    
    /**
     * Update a calendar event
     * 
     * @param string $eventId Event ID
     * @param array<string, mixed> $data Update data
     * @return bool Success status
     */
    public function updateEvent(string $eventId, array $data): bool
    {
        $response = $this->httpClient->put(
            self::API_BASE_URL . '/calendars/' . urlencode($this->calendarId) . '/events/' . urlencode($eventId),
            $data,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        
        if (isset($response['error']) || ($response['response']['code'] ?? 0) !== 200) {
            $this->logger->error('Google Calendar event update failed', [
                'event_id' => $eventId,
                'response' => $response,
            ]);
            return false;
        }
        
        $this->logger->info('Google Calendar event updated', [
            'event_id' => $eventId,
        ]);
        
        return true;
    }
    
    /**
     * Delete a calendar event
     * 
     * @param string $eventId Event ID
     * @return bool Success status
     */
    public function deleteEvent(string $eventId): bool
    {
        $response = $this->httpClient->delete(
            self::API_BASE_URL . '/calendars/' . urlencode($this->calendarId) . '/events/' . urlencode($eventId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]
        );
        
        if (isset($response['error']) && ($response['response']['code'] ?? 0) !== 204) {
            $this->logger->error('Google Calendar event deletion failed', [
                'event_id' => $eventId,
                'response' => $response,
            ]);
            return false;
        }
        
        $this->logger->info('Google Calendar event deleted', [
            'event_id' => $eventId,
        ]);
        
        return true;
    }
    
    /**
     * Check if time slot is busy
     * 
     * @param DateTimeImmutable $start Start date/time
     * @param DateTimeImmutable $end End date/time
     * @return bool True if busy
     */
    public function isBusy(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        $params = [
            'timeMin' => $start->format(\DateTimeInterface::RFC3339),
            'timeMax' => $end->format(\DateTimeInterface::RFC3339),
            'singleEvents' => 'true',
        ];
        
        $response = $this->httpClient->get(
            self::API_BASE_URL . '/calendars/' . urlencode($this->calendarId) . '/events?' . http_build_query($params),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]
        );
        
        if (isset($response['error'])) {
            $this->logger->warning('Google Calendar busy check failed', [
                'response' => $response,
            ]);
            return false; // Assume not busy on error
        }
        
        $data = json_decode($response['body'], true);
        $events = $data['items'] ?? [];
        
        return count($events) > 0;
    }
}

