<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use DateInterval;
use DateTimeImmutable;
use function __;
use function array_map;
use function in_array;
use function is_array;
use function sprintf;
use function strtolower;

/**
 * Valuta chiusure e loro impatto sulla disponibilità.
 * Estratto da Availability.php per migliorare modularità.
 */
final class ClosureEvaluator
{
    /**
     * @param array<int, array<string, mixed>> $closures
     *
     * @return array{status:string, blocked_tables:int[], capacity_percent:int, reasons:string[]}
     */
    public function evaluateClosures(array $closures, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): array
    {
        $blockedTables   = [];
        $capacityPercent = 100;
        $status          = 'open';
        $reasons         = [];

        foreach ($closures as $closure) {
            if (!$this->closureApplies($closure, $slotStart, $slotEnd, $roomId)) {
                continue;
            }

            if ($closure['capacity_override'] !== null && isset($closure['capacity_override']['percent'])) {
                $percent = (int) $closure['capacity_override']['percent'];
                $capacityPercent = min($capacityPercent, max(0, min(100, $percent)));
                $reasons[] = sprintf(
                    __('Capienza ridotta al %d%% da una regola di orario speciale.', 'fp-restaurant-reservations'),
                    $capacityPercent
                );
                continue;
            }

            if ($closure['scope'] === 'table' && $closure['table_id'] !== null) {
                $blockedTables[] = $closure['table_id'];
                $reasons[]       = __('Tavolo non disponibile per chiusura programmata.', 'fp-restaurant-reservations');
                continue;
            }

            if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
                continue;
            }

            $status    = 'blocked';
            $reasons[] = __('Slot non prenotabile per chiusura programmata.', 'fp-restaurant-reservations');
        }

        return [
            'status'          => $status,
            'blocked_tables'  => $blockedTables,
            'capacity_percent'=> $capacityPercent,
            'reasons'         => $reasons,
        ];
    }

    private function closureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): bool
    {
        if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
            return false;
        }

        if ($closure['scope'] === 'table' && $closure['table_id'] === null) {
            return false;
        }

        if ($closure['recurrence'] !== null) {
            return $this->recurringClosureApplies($closure, $slotStart, $slotEnd);
        }

        return $closure['start'] < $slotEnd && $closure['end'] > $slotStart;
    }

    private function recurringClosureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): bool
    {
        $recurrence = $closure['recurrence'];
        $type       = strtolower((string) ($recurrence['type'] ?? ''));
        $until      = isset($recurrence['until']) ? trim((string) $recurrence['until']) : '';
        $from       = isset($recurrence['from']) ? trim((string) $recurrence['from']) : '';

        if ($from !== '') {
            $fromDate = new DateTimeImmutable($from . ' 00:00:00', $slotStart->getTimezone());
            if ($slotStart < $fromDate) {
                return false;
            }
        }

        if ($until !== '') {
            $untilDate = new DateTimeImmutable($until . ' 23:59:59', $slotStart->getTimezone());
            if ($slotStart > $untilDate) {
                return false;
            }
        }

        $dayKey = strtolower($slotStart->format('D'));

        switch ($type) {
            case 'weekly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? array_map(static fn ($day): string => strtolower((string) $day), $days) : [];
                if (!in_array($dayKey, $days, true) && !in_array((string) $slotStart->format('N'), $days, true)) {
                    return false;
                }
                break;
            case 'daily':
                // Daily applies to all days within the from/until window.
                break;
            case 'monthly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? $days : [];
                $dayOfMonth = (int) $slotStart->format('j');
                if ($days !== [] && !in_array($dayOfMonth, array_map('intval', $days), true)) {
                    return false;
                }
                break;
            default:
                return false;
        }

        $startTime = $closure['start']->format('H:i:s');
        $endTime   = $closure['end']->format('H:i:s');

        $occurrenceStart = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $startTime, $slotStart->getTimezone());
        $occurrenceEnd   = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $endTime, $slotStart->getTimezone());

        if ($occurrenceEnd <= $occurrenceStart) {
            $occurrenceEnd = $occurrenceEnd->add(new DateInterval('P1D'));
        }

        return $occurrenceStart < $slotEnd && $occurrenceEnd > $slotStart;
    }
}
















