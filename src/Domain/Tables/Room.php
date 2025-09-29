<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

final class Room
{
    public int $id;
    public string $name;
    public string $description = '';
    public string $color = '';
    public int $capacity = 0;
    public int $orderIndex = 0;
    public bool $active = true;
    /**
     * @var array<int, Table>
     */
    public array $tables = [];
}
