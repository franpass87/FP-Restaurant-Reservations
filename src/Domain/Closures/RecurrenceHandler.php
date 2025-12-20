<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use function array_key_exists;
use function in_array;
use function is_array;
use function sanitize_key;
use function strtolower;

/**
 * Gestisce la logica delle ricorrenze per le chiusure.
 * Estratto da Service.php per migliorare modularità.
 */
final class RecurrenceHandler
{
    /**
     * @return array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public function expandOccurrences(Model $model, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        if ($model->recurrence === null) {
            if ($model->endAt < $rangeStart || $model->startAt > $rangeEnd) {
                return [];
            }

            return [[
                'start' => $model->startAt > $rangeStart ? $model->startAt : $rangeStart,
                'end'   => $model->endAt < $rangeEnd ? $model->endAt : $rangeEnd,
            ]];
        }

        $occurrences = [];
        $cursor      = $rangeStart->setTime(0, 0, 0);
        $limit       = $rangeEnd->setTime(23, 59, 59);
        $timezone    = $model->startAt->getTimezone();

        while ($cursor <= $limit) {
            if ($this->recurrenceMatches($model, $cursor)) {
                $occurrenceStart = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $model->startAt->format('H:i:s'), $timezone);
                $occurrenceEnd   = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $model->endAt->format('H:i:s'), $timezone);

                if ($occurrenceEnd <= $occurrenceStart) {
                    $occurrenceEnd = $occurrenceEnd->add(new DateInterval('P1D'));
                }

                if ($occurrenceEnd < $rangeStart || $occurrenceStart > $rangeEnd) {
                    $cursor = $cursor->add(new DateInterval('P1D'));
                    continue;
                }

                $occurrences[] = [
                    'start' => $occurrenceStart > $rangeStart ? $occurrenceStart : $rangeStart,
                    'end'   => $occurrenceEnd < $rangeEnd ? $occurrenceEnd : $rangeEnd,
                ];
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $occurrences;
    }

    public function recurrenceMatches(Model $model, DateTimeImmutable $day): bool
    {
        $recurrence = $model->recurrence;
        if ($recurrence === null) {
            return false;
        }

        $timezone = $model->startAt->getTimezone();

        if (isset($recurrence['from']) && $recurrence['from'] !== '') {
            $from = new DateTimeImmutable($recurrence['from'] . ' 00:00:00', $timezone);
            if ($day < $from) {
                return false;
            }
        }

        if (isset($recurrence['until']) && $recurrence['until'] !== '') {
            $until = new DateTimeImmutable($recurrence['until'] . ' 23:59:59', $timezone);
            if ($day > $until) {
                return false;
            }
        }

        $type = sanitize_key((string) ($recurrence['type'] ?? ''));

        if ($type === 'daily') {
            return true;
        }

        if ($type === 'weekly') {
            $days = $recurrence['days'] ?? [];
            if (!is_array($days) || $days === []) {
                return false;
            }

            $dayName   = strtolower($day->format('D'));
            $dayNumber = $day->format('N');

            foreach ($days as $candidate) {
                $candidate = strtolower((string) $candidate);
                if ($candidate === $dayName || $candidate === $dayNumber) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'monthly') {
            $days = $recurrence['days'] ?? [];
            if (is_array($days) && $days !== []) {
                $dayOfMonth = (int) $day->format('j');
                foreach ($days as $candidate) {
                    if ((int) $candidate === $dayOfMonth) {
                        return true;
                    }
                }

                return false;
            }

            if (isset($recurrence['week_of_month'])) {
                $week    = strtolower((string) $recurrence['week_of_month']);
                if ($week === '') {
                    return false;
                }

                $weekday = strtolower($day->format('D'));
                $first   = $day->modify('first day of this month');
                $cursor  = $first;
                $index   = 0;

                while ($cursor->format('m') === $day->format('m')) {
                    if (strtolower($cursor->format('D')) === $weekday) {
                        ++$index;
                        $isLast = $cursor->modify('+7 days')->format('m') !== $day->format('m');
                        if ($cursor->format('j') === $day->format('j')) {
                            if ($week === 'last' && $isLast) {
                                return true;
                            }

                            $map = [
                                'first'  => 1,
                                'second' => 2,
                                'third'  => 3,
                                'fourth' => 4,
                            ];

                            if (isset($map[$week]) && $map[$week] === $index) {
                                return true;
                            }
                        }
                    }

                    $cursor = $cursor->add(new DateInterval('P1D'));
                }

                return false;
            }

            return false;
        }

        return false;
    }
}
















