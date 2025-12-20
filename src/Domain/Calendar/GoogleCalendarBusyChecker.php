<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Core\Logging;
use FP\Resv\Domain\Settings\Options;
use function http_build_query;
use function is_array;
use function rawurlencode;
use function sprintf;

/**
 * Verifica conflitti di disponibilità su Google Calendar.
 * Estratto da GoogleCalendarService per migliorare la manutenibilità.
 */
final class GoogleCalendarBusyChecker
{
    public function __construct(
        private readonly Options $options,
        private readonly GoogleCalendarApiClient $apiClient
    ) {
    }

    /**
     * Verifica se una finestra temporale ha conflitti con eventi esistenti.
     */
    public function hasConflict(DateTimeImmutable $start, DateTimeImmutable $end, string $calendarId, ?string $excludeEventId = null): bool
    {
        if (!$this->shouldBlockOnBusy()) {
            return false;
        }

        $calendarIdEncoded = rawurlencode($calendarId);
        $params = http_build_query([
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'timeMin'      => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'timeMax'      => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'maxResults'   => 10,
        ]);

        $response = $this->apiClient->request('GET', sprintf('/calendars/%s/events?%s', $calendarIdEncoded, $params));
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

            $itemId = (string) ($item['id'] ?? '');
            if ($excludeEventId !== null && $itemId === $excludeEventId) {
                continue;
            }

            $itemStart = $item['start']['dateTime'] ?? null;
            $itemEnd   = $item['end']['dateTime'] ?? null;

            if ($itemStart === null || $itemEnd === null) {
                continue;
            }

            try {
                $itemStartDt = new DateTimeImmutable($itemStart);
                $itemEndDt   = new DateTimeImmutable($itemEnd);

                if ($itemStartDt < $end && $itemEndDt > $start) {
                    return true;
                }
            } catch (\Exception $exception) {
                continue;
            }
        }

        return false;
    }

    /**
     * Verifica se il servizio dovrebbe bloccare su eventi occupati.
     */
    private function shouldBlockOnBusy(): bool
    {
        $settings = $this->googleSettings();

        return ($settings['google_calendar_overbooking_guard'] ?? '0') === '1';
    }

    /**
     * @return array<string, mixed>
     */
    private function googleSettings(): array
    {
        return $this->options->getGroup('fp_resv_google_calendar', []);
    }
}















