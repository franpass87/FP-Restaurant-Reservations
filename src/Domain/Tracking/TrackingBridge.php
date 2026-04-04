<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;

use function add_action;
use function apply_filters;
use function array_merge;
use function do_action;
use function get_bloginfo;
use function home_url;
use function is_bool;
use function is_string;
use function uniqid;
use function wp_get_referer;

/**
 * Bridges FP-Restaurant-Reservations internal booking hooks to the
 * centralized FP-Marketing-Tracking-Layer via do_action('fp_tracking_event').
 *
 * Replaces the old Manager + GA4 + Ads + Meta + Clarity + ServerSideEventDispatcher stack.
 */
final class TrackingBridge
{
    /** Prevents double hook registration if boot() is called more than once. */
    private static bool $booted = false;

    public function __construct(
        private readonly Options $options
    ) {
    }

    public function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // New reservation created (confirmed / pending / waitlist / pending_payment)
        add_action('fp_resv_reservation_created', [$this, 'on_reservation_created'], 10, 3);

        // Event ticket purchase
        add_action('fp_resv_event_booked', [$this, 'on_event_booked'], 10, 4);

        // Status changes (admin panel: confirmed, cancelled, no_show, visited, waitlist_promoted…)
        add_action('fp_resv_reservation_status_changed', [$this, 'on_status_changed'], 10, 4);

        // Reservation moved to a different date/time
        add_action('fp_resv_reservation_moved', [$this, 'on_reservation_moved'], 10, 3);

        // Post-visit survey submitted
        add_action('fp_resv_survey_submitted', [$this, 'on_survey_submitted'], 10, 2);
    }

    /**
     * Fired when a reservation is created/confirmed.
     *
     * @param int              $reservation_id
     * @param array            $payload         Raw booking payload from the form
     * @param ReservationModel $reservation
     */
    public function on_reservation_created(int $reservation_id, array $payload, ReservationModel $reservation): void
    {
        $status   = $payload['status'] ?? 'confirmed';

        // Priorità: value totale esplicito → price_per_person × party → 0
        if (isset($payload['value']) && (float) $payload['value'] > 0) {
            $value = (float) $payload['value'];
        } elseif (isset($payload['price_per_person'], $payload['party']) && (float) $payload['price_per_person'] > 0) {
            $value = (float) $payload['price_per_person'] * (int) $payload['party'];
        } else {
            $value = 0.0;
        }

        $currency = is_string($payload['currency'] ?? null) && $payload['currency'] !== ''
            ? (string) $payload['currency']
            : 'EUR';
        $location = is_string($payload['location'] ?? null) && $payload['location'] !== ''
            ? (string) $payload['location']
            : '';

        $event_name = match ($status) {
            'confirmed'       => 'booking_confirmed',
            'pending'         => 'booking_submitted',
            'waitlist'        => 'waitlist_joined',
            'pending_payment' => 'booking_payment_required',
            default           => 'booking_submitted',
        };

        $params = [
            'reservation_id'       => $reservation_id,
            'transaction_id'       => 'resv-' . $reservation_id,
            'value'                => $value,
            'currency'             => $currency,
            'reservation_party'    => (int) ($payload['party'] ?? 1),
            'reservation_date'     => (string) ($payload['date'] ?? ''),
            'reservation_time'     => (string) ($payload['time'] ?? ''),
            'meal_type'            => '',
            'reservation_location' => $location,
            'event_id'             => uniqid('resv_' . $reservation_id . '_', true),
            'user_data'            => [
                'em' => $reservation->getEmail(),
                'fn' => $reservation->getFirstName(),
                'ln' => $reservation->getLastName(),
                'ph' => $reservation->getPhone(),
            ],
        ];

        $params = $this->enrichReservationCreatedParams($params, $payload, $reservation_id, $status);

        do_action('fp_tracking_event', $event_name, $params);

        // purchase: confermato o in attesa (stima), così GA4 riceve value anche con default "pending".
        // Esclusi pending_payment / waitlist dove il valore non è ancora definitivo.
        $purchaseStatuses = ['confirmed', 'pending'];
        if ($value > 0 && in_array($status, $purchaseStatuses, true)) {
            $purchase_params = array_merge($params, [
                'event_id'           => uniqid('resv_purchase_' . $reservation_id . '_', true),
                'value_is_estimated' => $status !== 'confirmed',
            ]);
            do_action('fp_tracking_event', 'purchase', $purchase_params);
        }
    }

    /**
     * Fired when event tickets are purchased.
     *
     * @param array $event_data
     * @param array $reservation
     * @param array $tickets
     * @param array $payload
     */
    public function on_event_booked(array $event_data, array $reservation, array $tickets, array $payload): void
    {
        $items = [];
        $total = 0.0;

        foreach ($tickets as $ticket) {
            $price    = (float) ($ticket['price'] ?? 0);
            $quantity = (int) ($ticket['quantity'] ?? 1);
            $total   += $price * $quantity;

            $items[] = [
                'item_id'   => (string) ($ticket['id'] ?? ''),
                'item_name' => (string) ($ticket['name'] ?? $event_data['title'] ?? ''),
                'price'     => $price,
                'quantity'  => $quantity,
            ];
        }

        $currency = is_string($payload['currency'] ?? null) && $payload['currency'] !== ''
            ? (string) $payload['currency']
            : 'EUR';

        $reservation_id = (int) ($reservation['id'] ?? 0);
        $location       = is_string($reservation['location'] ?? null) ? (string) $reservation['location'] : '';

        $params = [
            'reservation_id'       => $reservation_id,
            'transaction_id'       => 'resv-' . $reservation_id,
            'value'                => $total,
            'currency'             => $currency,
            'items'                => $items,
            'reservation_location' => $location,
            'event_id'             => uniqid('evt_' . $reservation_id . '_', true),
            'user_data'            => [
                'em' => (string) ($reservation['customer_email'] ?? ''),
                'fn' => (string) ($reservation['customer_name'] ?? ''),
                'ph' => (string) ($reservation['customer_phone'] ?? ''),
            ],
        ];

        do_action('fp_tracking_event', 'event_ticket_purchase', $params);
    }

    /**
     * Fired when a reservation status changes in the admin panel.
     *
     * Hook signature: fp_resv_reservation_status_changed($id, $previousStatus, $currentStatus, $entry)
     *
     * @param int    $reservation_id
     * @param string $previous_status
     * @param string $current_status
     * @param array  $entry           Full reservation record from DB
     */
    public function on_status_changed(int $reservation_id, string $previous_status, string $current_status, array $entry): void
    {
        $location = is_string($entry['location'] ?? null) ? (string) $entry['location'] : '';

        [$entry_value, $entry_currency] = $this->resolveMonetaryFromReservationEntry($entry);

        $base_params = [
            'reservation_id'       => $reservation_id,
            'transaction_id'       => 'resv-' . $reservation_id,
            'reservation_party'    => (int) ($entry['party'] ?? 1),
            'reservation_date'     => (string) ($entry['date'] ?? ''),
            'meal_type'            => (string) ($entry['meal_type'] ?? $entry['meal'] ?? ''),
            'reservation_location' => $location,
        ];

        $event_name = match ($current_status) {
            'confirmed'        => 'booking_confirmed',
            'cancelled'        => 'booking_cancelled',
            'no_show'          => 'booking_no_show',
            'visited'          => 'booking_visited',
            'waitlist'         => 'waitlist_joined',
            'waitlist_promoted'=> 'waitlist_promoted',
            'payment_completed'=> 'booking_payment_completed',
            default            => null,
        };

        if ($event_name === null) {
            return;
        }

        // Aggiunge value/currency per tutti gli eventi che hanno un valore economico
        if (in_array($event_name, ['booking_confirmed', 'booking_payment_completed'], true) && $entry_value > 0) {
            $base_params['value']    = $entry_value;
            $base_params['currency'] = $entry_currency;
            $mealKey                 = trim((string) ($entry['meal'] ?? ''));
            $party                   = (int) ($entry['party'] ?? 1);
            $items                   = $this->buildEcommerceItemsForMeal($mealKey, $party, $entry_value);
            if ($items !== null) {
                $base_params['items'] = $items;
            }
            $mealLabel = $this->resolveMealLabel($mealKey);
            if ($mealLabel !== '') {
                $base_params['meal_label'] = $mealLabel;
            }
        }

        do_action('fp_tracking_event', $event_name, $base_params);
    }

    /**
     * Fired when a reservation is moved to a different date/time.
     *
     * Hook signature: fp_resv_reservation_moved($id, $entry, $updates)
     *
     * @param int   $reservation_id
     * @param array $entry    Full reservation record (after update)
     * @param array $updates  Changed fields
     */
    public function on_reservation_moved(int $reservation_id, array $entry, array $updates): void
    {
        $location = is_string($entry['location'] ?? null) ? (string) $entry['location'] : '';

        do_action('fp_tracking_event', 'booking_moved', [
            'reservation_id'       => $reservation_id,
            'reservation_party'    => (int) ($entry['party'] ?? 1),
            'reservation_date'     => (string) ($entry['date'] ?? ''),
            'meal_type'            => (string) ($entry['meal_type'] ?? $entry['meal'] ?? ''),
            'reservation_location' => $location,
            'new_date'             => (string) ($updates['date'] ?? ''),
            'new_time'             => (string) ($updates['time'] ?? ''),
        ]);
    }

    /**
     * Fired when a post-visit survey is submitted.
     *
     * Hook signature: fp_resv_survey_submitted($reservation_id, $result)
     *
     * @param int   $reservation_id
     * @param array $result  Survey answers
     */
    public function on_survey_submitted(int $reservation_id, array $result): void
    {
        do_action('fp_tracking_event', 'survey_submitted', [
            'reservation_id' => $reservation_id,
            'rating'         => (int) ($result['rating'] ?? 0),
        ]);
    }

    /**
     * Arricchisce i parametri di tracking alla creazione prenotazione (GA4 / GTM / Meta CAPI).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrichReservationCreatedParams(array $params, array $payload, int $reservation_id, string $status): array
    {
        $mealKey = trim((string) ($payload['meal'] ?? ''));
        $party   = max(1, (int) ($payload['party'] ?? 1));
        $value   = (float) ($params['value'] ?? 0.0);

        $params['meal_type'] = $mealKey;

        $mealLabel = $this->resolveMealLabel($mealKey);
        if ($mealLabel !== '') {
            $params['meal_label'] = $mealLabel;
        }

        $params['booking_status']         = $status;
        $params['reservation_language']   = (string) ($payload['language'] ?? '');
        $params['affiliation']            = (string) get_bloginfo('name');

        $pricePerPerson = $this->resolvePricePerPerson($payload, $value, $party);
        if ($pricePerPerson !== null) {
            $params['price_per_person'] = $pricePerPerson;
        }

        $params['high_chair_count']    = max(0, (int) ($payload['high_chair_count'] ?? 0));
        $params['wheelchair_table']    = $this->boolToTrackingInt($payload['wheelchair_table'] ?? false);
        $params['pets']                = $this->boolToTrackingInt($payload['pets'] ?? false);
        $params['marketing_consent']   = $this->boolToTrackingInt($payload['marketing_consent'] ?? false);

        foreach (
            [
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'gclid',
                'fbclid',
                'msclkid',
                'ttclid',
            ] as $attributionKey
        ) {
            if (!isset($payload[$attributionKey])) {
                continue;
            }
            $raw = (string) $payload[$attributionKey];
            if ($raw !== '') {
                $params[$attributionKey] = $raw;
            }
        }

        $referer = wp_get_referer();
        if (is_string($referer) && $referer !== '') {
            $params['page_url'] = $referer;
        } else {
            $params['page_url'] = home_url('/');
        }

        $items = $this->buildEcommerceItemsForMeal($mealKey, $party, $value);
        if ($items !== null) {
            $params['items'] = $items;
        }

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('fp_resv_tracking_reservation_created_params', $params, $payload, $reservation_id, $status);

        return $filtered;
    }

    /**
     * Etichetta display del pasto dal piano frontend (se configurato).
     */
    private function resolveMealLabel(string $mealKey): string
    {
        if ($mealKey === '') {
            return '';
        }

        $definition = (string) $this->options->getField('fp_resv_general', 'frontend_meals', '');
        if ($definition === '') {
            return '';
        }

        $meals = MealPlan::parse($definition);
        $byKey = MealPlan::indexByKey($meals);
        if (!isset($byKey[$mealKey]['label'])) {
            return '';
        }

        return (string) $byKey[$mealKey]['label'];
    }

    /**
     * Prezzo a persona per item ecommerce: da payload o da value / party.
     *
     * @param array<string, mixed> $payload
     */
    private function resolvePricePerPerson(array $payload, float $value, int $party): ?float
    {
        if (isset($payload['price_per_person']) && (float) $payload['price_per_person'] > 0) {
            return round((float) $payload['price_per_person'], 2);
        }

        if ($value > 0.0 && $party > 0) {
            return round($value / $party, 2);
        }

        return null;
    }

    /**
     * Righe `items` GA4 / Meta per una prenotazione pasto (prezzo × coperti).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildEcommerceItemsForMeal(string $mealKey, int $party, float $value): ?array
    {
        if ($value <= 0.0 || $party <= 0) {
            return null;
        }

        $ppp = $value / $party;
        if ($ppp <= 0.0) {
            return null;
        }

        $pppRounded = round($ppp, 2);
        $label      = $this->resolveMealLabel(trim($mealKey));
        $name       = $label !== '' ? $label : ($mealKey !== '' ? $mealKey : 'restaurant_reservation');
        $id         = $mealKey !== '' ? $mealKey : 'reservation';

        return [[
            'item_id'       => $id,
            'item_name'     => $name,
            'item_category' => 'restaurant_reservation',
            'price'         => $pppRounded,
            'quantity'      => $party,
        ]];
    }

    /**
     * Ricava value e currency da una riga prenotazione (es. dopo update stato in admin).
     * Se `value` in DB è assente/zero, ricostruisce dal prezzo nel piano pasti × coperti (come al submit).
     *
     * @param array<string, mixed> $entry Record agenda / DB join customer.
     * @return array{0: float, 1: string}
     */
    private function resolveMonetaryFromReservationEntry(array $entry): array
    {
        $currency = trim((string) ($entry['currency'] ?? ''));
        if ($currency === '' || !preg_match('/^[A-Z]{3}$/i', $currency)) {
            $general = $this->options->getGroup('fp_resv_general', ['default_currency' => 'EUR']);
            $currency = strtoupper((string) ($general['default_currency'] ?? 'EUR'));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                $currency = 'EUR';
            }
        } else {
            $currency = strtoupper($currency);
        }

        if (isset($entry['value']) && (float) $entry['value'] > 0) {
            return [(float) $entry['value'], $currency];
        }

        $party = max(1, (int) ($entry['party'] ?? 1));
        if (isset($entry['price_per_person']) && (float) $entry['price_per_person'] > 0) {
            return [round((float) $entry['price_per_person'] * $party, 2), $currency];
        }

        $mealKey = trim((string) ($entry['meal'] ?? ''));
        if ($mealKey === '') {
            return [0.0, $currency];
        }

        $definition = (string) $this->options->getField('fp_resv_general', 'frontend_meals', '');
        if ($definition === '') {
            return [0.0, $currency];
        }

        $meals = MealPlan::parse($definition);
        if ($meals === []) {
            return [0.0, $currency];
        }

        $byKey = MealPlan::indexByKey($meals);
        if (!isset($byKey[$mealKey]['price'])) {
            return [0.0, $currency];
        }

        $price = (float) $byKey[$mealKey]['price'];
        if ($price <= 0.0) {
            return [0.0, $currency];
        }

        return [round($price * $party, 2), $currency];
    }

    private function boolToTrackingInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}
