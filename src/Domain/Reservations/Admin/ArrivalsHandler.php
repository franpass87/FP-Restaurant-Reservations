<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Admin;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Domain\Reservations\Repository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function array_map;
use function array_values;
use function error_log;
use function implode;
use function in_array;
use function is_string;
use function preg_split;
use function rest_ensure_response;
use function sanitize_text_field;
use function sprintf;
use function strtolower;
use function substr;
use function trim;
use function wp_timezone;
use function __;

/**
 * Gestisce gli endpoint REST per gli arrivi.
 * Estratto da AdminREST per migliorare la manutenibilità.
 */
final class ArrivalsHandler
{
    public function __construct(
        private readonly Repository $reservations
    ) {
    }

    /**
     * Gestisce la richiesta di arrivi.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $range = strtolower((string) $request->get_param('range'));
            if (!in_array($range, ['today', 'week'], true)) {
                $range = 'today';
            }

            $timezone = wp_timezone();
            $start    = new DateTimeImmutable('today', $timezone);
            $end      = $range === 'week' ? $start->add(new DateInterval('P6D')) : $start;

            $filters = [];

            $room = $request->get_param('room');
            if ($room !== null && $room !== '') {
                $filters['room'] = (string) $room;
            }

            $status = $request->get_param('status');
            if ($status !== null && $status !== '') {
                $filters['status'] = sanitize_text_field((string) $status);
            }

            $rows = $this->reservations->findArrivals(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $filters
            );

            $reservations = array_map([$this, 'mapReservation'], $rows);

            $responseData = [
                'range'        => [
                    'mode'  => $range,
                    'start' => $start->format('Y-m-d'),
                    'end'   => $end->format('Y-m-d'),
                ],
                'reservations' => $reservations,
            ];
            
            return rest_ensure_response($responseData);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Arrivals] Errore critico: ' . $e->getMessage());
            }
            return new WP_Error(
                'fp_resv_arrivals_error',
                sprintf(__('Errore nel caricamento degli arrivi: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Mappa una prenotazione per gli arrivi.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function mapReservation(array $row): array
    {
        $time = isset($row['time']) ? substr((string) $row['time'], 0, 5) : '';

        $guestParts = [];
        if (!empty($row['first_name'])) {
            $guestParts[] = (string) $row['first_name'];
        }
        if (!empty($row['last_name'])) {
            $guestParts[] = (string) $row['last_name'];
        }

        $guest = trim(implode(' ', $guestParts));
        if ($guest === '') {
            $guest = (string) ($row['email'] ?? '');
        }

        $tableParts = [];
        if (!empty($row['table_code'])) {
            $tableParts[] = (string) $row['table_code'];
        }
        if (!empty($row['room_name'])) {
            $tableParts[] = (string) $row['room_name'];
        }

        $tableLabel = $tableParts !== [] ? implode(' · ', $tableParts) : '';

        $allergies = [];
        if (!empty($row['allergies']) && is_string($row['allergies'])) {
            $chunks = preg_split('/[\r\n,;]+/', (string) $row['allergies']) ?: [];
            $allergies = array_values(array_filter(array_map(static function ($value) {
                $value = trim((string) $value);
                return $value !== '' ? $value : null;
            }, $chunks)));
        }

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'date'         => (string) ($row['date'] ?? ''),
            'time'         => $time,
            'party'        => (int) ($row['party'] ?? 0),
            'table_label'  => $tableLabel,
            'guest'        => $guest,
            'notes'        => isset($row['notes']) ? (string) $row['notes'] : '',
            'allergies'    => $allergies,
            'status'       => (string) ($row['status'] ?? ''),
            'language'     => (string) ($row['customer_lang'] ?? ($row['lang'] ?? '')),
            'phone'        => isset($row['phone']) ? (string) $row['phone'] : '',
        ];
    }
}


