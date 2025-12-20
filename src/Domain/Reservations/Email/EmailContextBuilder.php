<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Email;

use DateTimeImmutable;
use FP\Resv\Domain\Notifications\Settings as NotificationSettings;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Settings\Language;
use function get_bloginfo;
use function wp_parse_args;

/**
 * Costruisce il contesto completo per le email di prenotazione.
 * Estratto da EmailService per migliorare la manutenibilità.
 */
final class EmailContextBuilder
{
    public function __construct(
        private readonly Language $language,
        private readonly NotificationSettings $notificationSettings
    ) {
    }

    /**
     * Costruisce il contesto completo per le email.
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @param int $reservationId ID prenotazione
     * @param string $manageUrl URL gestione prenotazione
     * @param string $status Stato prenotazione
     * @param ReservationModel $reservation Modello prenotazione
     * @param array<string, mixed> $general Impostazioni generali
     * @return array<string, mixed> Contesto completo
     */
    public function build(
        array $payload,
        int $reservationId,
        string $manageUrl,
        string $status,
        ReservationModel $reservation,
        array $general
    ): array {
        $general = wp_parse_args($general, [
            'restaurant_name'        => get_bloginfo('name'),
            'restaurant_timezone'    => 'Europe/Rome',
            'table_turnover_minutes' => '120',
        ]);

        $timezone = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');
        if ($timezone === '') {
            $timezone = 'Europe/Rome';
        }

        $turnover = (int) ($general['table_turnover_minutes'] ?? 120);
        if ($turnover <= 0) {
            $turnover = 120;
        }

        $languageCode = $payload['language'] !== '' ? $payload['language'] : $this->language->getDefaultLanguage();

        $context = [
            'id'            => $reservationId,
            'status'        => $status,
            'status_label'  => $this->language->statusLabel($status, $languageCode),
            'date'          => $payload['date'],
            'time'          => $payload['time'],
            'party'         => $payload['party'],
            'meal'          => $payload['meal'] ?? '',
            'manage_url'    => $manageUrl,
            'notes'         => $payload['notes'],
            'allergies'     => $payload['allergies'],
            'extras'        => [
                'high_chair_count' => (int) ($payload['high_chair_count'] ?? 0),
                'wheelchair_table' => (bool) ($payload['wheelchair_table'] ?? false),
                'pets'             => (bool) ($payload['pets'] ?? false),
            ],
            'language'      => $payload['language'],
            'locale'        => $payload['locale'],
            'location'      => $payload['location'],
            'currency'      => $payload['currency'],
            'room_id'       => $payload['room_id'],
            'table_id'      => $payload['table_id'],
            'created_at'    => $reservation->created,
            'utm'           => [
                'source'   => $payload['utm_source'],
                'medium'   => $payload['utm_medium'],
                'campaign' => $payload['utm_campaign'],
            ],
            'customer'      => [
                'first_name' => $payload['first_name'],
                'last_name'  => $payload['last_name'],
                'email'      => $payload['email'],
                'phone'      => $payload['phone'],
            ],
            'restaurant'    => [
                'name'             => (string) ($general['restaurant_name'] ?? get_bloginfo('name')),
                'timezone'         => $timezone,
                'turnover_minutes' => $turnover,
                'logo_url'         => $this->notificationSettings->logoUrl(),
            ],
        ];

        $context['date_formatted'] = $this->language->formatDate($payload['date'], $languageCode);
        $context['time_formatted'] = $this->language->formatTime($payload['time'], $languageCode);
        $context['datetime_formatted'] = $this->language->formatDateTime(
            $payload['date'],
            $payload['time'],
            $languageCode,
            $timezone
        );

        if ($reservation->created instanceof DateTimeImmutable) {
            $context['created_at_formatted'] = $this->language->formatDateTimeObject(
                $reservation->created,
                $languageCode,
                $timezone
            );
        } else {
            $context['created_at_formatted'] = '';
        }

        return $context;
    }
}















