<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

final class Table
{
    public int $id;
    public int $roomId;
    public string $code = '';
    public ?int $seatsMin = null;
    public ?int $seatsStd = null;
    public ?int $seatsMax = null;
    /**
     * @var array<string, mixed>
     */
    public array $attributes = [];
    public ?string $joinGroup = null;
    public ?float $posX = null;
    public ?float $posY = null;
    public string $status = 'available';
    public bool $active = true;
    public int $orderIndex = 0;
}
