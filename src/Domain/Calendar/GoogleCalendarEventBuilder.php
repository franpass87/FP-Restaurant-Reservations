<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use DateTimeImmutable;
use FP\Resv\Domain\Settings\Options;
use function __;
use function array_filter;
use function get_bloginfo;
use function home_url;
use function implode;
use function sanitize_email;
use function sprintf;
use function trim;

/**
 * Costruisce i payload degli eventi per Google Calendar.
 * Estratto da GoogleCalendarService per migliorare la manutenibilità.
 */
final class GoogleCalendarEventBuilder
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Costruisce il payload per un evento Google Calendar.
     *
     * @param array<string, mixed> $row
     * @param array{start: DateTimeImmutable, end: DateTimeImmutable, timezone: string} $window
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function build(int $reservationId, array $row, array $window, array $context): array
    {
        $general = $this->generalSettings();
        $restaurantName = (string) ($general['restaurant_name'] ?? get_bloginfo('name'));
        if ($restaurantName === '') {
            $restaurantName = get_bloginfo('name');
        }

        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName  = trim((string) ($row['last_name'] ?? ''));
        $customerName = trim($firstName . ' ' . $lastName);
        if ($customerName === '') {
            $customerName = __('Ospite', 'fp-restaurant-reservations');
        }

        $party = (int) ($row['party'] ?? 0);
        $summary = sprintf(
            __('%s - %s (%d coperti)', 'fp-restaurant-reservations'),
            $restaurantName !== '' ? $restaurantName : __('Prenotazione', 'fp-restaurant-reservations'),
            $customerName,
            $party
        );

        $descriptionLines = array_filter([
            sprintf(__('Prenotazione #%d', 'fp-restaurant-reservations'), $reservationId),
            sprintf(__('Cliente: %s', 'fp-restaurant-reservations'), $customerName),
            $party > 0 ? sprintf(__('Coperti: %d', 'fp-restaurant-reservations'), $party) : null,
            !empty($row['phone']) ? sprintf(__('Telefono: %s', 'fp-restaurant-reservations'), $row['phone']) : null,
            !empty($row['notes']) ? sprintf(__('Note: %s', 'fp-restaurant-reservations'), $row['notes']) : null,
            !empty($row['allergies']) ? sprintf(__('Allergie: %s', 'fp-restaurant-reservations'), $row['allergies']) : null,
            !empty($context['manage_url']) ? sprintf(__('Gestione prenotazione: %s', 'fp-restaurant-reservations'), $context['manage_url']) : null,
        ]);

        $settings = $this->googleSettings();
        $privacy  = (string) ($settings['google_calendar_privacy'] ?? 'private');

        $payload = [
            'summary'     => $summary,
            'description' => implode("\n", $descriptionLines),
            'start'       => [
                'dateTime' => $window['start']->format('c'),
                'timeZone' => $window['timezone'],
            ],
            'end' => [
                'dateTime' => $window['end']->format('c'),
                'timeZone' => $window['timezone'],
            ],
            'visibility'              => 'private',
            'guestsCanModify'         => false,
            'guestsCanInviteOthers'   => false,
            'guestsCanSeeOtherGuests' => $privacy === 'guests',
            'extendedProperties'      => [
                'private' => [
                    'reservation_id' => (string) $reservationId,
                    'source'         => 'fp-restaurant-reservations',
                ],
            ],
            'source' => [
                'title' => 'FP Restaurant Reservations',
                'url'   => home_url('/'),
            ],
        ];

        if (!empty($context['manage_url'])) {
            $payload['extendedProperties']['private']['manage_url'] = (string) $context['manage_url'];
        }

        if ($privacy === 'guests') {
            $email = sanitize_email((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $payload['attendees'] = [[
                    'email'         => $email,
                    'displayName'   => $customerName,
                    'responseStatus'=> 'needsAction',
                ]];
            }
        } else {
            $payload['attendees'] = [];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function googleSettings(): array
    {
        return $this->options->getGroup('fp_resv_google_calendar', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function generalSettings(): array
    {
        return $this->options->getGroup('fp_resv_general', []);
    }
}















