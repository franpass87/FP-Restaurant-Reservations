<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use FP\Resv\Core\Logging;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Options;
use function add_action;
use function current_time;
use function in_array;
use function rawurlencode;
use function sprintf;
use function strtolower;

final class GoogleCalendarService
{

    private ?array $googleSettings = null;
    private ?array $generalSettings = null;

    public function __construct(
        private readonly Options $options,
        private readonly ReservationsRepository $reservations,
        private readonly GoogleCalendarApiClient $apiClient,
        private readonly GoogleCalendarEventBuilder $eventBuilder,
        private readonly GoogleCalendarWindowBuilder $windowBuilder,
        private readonly GoogleCalendarBusyChecker $busyChecker
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

        return $this->apiClient->isConnected();
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
        $window = $this->windowBuilder->build($date, $time);
        if ($window === null) {
            return false;
        }

        $calendarId = $this->calendarId();
        return $this->busyChecker->hasConflict($window['start'], $window['end'], $calendarId, $excludeEventId);
    }

    public function exchangeAuthorizationCode(string $code): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $this->apiClient->exchangeAuthorizationCode($code);
    }

    public function disconnect(): void
    {
        $this->apiClient->disconnect();
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
        $window = $this->windowBuilder->buildFromRow($row);
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

        $payload = $this->eventBuilder->build($reservationId, $row, $window, $context);
        $response = $this->apiClient->request('PUT', $path, $payload);

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

        $response = $this->apiClient->request('DELETE', $path);
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

}
