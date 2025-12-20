<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures\Models;

use DateTimeImmutable;

/**
 * Closure Model
 * 
 * Represents a restaurant closure in the domain layer.
 * This is a pure domain object with no WordPress dependencies.
 *
 * @package FP\Resv\Domain\Closures\Models
 */
final class Closure
{
    private ?int $id = null;
    private string $title;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private string $scope; // 'all', 'room', 'table'
    private ?int $roomId = null;
    private ?int $tableId = null;
    private bool $isRecurring = false;
    private ?string $recurrenceRule = null;
    private bool $isActive = true;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;
    
    /**
     * Constructor
     * 
     * @param string $title Closure title
     * @param DateTimeImmutable $startDate Start date
     * @param DateTimeImmutable $endDate End date
     * @param string $scope Closure scope
     */
    public function __construct(
        string $title,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $scope = 'all'
    ) {
        $this->title = $title;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->scope = $scope;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getStartDate(): DateTimeImmutable { return $this->startDate; }
    public function getEndDate(): DateTimeImmutable { return $this->endDate; }
    public function getScope(): string { return $this->scope; }
    public function getRoomId(): ?int { return $this->roomId; }
    public function getTableId(): ?int { return $this->tableId; }
    public function isRecurring(): bool { return $this->isRecurring; }
    public function getRecurrenceRule(): ?string { return $this->recurrenceRule; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    
    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setRoomId(?int $roomId): void { $this->roomId = $roomId; }
    public function setTableId(?int $tableId): void { $this->tableId = $tableId; }
    public function setRecurring(bool $isRecurring, ?string $rule = null): void
    {
        $this->isRecurring = $isRecurring;
        $this->recurrenceRule = $rule;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->updatedAt = new DateTimeImmutable();
    }
    
    /**
     * Check if closure affects a specific date
     * 
     * @param DateTimeImmutable $date Date to check
     * @return bool
     */
    public function affectsDate(DateTimeImmutable $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }
    
    /**
     * Convert to array for persistence
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'scope' => $this->scope,
            'room_id' => $this->roomId,
            'table_id' => $this->tableId,
            'is_recurring' => $this->isRecurring,
            'recurrence_rule' => $this->recurrenceRule,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Create from array (for repository hydration)
     * 
     * @param array<string, mixed> $data Closure data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['start_date'] ?? '');
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['end_date'] ?? '');
        
        $closure = new self(
            $data['title'] ?? '',
            $startDate ?: new DateTimeImmutable(),
            $endDate ?: new DateTimeImmutable(),
            $data['scope'] ?? 'all'
        );
        
        if (isset($data['id'])) {
            $closure->setId((int) $data['id']);
        }
        
        if (isset($data['room_id'])) {
            $closure->setRoomId((int) $data['room_id']);
        }
        
        if (isset($data['table_id'])) {
            $closure->setTableId((int) $data['table_id']);
        }
        
        if (isset($data['is_recurring'])) {
            $closure->setRecurring(
                (bool) $data['is_recurring'],
                $data['recurrence_rule'] ?? null
            );
        }
        
        if (isset($data['is_active'])) {
            $closure->setActive((bool) $data['is_active']);
        }
        
        return $closure;
    }
}










