<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Models;

use DateTimeImmutable;

/**
 * Reservation Model
 * 
 * Represents a restaurant reservation in the domain layer.
 * This is a pure domain object with no WordPress dependencies.
 *
 * @package FP\Resv\Domain\Reservations\Models
 */
final class Reservation
{
    private ?int $id = null;
    private string $date;
    private string $time;
    private int $party;
    private string $meal;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $phone;
    private string $status;
    private ?string $notes = null;
    private ?string $allergies = null;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;
    
    /**
     * Constructor
     * 
     * @param string $date Reservation date (Y-m-d)
     * @param string $time Reservation time (H:i)
     * @param int $party Number of guests
     * @param string $meal Meal type
     * @param string $firstName Customer first name
     * @param string $lastName Customer last name
     * @param string $email Customer email
     * @param string $phone Customer phone
     * @param string $status Reservation status
     */
    public function __construct(
        string $date,
        string $time,
        int $party,
        string $meal,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $status = 'pending'
    ) {
        $this->date = $date;
        $this->time = $time;
        $this->party = $party;
        $this->meal = $meal;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getDate(): string { return $this->date; }
    public function getTime(): string { return $this->time; }
    public function getParty(): int { return $this->party; }
    public function getMeal(): string { return $this->meal; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getAllergies(): ?string { return $this->allergies; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
    
    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setStatus(string $status): void 
    { 
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setNotes(?string $notes): void 
    { 
        $this->notes = $notes;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setAllergies(?string $allergies): void 
    { 
        $this->allergies = $allergies;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setFirstName(string $firstName): void 
    { 
        $this->firstName = $firstName;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setLastName(string $lastName): void 
    { 
        $this->lastName = $lastName;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setEmail(string $email): void 
    { 
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable();
    }
    public function setPhone(string $phone): void 
    { 
        $this->phone = $phone;
        $this->updatedAt = new DateTimeImmutable();
    }
    
    public function setParty(int $party): void 
    { 
        $this->party = $party;
        $this->updatedAt = new DateTimeImmutable();
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
            'date' => $this->date,
            'time' => $this->time,
            'party' => $this->party,
            'meal' => $this->meal,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'allergies' => $this->allergies,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Create from array (for repository hydration)
     * 
     * @param array<string, mixed> $data Reservation data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        // Handle both snake_case and camelCase keys
        $date = $data['date'] ?? '';
        $time = $data['time'] ?? '';
        $party = (int) ($data['party'] ?? 0);
        $meal = $data['meal'] ?? $data['meal_type'] ?? 'dinner';
        $firstName = $data['first_name'] ?? $data['firstName'] ?? '';
        $lastName = $data['last_name'] ?? $data['lastName'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $status = $data['status'] ?? 'pending';
        
        $reservation = new self(
            $date,
            $time,
            $party,
            $meal,
            $firstName,
            $lastName,
            $email,
            $phone,
            $status
        );
        
        if (isset($data['id'])) {
            $reservation->setId((int) $data['id']);
        }
        
        if (isset($data['notes'])) {
            $reservation->setNotes($data['notes']);
        }
        
        if (isset($data['allergies'])) {
            $reservation->setAllergies($data['allergies']);
        }
        
        if (isset($data['created_at'])) {
            $reservation->createdAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['created_at']);
        } elseif (isset($data['created'])) {
            $reservation->createdAt = $data['created'] instanceof DateTimeImmutable 
                ? $data['created'] 
                : DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $data['created']);
        }
        
        if (isset($data['updated_at'])) {
            $reservation->updatedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['updated_at']);
        }
        
        return $reservation;
    }
}
