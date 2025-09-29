<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Models;

final class Reservation
{
    public int $id;
    public string $status;
    public string $date;
    public string $time;
    public int $party;
    public string $email = '';
    public \DateTimeImmutable $created;
    public ?string $calendarEventId = null;
    public ?string $calendarSyncStatus = null;
    public ?\DateTimeImmutable $calendarSyncedAt = null;
}
