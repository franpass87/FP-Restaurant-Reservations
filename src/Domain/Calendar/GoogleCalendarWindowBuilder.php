<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use function substr;
use function trim;

/**
 * Costruisce le finestre temporali per gli eventi Google Calendar.
 * Estratto da GoogleCalendarService per migliorare la manutenibilità.
 */
final class GoogleCalendarWindowBuilder
{
    private const DEFAULT_TIMEZONE = 'Europe/Rome';
    private const DEFAULT_DURATION_HOURS = 2;

    /**
     * Costruisce una finestra temporale da una riga del database.
     *
     * @param array<string, mixed> $row
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, timezone: string}|null
     */
    public function buildFromRow(array $row): ?array
    {
        $date = (string) ($row['date'] ?? '');
        $time = substr((string) ($row['time'] ?? ''), 0, 5);

        return $this->build($date, $time);
    }

    /**
     * Costruisce una finestra temporale da data e ora.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, timezone: string}|null
     */
    public function build(?string $date, ?string $time): ?array
    {
        $date = is_string($date) ? trim($date) : '';
        $time = is_string($time) ? trim($time) : '';
        if ($date === '' || $time === '') {
            return null;
        }

        try {
            $timezone = new DateTimeZone(self::DEFAULT_TIMEZONE);
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $timezone);
            if ($start === false) {
                return null;
            }

            $end = $start->add(new DateInterval('PT' . self::DEFAULT_DURATION_HOURS . 'H'));

            return [
                'start'    => $start,
                'end'      => $end,
                'timezone' => self::DEFAULT_TIMEZONE,
            ];
        } catch (\Exception $exception) {
            return null;
        }
    }
}















