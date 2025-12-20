<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use InvalidArgumentException;
use function in_array;
use function max;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Normalizza i dati di sale e tavoli.
 * Estratto da LayoutService.php per migliorare modularità.
 */
final class RoomTableNormalizer
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalizeRoomData(array $data): array
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        if ($name === '') {
            throw new InvalidArgumentException('Room name is required.');
        }

        $color = isset($data['color']) ? trim((string) $data['color']) : '';
        if ($color !== '' && preg_match('/^#?[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new InvalidArgumentException('Room color must be a valid hex value.');
        }

        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }

        return [
            'name'        => $name,
            'description' => isset($data['description']) ? trim((string) $data['description']) : '',
            'color'       => $color,
            'capacity'    => isset($data['capacity']) ? max(0, (int) $data['capacity']) : 0,
            'order_index' => isset($data['order_index']) ? (int) $data['order_index'] : 0,
            'active'      => !empty($data['active']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalizeTableData(array $data): array
    {
        $code = isset($data['code']) ? trim((string) $data['code']) : '';
        if ($code === '') {
            throw new InvalidArgumentException('Table code is required.');
        }

        if (!isset($data['room_id'])) {
            throw new InvalidArgumentException('Table room_id is required.');
        }

        $roomId = (int) $data['room_id'];
        if ($roomId <= 0) {
            throw new InvalidArgumentException('Table room_id must be a positive integer.');
        }

        $status = isset($data['status']) ? strtolower(trim((string) $data['status'])) : 'available';
        if (!in_array($status, ['available', 'blocked', 'maintenance', 'hidden'], true)) {
            $status = 'available';
        }

        $attributes = [];
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $attributes = $data['attributes'];
        }

        return [
            'room_id'    => $roomId,
            'code'       => $code,
            'status'     => $status,
            'seats_min'  => isset($data['seats_min']) ? $this->positiveOrNull($data['seats_min']) : null,
            'seats_std'  => isset($data['seats_std']) ? $this->positiveOrNull($data['seats_std']) : null,
            'seats_max'  => isset($data['seats_max']) ? $this->positiveOrNull($data['seats_max']) : null,
            'attributes' => $attributes,
            'pos_x'      => isset($data['pos_x']) ? (float) $data['pos_x'] : null,
            'pos_y'      => isset($data['pos_y']) ? (float) $data['pos_y'] : null,
            'active'     => !empty($data['active']),
            'order_index'=> isset($data['order_index']) ? (int) $data['order_index'] : 0,
        ];
    }

    public function positiveOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    public function normalizeGroupCode(string $code, int $roomId = 0): string
    {
        $code = trim($code);
        if ($code === '') {
            return $this->generateGroupCode($roomId);
        }

        if (preg_match('/^[A-Za-z0-9_-]{3,30}$/', $code) !== 1) {
            throw new InvalidArgumentException('Group code must contain only alphanumeric characters, dashes, or underscores.');
        }

        return strtolower($code);
    }

    public function generateGroupCode(int $roomId = 0): string
    {
        return 'grp-' . ($roomId > 0 ? $roomId . '-' : '') . substr(uniqid('', true), -6);
    }
}

