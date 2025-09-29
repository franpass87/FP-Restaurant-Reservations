<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

final class Ticket
{
    public int $id;
    public int $eventId;
    public ?int $reservationId = null;
    public ?int $customerId = null;
    public string $status = 'pending';
    public string $category = '';
    public ?float $price = null;
    public string $currency = 'EUR';
    public string $qrCode = '';
    public string $email = '';
    public string $holder = '';
    public string $createdAt = '';

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $ticket                = new self();
        $ticket->id            = (int) ($row['id'] ?? 0);
        $ticket->eventId       = (int) ($row['event_id'] ?? 0);
        $ticket->reservationId = isset($row['reservation_id']) ? (int) $row['reservation_id'] : null;
        $ticket->customerId    = isset($row['customer_id']) ? (int) $row['customer_id'] : null;
        $ticket->status        = (string) ($row['status'] ?? 'pending');
        $ticket->category      = (string) ($row['category'] ?? '');
        $ticket->price         = isset($row['price']) ? (float) $row['price'] : null;
        $ticket->currency      = (string) ($row['currency'] ?? 'EUR');
        $ticket->qrCode        = (string) ($row['qr_code_text'] ?? '');
        $ticket->email         = (string) ($row['email'] ?? '');
        $ticket->holder        = (string) ($row['holder_name'] ?? '');
        $ticket->createdAt     = (string) ($row['created_at'] ?? '');

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'event_id'       => $this->eventId,
            'reservation_id' => $this->reservationId,
            'customer_id'    => $this->customerId,
            'status'         => $this->status,
            'category'       => $this->category,
            'price'          => $this->price,
            'currency'       => $this->currency,
            'qr_code'        => $this->qrCode,
            'email'          => $this->email,
            'holder'         => $this->holder,
            'created_at'     => $this->createdAt,
        ];
    }
}
