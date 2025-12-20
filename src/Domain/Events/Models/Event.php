<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events\Models;

use DateTimeImmutable;

/**
 * Event Model
 * 
 * Represents a restaurant event in the domain layer.
 * This is a pure domain object with no WordPress dependencies.
 *
 * @package FP\Resv\Domain\Events\Models
 */
final class Event
{
    private ?int $id = null;
    private string $title;
    private string $description;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private int $maxCapacity;
    private int $currentBookings = 0;
    private bool $isActive = true;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;
    
    /**
     * Constructor
     * 
     * @param string $title Event title
     * @param string $description Event description
     * @param DateTimeImmutable $startDate Start date
     * @param DateTimeImmutable $endDate End date
     * @param int $maxCapacity Maximum capacity
     */
    public function __construct(
        string $title,
        string $description,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $maxCapacity
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->maxCapacity = $maxCapacity;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getStartDate(): DateTimeImmutable { return $this->startDate; }
    public function getEndDate(): DateTimeImmutable { return $this->endDate; }
    public function getMaxCapacity(): int { return $this->maxCapacity; }
    public function getCurrentBookings(): int { return $this->currentBookings; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    
    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setCurrentBookings(int $count): void
    {
        $this->currentBookings = $count;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->updatedAt = new DateTimeImmutable();
    }
    
    /**
     * Check if event has available capacity
     * 
     * @return bool
     */
    public function hasAvailability(): bool
    {
        return $this->isActive && $this->currentBookings < $this->maxCapacity;
    }
    
    /**
     * Get available capacity
     * 
     * @return int
     */
    public function getAvailableCapacity(): int
    {
        return max(0, $this->maxCapacity - $this->currentBookings);
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
            'description' => $this->description,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate->format('Y-m-d H:i:s'),
            'max_capacity' => $this->maxCapacity,
            'current_bookings' => $this->currentBookings,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Create from array (for repository hydration)
     * 
     * @param array<string, mixed> $data Event data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['start_date'] ?? '');
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['end_date'] ?? '');
        
        $event = new self(
            $data['title'] ?? '',
            $data['description'] ?? '',
            $startDate ?: new DateTimeImmutable(),
            $endDate ?: new DateTimeImmutable(),
            (int) ($data['max_capacity'] ?? 0)
        );
        
        if (isset($data['id'])) {
            $event->setId((int) $data['id']);
        }
        
        if (isset($data['current_bookings'])) {
            $event->setCurrentBookings((int) $data['current_bookings']);
        }
        
        if (isset($data['is_active'])) {
            $event->setActive((bool) $data['is_active']);
        }
        
        return $event;
    }
}










