<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Availability;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Costruisce il payload per uno slot di disponibilità.
 * Estratto da Availability per migliorare la manutenibilità.
 */
final class SlotPayloadBuilder
{
    /**
     * Costruisce il payload per uno slot.
     *
     * @param array<int, array{tables:int[], seats:int, type:string}> $suggestions
     * @return array<string, mixed>
     */
    public function build(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $status,
        int $capacity,
        int $party,
        bool $waitlist,
        array $reasons,
        array $suggestions
    ): array {
        return [
            'start'              => $start->format(DateTimeInterface::ATOM),
            'end'                => $end->format(DateTimeInterface::ATOM),
            'label'              => $start->format('H:i'),
            'status'             => $status,
            'available_capacity' => $capacity,
            'requested_party'    => $party,
            'waitlist_available' => $waitlist && $status === 'full',
            'reasons'            => $reasons,
            'suggested_tables'   => $suggestions,
        ];
    }
}















