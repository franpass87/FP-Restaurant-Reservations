<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use DateTimeImmutable;
use DateTimeZone;
use function array_filter;
use function array_map;
use function array_merge;
use function count;
use function floor;
use function gmdate;
use function implode;
use function is_string;
use function sprintf;
use function str_replace;
use function strlen;
use function strtoupper;
use function substr;
use function trim;
use function uniqid;

final class ICS
{
    /**
     * @param array{uid?:string,summary?:string,description?:string,location?:string,organizer?:string,url?:string,attendees?:array<int, string>,start:DateTimeImmutable,end:DateTimeImmutable,timezone?:string} $data
     */
    public static function generate(array $data): string
    {
        $start    = $data['start'];
        $end      = $data['end'];
        $timezone = $data['timezone'] ?? 'Europe/Rome';

        $tz = new DateTimeZone($timezone);
        $startTz = $start->setTimezone($tz);
        $endTz   = $end->setTimezone($tz);

        $uid      = $data['uid'] ?? uniqid('fp-resv-', true);
        $summary  = $data['summary'] ?? 'Restaurant Reservation';
        $desc     = $data['description'] ?? '';
        $location = $data['location'] ?? '';
        $organizer = $data['organizer'] ?? '';
        $url       = $data['url'] ?? '';
        $attendees = $data['attendees'] ?? [];

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'PRODID:-//FP Restaurant Reservations//EN';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = self::buildVTimezone($tz, $startTz);
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = self::foldLine('UID:' . $uid);
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = sprintf('DTSTART;TZID=%s:%s', $timezone, $startTz->format('Ymd\THis'));
        $lines[] = sprintf('DTEND;TZID=%s:%s', $timezone, $endTz->format('Ymd\THis'));
        $lines[] = self::foldLine('SUMMARY:' . self::escapeText($summary));
        if ($desc !== '') {
            $lines[] = self::foldLine('DESCRIPTION:' . self::escapeText($desc));
        }
        if ($location !== '') {
            $lines[] = self::foldLine('LOCATION:' . self::escapeText($location));
        }
        if ($organizer !== '') {
            $lines[] = self::foldLine('ORGANIZER:' . self::escapeText($organizer));
        }
        if ($url !== '') {
            $lines[] = self::foldLine('URL:' . self::escapeText($url));
        }
        foreach ($attendees as $attendee) {
            if (!is_string($attendee) || $attendee === '') {
                continue;
            }

            $lines[] = self::foldLine('ATTENDEE:' . self::escapeText($attendee));
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_filter($lines)) . "\r\n";
    }

    private static function escapeText(string $value): string
    {
        $escaped = str_replace(['\\', ';', ',', "\r", "\n"], ['\\\\', '\\;', '\\,', '', '\\n'], $value);

        return $escaped;
    }

    private static function foldLine(string $line): string
    {
        $line   = trim($line);
        $length = 75;
        $output = '';

        while (strlen($line) > $length) {
            $output .= substr($line, 0, $length) . "\r\n ";
            $line = substr($line, $length);
        }

        $output .= $line;

        return $output;
    }

    private static function buildVTimezone(DateTimeZone $timezone, DateTimeImmutable $reference): string
    {
        $yearStart = (new DateTimeImmutable($reference->format('Y') . '-01-01 00:00:00', $timezone))->setTimezone(new DateTimeZone('UTC'));
        $yearEnd   = $yearStart->modify('+2 years');

        $transitions = $timezone->getTransitions($yearStart->getTimestamp(), $yearEnd->getTimestamp());

        $standard = null;
        $daylight = null;

        for ($i = 1, $len = count($transitions); $i < $len; $i++) {
            $current = $transitions[$i];
            $previous = $transitions[$i - 1];

            if ($current['ts'] <= $reference->getTimestamp()) {
                continue;
            }

            if ($current['isdst'] && $daylight === null) {
                $daylight = [$previous, $current];
            }

            if (!$current['isdst'] && $standard === null) {
                $standard = [$previous, $current];
            }

            if ($daylight !== null && $standard !== null) {
                break;
            }
        }

        if ($standard === null && $daylight === null) {
            $offset = $timezone->getOffset($reference);

            return implode("\r\n", [
                'BEGIN:VTIMEZONE',
                'TZID:' . $timezone->getName(),
                'X-LIC-LOCATION:' . $timezone->getName(),
                'BEGIN:STANDARD',
                'TZOFFSETFROM:' . self::formatOffset($offset),
                'TZOFFSETTO:' . self::formatOffset($offset),
                'TZNAME:' . strtoupper($timezone->getName()),
                'DTSTART:' . $reference->format('Ymd\THis'),
                'END:STANDARD',
                'END:VTIMEZONE',
            ]);
        }

        $sections = ['BEGIN:VTIMEZONE', 'TZID:' . $timezone->getName(), 'X-LIC-LOCATION:' . $timezone->getName()];

        if ($daylight !== null) {
            [$prev, $curr] = $daylight;
            $sections = array_merge($sections, self::buildTransition('DAYLIGHT', $prev, $curr));
        }

        if ($standard !== null) {
            [$prev, $curr] = $standard;
            $sections = array_merge($sections, self::buildTransition('STANDARD', $prev, $curr));
        }

        $sections[] = 'END:VTIMEZONE';

        return implode("\r\n", array_map([self::class, 'foldLine'], $sections));
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $current
     *
     * @return array<int, string>
     */
    private static function buildTransition(string $type, array $previous, array $current): array
    {
        return [
            'BEGIN:' . strtoupper($type),
            'TZOFFSETFROM:' . self::formatOffset((int) $previous['offset']),
            'TZOFFSETTO:' . self::formatOffset((int) $current['offset']),
            'TZNAME:' . strtoupper((string) $current['abbr']),
            'DTSTART:' . gmdate('Ymd\THis', (int) $current['ts']),
            'END:' . strtoupper($type),
        ];
    }

    private static function formatOffset(int $offset): string
    {
        $sign = $offset >= 0 ? '+' : '-';
        $offset = abs($offset);
        $hours = (int) floor($offset / 3600);
        $minutes = (int) floor(($offset % 3600) / 60);

        return sprintf('%s%02d%02d', $sign, $hours, $minutes);
    }
}
