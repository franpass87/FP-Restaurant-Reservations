<?php
declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeImmutable;

final class Model
{
    // Closure types
    public const TYPE_FULL = 'full';
    public const TYPE_CAPACITY_REDUCTION = 'capacity_reduction';
    public const TYPE_SPECIAL_HOURS = 'special_hours';
    public const TYPE_SPECIAL_OPENING = 'special_opening';
    
    public const VALID_TYPES = [
        self::TYPE_FULL,
        self::TYPE_CAPACITY_REDUCTION,
        self::TYPE_SPECIAL_HOURS,
        self::TYPE_SPECIAL_OPENING,
    ];
    
    // Scope types
    public const SCOPE_RESTAURANT = 'restaurant';
    public const SCOPE_ROOM = 'room';
    public const SCOPE_TABLE = 'table';

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
