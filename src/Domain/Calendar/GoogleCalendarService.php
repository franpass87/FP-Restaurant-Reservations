<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP\Resv\Core\Logging;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use function __;
use function add_action;
use function array_filter;
use function current_time;
use function get_bloginfo;
use function get_option;
use function home_url;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_wp_error;
use function json_decode;
use function max;
use function rawurlencode;
use function sanitize_email;
use function sprintf;
use function substr;
use function time;
use function trim;
use function update_option;
use function delete_option;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

final class GoogleCalendarService
{
    private const TOKEN_OPTION = 'fp_resv_google_calendar_tokens';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private ?array $googleSettings = null;
    private ?array $generalSettings = null;

    public function __construct(
        private readonly Options $options,
        private readonly ReservationsRepository $reservations
    ) {
    }

    public function boot(): void
    {
        add_action('fp_resv_reservation_created', [$this, 'handleReservationCreated'], 50, 4);
        add_action('fp_resv_reservation_status_changed', [$this, 'handleReservationStatusChanged'], 50, 4);
        add_action('fp_resv_reservation_updated', [$this, 'handleReservationUpdated'], 50, 4);
        add_action('fp_resv_reservation_moved', [$this, 'handleReservationMoved'], 50, 3);
    }

    public function isEnabled(): bool
    {
        $settings = $this->googleSettings();

        return ($settings['google_calendar_enabled'] ?? '0') === '1';
    }

    public function isConnected(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $tokens = $this->getStoredTokens();

        return isset($tokens['refresh_token']) && $tokens['refresh_token'] !== '';
    }

    public function shouldBlockOnBusy(): bool
    {
        $settings = $this->googleSettings();

        return $this->isConnected() && ($settings['google_calendar_overbooking_guard'] ?? '0') === '1';
    }

    public function eventId(int $reservationId): string
    {
        return 'fp-resv-' . $reservationId;
    }

    public function isWindowBusy(string $date, string $time, ?string $excludeEventId = null): bool
    {
        $window = $this->buildWindow($date, $time);
        if ($window === null) {
            return false;
        }

        return $this->hasBusyConflict($window['start'], $window['end'], $excludeEventId);
    }

    public function exchangeAuthorizationCode(string $code): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $settings = $this->googleSettings();
        $redirect = (string) ($settings['google_calendar_redirect_uri'] ?? '');

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $settings['google_calendar_client_id'] ?? '',
                'client_secret' => $settings['google_calendar_client_secret'] ?? '',
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            Logging::log('google_calendar', 'Failed to exchange authorization code', [
                'error' => $response->get_error_message(),
            ]);

            return false;
        }

        $data = $this->decodeResponse(wp_remote_retrieve_body($response));
        if (!is_array($data) || empty($data['refresh_token'])) {
            Logging::log('google_calendar', 'Unexpected token payload from Google', [
                'response' => $data,
            ]);

            return false;
        }

        $tokens = [
            'access_token'  => (string) ($data['access_token'] ?? ''),
            'refresh_token' => (string) $data['refresh_token'],
        ];

        if (!empty($data['expires_in'])) {
            $tokens['expires_at'] = time() + (int) $data['expires_in'];
        }

        $this->storeTokens($tokens);

        return true;
    }

    public function disconnect(): void
    {
        delete_option(self::TOKEN_OPTION);
    }

    public function handleReservationCreated(int $reservationId, array $payload, ReservationModel $reservation, string $manageUrl): void
    {
        unset($payload, $reservation);

        $this->syncReservation($reservationId, ['manage_url' => $manageUrl]);
    }

    public function handleReservationStatusChanged(int $reservationId, string $previousStatus, string $currentStatus, array $row): void
    {
        unset($previousStatus, $currentStatus, $row);

        $this->syncReservation($reservationId);
    }

    public function handleReservationUpdated(int $reservationId, ?array $entry, array $updates, ?array $original): void
    {
        unset($entry, $updates, $original);

        $this->syncReservation($reservationId);
    }

    public function handleReservationMoved(int $reservationId, ?array $entry, array $updates): void
    {
        unset($entry, $updates);

        $this->syncReservation($reservationId);
    }

    private function syncReservation(int $reservationId, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$this->isConnected()) {
            $this->reservations->update($reservationId, [
                'calendar_sync_status' => 'disconnected',
            ]);

            return;
        }

        $row = $this->reservations->findAgendaEntry($reservationId);
        if ($row === null) {
            $model = $this->reservations->find($reservationId);
            if ($model === null) {
                return;
            }

            $row = [
                'id'                => $model->id,
                'status'            => $model->status,
                'date'              => $model->date,
                'time'              => $model->time,
                'party'             => $model->party,
                'calendar_event_id' => $model->calendarEventId,
            ];
        }

        $status = strtolower((string) ($row['status'] ?? 'pending'));
        $window = $this->buildWindowFromRow($row);
        if ($window === null) {
            return;
        }

        if ($this->shouldCreateEvent($status)) {
            $this->upsertEvent($reservationId, $row, $window, $context);
        } else {
            $this->deleteEvent($reservationId, $row);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $window
     * @param array<string, mixed> $context
     */
    private function upsertEvent(int $reservationId, array $row, array $window, array $context): void
    {
        $eventId    = $this->eventId($reservationId);
        $calendarId = rawurlencode($this->calendarId());
        $path       = sprintf('/calendars/%s/events/%s?sendUpdates=none', $calendarId, rawurlencode($eventId));

        $payload = $this->buildEventPayload($reservationId, $row, $window, $context);
        $response = $this->request('PUT', $path, $payload);

        if ($response['success']) {
            $this->reservations->update($reservationId, [
                'calendar_event_id'    => $eventId,
                'calendar_synced_at'   => current_time('mysql'),
                'calendar_sync_status' => 'synced',
                'calendar_last_error'  => null,
            ]);

            return;
        }

        Logging::log('google_calendar', 'Failed to sync reservation with Google Calendar', [
            'reservation_id' => $reservationId,
            'error'          => $response['message'],
            'code'           => $response['code'],
            'response'       => $response['data'],
        ]);

        $this->reservations->update($reservationId, [
            'calendar_sync_status' => 'error',
            'calendar_last_error'  => $response['message'],
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function deleteEvent(int $reservationId, array $row): void
    {
        $eventId = (string) ($row['calendar_event_id'] ?? $this->eventId($reservationId));
        if ($eventId === '') {
            $this->reservations->update($reservationId, [
                'calendar_event_id'    => null,
                'calendar_sync_status' => 'skipped',
                'calendar_synced_at'   => current_time('mysql'),
                'calendar_last_error'  => null,
            ]);

            return;
        }

        $calendarId = rawurlencode($this->calendarId());
        $path       = sprintf('/calendars/%s/events/%s?sendUpdates=none', $calendarId, rawurlencode($eventId));

        $response = $this->request('DELETE', $path);
        if ($response['success'] || $response['code'] === 404) {
            $this->reservations->update($reservationId, [
                'calendar_event_id'    => null,
                'calendar_synced_at'   => current_time('mysql'),
                'calendar_sync_status' => 'deleted',
                'calendar_last_error'  => null,
            ]);

            return;
        }

        Logging::log('google_calendar', 'Failed to remove reservation event from Google Calendar', [
            'reservation_id' => $reservationId,
            'error'          => $response['message'],
        ]);

        $this->reservations->update($reservationId, [
            'calendar_sync_status' => 'error',
            'calendar_last_error'  => $response['message'],
        ]);
    }

    private function hasBusyConflict(DateTimeImmutable $start, DateTimeImmutable $end, ?string $excludeEventId = null): bool
    {
        if (!$this->shouldBlockOnBusy()) {
            return false;
        }

        $calendarId = rawurlencode($this->calendarId());
        $params = http_build_query([
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'timeMin'      => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'timeMax'      => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'maxResults'   => 10,
        ]);

        $response = $this->request('GET', sprintf('/calendars/%s/events?%s', $calendarId, $params));
        if (!$response['success']) {
            Logging::log('google_calendar', 'Unable to perform busy check on Google Calendar', [
                'error' => $response['message'],
            ]);

            return false;
        }

        $items = $response['data']['items'] ?? [];
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = (string) ($item['status'] ?? '');
            if ($status === 'cancelled') {
                continue;
            }

            $eventId = (string) ($item['id'] ?? '');
            if ($excludeEventId !== null && $eventId === $excludeEventId) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function shouldCreateEvent(string $status): bool
    {
        return !in_array($status, ['cancelled', 'no-show'], true);
    }

    private function calendarId(): string
    {
        $settings = $this->googleSettings();
        $calendar = (string) ($settings['google_calendar_calendar_id'] ?? '');

        return $calendar !== '' ? $calendar : 'primary';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventPayload(int $reservationId, array $row, array $window, array $context): array
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

    private function buildWindowFromRow(array $row): ?array
    {
        $date = (string) ($row['date'] ?? '');
        $time = substr((string) ($row['time'] ?? ''), 0, 5);

        return $this->buildWindow($date, $time);
    }

    private function buildWindow(?string $date, ?string $time): ?array
    {
        $date = is_string($date) ? trim($date) : '';
        $time = is_string($time) ? trim($time) : '';
        if ($date === '' || $time === '') {
            return null;
        }

        $general = $this->generalSettings();
        $timezoneId = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');

        try {
            $timezone = new DateTimeZone($timezoneId);
        } catch (Exception) {
            $timezone = new DateTimeZone('Europe/Rome');
        }

        $time = substr($time, 0, 5);
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $timezone);
        if ($start === false) {
            try {
                $start = new DateTimeImmutable($date . ' ' . $time, $timezone);
            } catch (Exception $exception) {
                Logging::log('google_calendar', 'Unable to parse reservation datetime for calendar window', [
                    'date'  => $date,
                    'time'  => $time,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        }

        $duration = (int) ($general['table_turnover_minutes'] ?? 120);
        if ($duration <= 0) {
            $duration = 120;
        }

        $end = $start->add(new DateInterval('PT' . max(15, $duration) . 'M'));

        return [
            'start'    => $start,
            'end'      => $end,
            'timezone' => $timezone->getName(),
        ];
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'message' => 'missing_access_token',
            ];
        }

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ];

        if ($body !== null) {
            $encoded = wp_json_encode($body);
            $args['body'] = $encoded === false ? '{}' : $encoded;
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        }

        $response = wp_remote_request(self::API_BASE . $path, $args);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $bodyContent = wp_remote_retrieve_body($response);
        $data = $this->decodeResponse($bodyContent);

        $success = $code >= 200 && $code < 300;

        return [
            'success' => $success,
            'code'    => $code,
            'data'    => $data,
            'message' => $success ? '' : $this->extractErrorMessage($data, $bodyContent),
        ];
    }

    private function getAccessToken(): ?string
    {
        $tokens = $this->getStoredTokens();
        if ($tokens === []) {
            return null;
        }

        $expiresAt = isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : 0;
        if (empty($tokens['access_token']) || $expiresAt <= time() + 60) {
            $refreshed = $this->refreshTokens($tokens);
            if ($refreshed === null) {
                return null;
            }

            $tokens = $refreshed;
        }

        return isset($tokens['access_token']) ? (string) $tokens['access_token'] : null;
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>|null
     */
    private function refreshTokens(array $current): ?array
    {
        if (empty($current['refresh_token'])) {
            return null;
        }

        $settings = $this->googleSettings();
        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout' => 20,
            'body'    => [
                'client_id'     => $settings['google_calendar_client_id'] ?? '',
                'client_secret' => $settings['google_calendar_client_secret'] ?? '',
                'refresh_token' => $current['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            Logging::log('google_calendar', 'Failed to refresh Google OAuth token', [
                'error' => $response->get_error_message(),
            ]);

            return null;
        }

        $data = $this->decodeResponse(wp_remote_retrieve_body($response));
        if (!is_array($data) || empty($data['access_token'])) {
            Logging::log('google_calendar', 'Invalid response when refreshing Google OAuth token', [
                'response' => $data,
            ]);

            return null;
        }

        $tokens = [
            'access_token'  => (string) $data['access_token'],
            'refresh_token' => (string) ($current['refresh_token'] ?? ''),
        ];

        if (!empty($data['refresh_token'])) {
            $tokens['refresh_token'] = (string) $data['refresh_token'];
        }

        if (!empty($data['expires_in'])) {
            $tokens['expires_at'] = time() + (int) $data['expires_in'];
        }

        $this->storeTokens($tokens);

        return $tokens;
    }

    private function getStoredTokens(): array
    {
        $value = get_option(self::TOKEN_OPTION, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $tokens
     */
    private function storeTokens(array $tokens): void
    {
        $existing = $this->getStoredTokens();
        $merged   = array_filter(array_merge($existing, $tokens));

        update_option(self::TOKEN_OPTION, $merged);
    }

    private function googleSettings(): array
    {
        if ($this->googleSettings !== null) {
            return $this->googleSettings;
        }

        $defaults = [
            'google_calendar_enabled'            => '0',
            'google_calendar_client_id'          => '',
            'google_calendar_client_secret'      => '',
            'google_calendar_redirect_uri'       => '',
            'google_calendar_calendar_id'        => '',
            'google_calendar_privacy'            => 'private',
            'google_calendar_overbooking_guard'  => '1',
        ];

        $this->googleSettings = $this->options->getGroup('fp_resv_google_calendar', $defaults);

        return $this->googleSettings;
    }

    private function generalSettings(): array
    {
        if ($this->generalSettings !== null) {
            return $this->generalSettings;
        }

        $defaults = [
            'restaurant_name'        => get_bloginfo('name'),
            'restaurant_timezone'    => 'Europe/Rome',
            'table_turnover_minutes' => 120,
        ];

        $this->generalSettings = $this->options->getGroup('fp_resv_general', $defaults);

        return $this->generalSettings;
    }

    private function decodeResponse(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractErrorMessage(mixed $data, string $fallback): string
    {
        if (is_array($data)) {
            if (!empty($data['error']['message'])) {
                return (string) $data['error']['message'];
            }

            if (!empty($data['error_description'])) {
                return (string) $data['error_description'];
            }

            if (!empty($data['message'])) {
                return (string) $data['message'];
            }
        }

        return $fallback !== '' ? $fallback : 'google_calendar_error';
    }
}
