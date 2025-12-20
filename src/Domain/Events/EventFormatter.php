<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function is_array;
use function json_decode;

/**
 * Formatta i dati evento per la risposta.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class EventFormatter
{
    public function __construct(
        private readonly TicketCounter $ticketCounter,
        private readonly EventPermalinkResolver $permalinkResolver
    ) {
    }

    /**
     * Formatta un evento da riga database.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function format(array $row): array
    {
        $eventId = (int) ($row['id'] ?? 0);
        $capacity = isset($row['capacity']) ? (int) $row['capacity'] : null;
        $sold     = $this->ticketCounter->count($eventId);

        $settings = [];
        if (!empty($row['settings_json'])) {
            $decoded = json_decode((string) $row['settings_json'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $startAt = (string) ($row['start_at'] ?? '');

        return [
            'id'                  => $eventId,
            'title'               => (string) ($row['title'] ?? ''),
            'slug'                => (string) ($row['slug'] ?? ''),
            'start_at'            => $startAt,
            'end_at'              => (string) ($row['end_at'] ?? ''),
            'capacity'            => $capacity,
            'remaining_capacity'  => $capacity !== null ? max(0, $capacity - $sold) : null,
            'price'               => isset($row['price']) ? (float) $row['price'] : null,
            'currency'            => (string) ($row['currency'] ?? ''),
            'status'              => (string) ($row['status'] ?? 'draft'),
            'lang'                => (string) ($row['lang'] ?? ''),
            'settings'            => $settings,
            'permalink'           => $this->permalinkResolver->resolve((string) ($row['slug'] ?? '')),
        ];
    }
}















