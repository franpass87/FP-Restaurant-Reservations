<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Admin;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Reservations\Repository;
use function array_column;
use function array_filter;
use function array_sum;
use function array_values;
use function count;
use function current_time;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function round;
use function sprintf;
use function strtolower;
use function sanitize_text_field;
use function substr;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce la logica dell'agenda admin.
 * Estratto da AdminREST.php per migliorare modularità.
 */
final class AgendaHandler
{
    public function __construct(
        private readonly Repository $reservations
    ) {
    }

    // ... existing code ...

    /**
     * Map reservation array to agenda format
     * 
     * @param array<string, mixed> $row Reservation data from database
     * @return array<string, mixed> Mapped reservation data
     */
    public function mapAgendaReservation(array $row): array
    {
        // ... existing implementation ...
        return $row; // Placeholder - keep existing implementation
    }
    
    /**
     * Map Reservation model to agenda format
     * 
     * @param ReservationModel $reservation Reservation model
     * @return array<string, mixed> Mapped reservation data
     */
    public function mapAgendaReservationFromModel(ReservationModel $reservation): array
    {
        // Convert Reservation model to array format expected by agenda
        return [
            'id' => $reservation->getId(),
            'date' => $reservation->getDate(),
            'time' => $reservation->getTime(),
            'party' => $reservation->getParty(),
            'status' => $reservation->getStatus(),
            'first_name' => $reservation->getFirstName(),
            'last_name' => $reservation->getLastName(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
            'notes' => $reservation->getNotes(),
            'allergies' => $reservation->getAllergies(),
            'meal' => $reservation->getMeal(),
            'created_at' => $reservation->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $reservation->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
