<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Core\ICS;
use PHPUnit\Framework\TestCase;

final class ICSTest extends TestCase
{
    public function testGeneratesEscapedCalendar(): void
    {
        $start = new DateTimeImmutable('2025-03-10 19:30:00', new DateTimeZone('UTC'));
        $end   = $start->modify('+2 hours');

        $ics = ICS::generate([
            'uid' => 'test-uid',
            'summary' => 'Cena speciale, degustazione',
            'description' => "Prima linea\nSeconda; linea con ; e , e \\ backslash",
            'location' => "Ristorante \"La, Pergola\"; Sala\nVIP",
            'organizer' => 'mailto:host@example.com',
            'url' => 'https://example.com/reservation?id=42',
            'attendees' => [
                'mailto:guest@example.com',
                'mailto:vip@example.com',
                '',
                123,
            ],
            'start' => $start,
            'end' => $end,
            'timezone' => 'Europe/Rome',
        ]);

        self::assertStringContainsString('UID:test-uid', $ics);
        self::assertStringContainsString('SUMMARY:Cena speciale\\, degustazione', $ics);
        self::assertStringContainsString('DESCRIPTION:Prima linea\\nSeconda\\; linea con \\; e \\, e \\\\ backslash', $ics);
        self::assertStringContainsString('LOCATION:Ristorante "La\\, Pergola"\\; Sala\\nVIP', $ics);
        self::assertStringContainsString('DTSTART;TZID=Europe/Rome:20250310T203000', $ics);
        self::assertStringContainsString('DTEND;TZID=Europe/Rome:20250310T223000', $ics);
        self::assertStringContainsString('BEGIN:DAYLIGHT', $ics);
        self::assertStringContainsString('BEGIN:STANDARD', $ics);
        self::assertSame(2, substr_count($ics, 'ATTENDEE:mailto:'));
    }

    public function testFoldLineWrapsLongFieldsAndTerminatesWithCrLf(): void
    {
        $start = new DateTimeImmutable('2025-03-10 19:30:00', new DateTimeZone('UTC'));
        $end   = $start->modify('+30 minutes');

        $ics = ICS::generate([
            'start' => $start,
            'end' => $end,
            'timezone' => 'Europe/Rome',
            'summary' => str_repeat('A', 90),
        ]);

        self::assertStringContainsString("\r\n ", $ics);
        self::assertStringEndsWith("\r\n", $ics);
    }

    public function testGeneratesStructuredAttendees(): void
    {
        $start = new DateTimeImmutable('2025-07-01 18:00:00', new DateTimeZone('UTC'));
        $end   = $start->modify('+90 minutes');

        $ics = ICS::generate([
            'start' => $start,
            'end' => $end,
            'timezone' => 'Europe/Rome',
            'attendees' => [
                'mailto:string@example.com',
                [
                    'email' => 'guest@example.com',
                    'name' => 'Mario Rossi',
                    'role' => 'req-participant',
                    'rsvp' => true,
                ],
                [
                    'email' => 'mailto:manager@example.com',
                    'name' => 'Responsabile; Eventi',
                    'role' => 'chair',
                    'rsvp' => false,
                    'type' => 'resource',
                    'status' => 'accepted',
                ],
                ['email' => '   '],
                ['name' => 'Missing email'],
            ],
        ]);

        $normalized = str_replace("\r\n ", '', $ics);

        self::assertStringContainsString('ATTENDEE:mailto:string@example.com', $normalized);
        self::assertStringContainsString('ATTENDEE;CN="Mario Rossi";ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:guest@example.com', $normalized);
        self::assertStringContainsString('ATTENDEE;CN="Responsabile; Eventi";ROLE=CHAIR;RSVP=FALSE;CUTYPE=RESOURCE;PARTSTAT=ACCEPTED:mailto:manager@example.com', $normalized);
        self::assertSame(3, substr_count($normalized, 'ATTENDEE'));
    }

    public function testTimezoneTransitionsUseLocalTime(): void
    {
        $start = new DateTimeImmutable('2025-03-10 19:30:00', new DateTimeZone('UTC'));
        $end   = $start->modify('+2 hours');

        $ics = ICS::generate([
            'start' => $start,
            'end' => $end,
            'timezone' => 'Europe/Rome',
        ]);

        $normalized = str_replace("\r\n ", '', $ics);

        self::assertStringContainsString('BEGIN:DAYLIGHT', $normalized);
        self::assertStringContainsString('DTSTART:20250330T030000', $normalized);
        self::assertStringContainsString('BEGIN:STANDARD', $normalized);
        self::assertStringContainsString('DTSTART:20251026T020000', $normalized);
    }
}
