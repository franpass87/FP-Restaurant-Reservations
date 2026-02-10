<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use function array_filter;
use function count;
use function is_numeric;
use function is_string;
use function max;
use function round;
use function strtolower;
use function substr;

/**
 * Costruisce i payload degli eventi di tracking per prenotazioni ed eventi.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class ReservationEventBuilder
{
    public function __construct(
        private readonly Ads $ads,
        private readonly Meta $meta
    ) {
    }

    /**
     * Costruisce l'evento per una prenotazione creata.
     *
     * @param int $reservationId
     * @param array<string, mixed> $payload
     * @param ReservationModel $reservation
     * @param string $eventId
     * @return array<string, mixed>
     */
    public function buildReservationEvent(
        int $reservationId,
        array $payload,
        ReservationModel $reservation,
        string $eventId
    ): array {
        $status   = strtolower($reservation->getStatus());
        $value    = isset($payload['value']) && is_numeric($payload['value']) ? (float) $payload['value'] : 0.0;
        $currency = is_string($payload['currency'] ?? null) && $payload['currency'] !== '' ? (string) $payload['currency'] : 'EUR';
        $location = is_string($payload['location'] ?? null) && $payload['location'] !== '' ? (string) $payload['location'] : 'default';

        // Se value non è inviato ma c'è prezzo a persona, usa value stimato (per tracking conversioni/Purchase)
        if ($value <= 0.0 && isset($payload['price_per_person']) && is_numeric($payload['price_per_person'])) {
            $pricePerPerson = (float) $payload['price_per_person'];
            if ($pricePerPerson > 0.0) {
                $party = isset($payload['party']) && is_numeric($payload['party'])
                    ? max(1, (int) $payload['party'])
                    : max(1, $reservation->getParty());
                $value = round($pricePerPerson * $party, 2);
            }
        }

        $event = [
            'event'       => 'reservation_submit',
            'event_id'    => $eventId,
            'reservation' => [
                'id'       => $reservationId,
                'status'   => $status,
                'date'     => $reservation->getDate(),
                'time'     => substr($reservation->getTime(), 0, 5),
                'party'    => $reservation->getParty(),
                'location' => $location,
            ],
            'ga4' => [
                'name'   => 'reservation_submit',
                'params' => array_filter([
                    'reservation_id'     => $reservationId,
                    'reservation_status' => $status,
                    'reservation_party'  => $reservation->getParty(),
                    'reservation_date'   => $reservation->getDate(),
                    'reservation_time'   => substr($reservation->getTime(), 0, 5),
                    'reservation_location' => $location,
                    'value'              => $value > 0 ? $value : null,
                    'currency'           => $currency,
                    'event_id'           => $eventId,
                ], static fn ($val) => $val !== null && $val !== ''), 
            ],
        ];

        if ($status === 'confirmed') {
            $event['ga4']['name']             = 'reservation_confirmed';
            $event['reservation']['status']   = 'confirmed';
            $adsPayload                       = $this->ads->conversionPayload($reservationId, $value, $currency);
            $metaPayload                      = $this->meta->eventPayload('Purchase', $value, $currency, $reservationId);
            if ($adsPayload !== null) {
                $event['ads'] = $adsPayload;
            }
            if ($metaPayload !== null) {
                $event['meta'] = $metaPayload;
                $event['meta']['event_id'] = $eventId;
            }
        } elseif ($status === 'waitlist') {
            $event['ga4']['name'] = 'waitlist_joined';
        } elseif ($status === 'pending_payment') {
            $event['ga4']['name'] = 'reservation_payment_required';
        }

        return $event;
    }

    /**
     * Costruisce l'evento per un ticket evento acquistato.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $reservation
     * @param array<int, array<string, mixed>> $tickets
     * @param array<string, mixed> $payload
     * @param string $eventId
     * @return array<string, mixed>
     */
    public function buildEventTicketEvent(
        array $eventData,
        array $reservation,
        array $tickets,
        array $payload,
        string $eventId
    ): array {
        $count    = count($tickets);
        $value    = isset($eventData['price']) && is_numeric($eventData['price']) ? (float) $eventData['price'] * max(1, $count) : 0.0;
        $currency = is_string($eventData['currency'] ?? null) && $eventData['currency'] !== '' ? (string) $eventData['currency'] : ($payload['currency'] ?? 'EUR');

        $metaPayload = $this->meta->eventPayload('Purchase', $value, $currency, (int) ($reservation['id'] ?? 0));
        $adsPayload  = $this->ads->conversionPayload((int) ($reservation['id'] ?? 0), $value, $currency);

        $event = [
            'event'  => 'event_ticket_purchase',
            'event_id' => $eventId,
            'event_meta' => [
                'event_id' => $eventData['id'] ?? null,
                'tickets'  => $count,
            ],
            'ga4'   => [
                'name'   => 'event_ticket_purchase',
                'params' => array_filter([
                    'items'   => [
                        [
                            'item_id'   => 'event-' . ($eventData['id'] ?? '0'),
                            'item_name' => $eventData['title'] ?? '',
                            'quantity'  => $count,
                            'price'     => $value > 0 && $count > 0 ? $value / $count : 0,
                        ],
                    ],
                    'value'    => $value > 0 ? $value : null,
                    'currency' => $currency,
                    'event_id' => $eventId,
                ], static fn ($val) => $val !== null),
            ],
        ];

        if ($metaPayload !== null) {
            $event['meta'] = $metaPayload;
            $event['meta']['event_id'] = $eventId;
        }

        if ($adsPayload !== null) {
            $event['ads'] = $adsPayload;
        }

        return $event;
    }

    /**
     * Costruisce l'evento per un acquisto stimato (basato su prezzo per persona).
     *
     * @param array<string, mixed> $payload
     * @param ReservationModel $reservation
     * @param string $currency
     * @return array<string, mixed>|null
     */
    public function buildEstimatedPurchaseEvent(
        array $payload,
        ReservationModel $reservation,
        string $currency
    ): ?array {
        if (isset($payload['value']) && is_numeric($payload['value']) && (float) $payload['value'] > 0) {
            return null;
        }

        if (!isset($payload['price_per_person']) || !is_numeric($payload['price_per_person'])) {
            return null;
        }

        $price = (float) $payload['price_per_person'];
        if ($price <= 0.0) {
            return null;
        }

        $party = isset($payload['party']) && is_numeric($payload['party'])
            ? max(1, (int) $payload['party'])
            : max(1, $reservation->getParty());

        $estimated = round($price * $party, 2);
        if ($estimated <= 0.0) {
            return null;
        }

        $currency = $currency !== '' ? $currency : 'EUR';
        $mealType = isset($payload['meal']) && is_string($payload['meal']) ? (string) $payload['meal'] : '';

        return [
            'event'    => 'purchase',
            'purchase' => [
                'value'              => $estimated,
                'currency'           => $currency,
                'value_is_estimated' => true,
                'meal_type'          => $mealType,
                'party_size'         => $party,
            ],
            'reservation' => [
                'id'        => $reservation->getId(),
                'status'    => strtolower($reservation->getStatus()),
                'party'     => $party,
                'meal_type' => $mealType,
            ],
            'ga4' => [
                'name'   => 'purchase',
                'params' => array_filter([
                    'reservation_id'     => $reservation->getId(),
                    'reservation_party'  => $party,
                    'meal_type'          => $mealType !== '' ? $mealType : null,
                    'value'              => $estimated,
                    'currency'           => $currency,
                    'value_is_estimated' => true,
                ], static fn ($val) => $val !== null && $val !== ''),
            ],
        ];
    }
}















