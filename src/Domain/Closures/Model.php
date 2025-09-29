<?php
declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeImmutable;

final class Model
{
    public int $id;
    public string $scope;
    public string $type;
    public DateTimeImmutable $startAt;
    public DateTimeImmutable $endAt;
    public ?int $roomId = null;
    public ?int $tableId = null;
    /** @var array<string, mixed>|null */
    public ?array $recurrence = null;
    /** @var array<string, mixed>|null */
    public ?array $capacityOverride = null;
    public string $note = '';
    public bool $active = true;
    public int $priority = 0;
}
