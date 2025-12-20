<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use FP\Resv\Core\QR;
use wpdb;
use function current_time;
use function do_action;
use function sprintf;
use function strtolower;
use function trim;
use function __;

/**
 * Crea ticket per eventi.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class TicketCreator
{
    public function __construct(
        private readonly wpdb $wpdb
    ) {
    }

    /**
     * Crea un ticket per un evento.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $payload
     */
    public function create(
        array $event,
        int $reservationId,
        ?int $customerId,
        array $payload,
        bool $paymentRequired,
        int $index
    ): Ticket {
        $status  = $paymentRequired ? 'pending_payment' : 'confirmed';
        $price   = $event['price'] !== null ? (float) $event['price'] : null;
        $currency = $event['currency'] !== '' ? $event['currency'] : $payload['currency'];

        $data = [
            'event_id'       => $event['id'],
            'reservation_id' => $reservationId,
            'customer_id'    => $customerId,
            'category'       => $payload['category'],
            'price'          => $price,
            'currency'       => $currency,
            'status'         => $status,
            'qr_code_text'   => '',
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        $inserted = $this->wpdb->insert($this->ticketsTable(), $data);
        if ($inserted === false) {
            throw new RuntimeException($this->wpdb->last_error ?: __('Impossibile creare il biglietto.', 'fp-restaurant-reservations'));
        }

        $ticketId = (int) $this->wpdb->insert_id;
        $seed     = sprintf('event:%d|ticket:%d|email:%s|n:%d', $event['id'], $ticketId, strtolower($payload['email']), $index);
        $qr       = QR::encode($seed);

        $this->wpdb->update(
            $this->ticketsTable(),
            ['qr_code_text' => $qr],
            ['id' => $ticketId]
        );

        $ticketRow = [
            'id'             => $ticketId,
            'event_id'       => $event['id'],
            'reservation_id' => $reservationId,
            'customer_id'    => $customerId,
            'status'         => $status,
            'category'       => $payload['category'],
            'price'          => $price,
            'currency'       => $currency,
            'qr_code_text'   => $qr,
            'created_at'     => current_time('mysql'),
            'email'          => $payload['email'],
            'holder_name'    => trim($payload['first_name'] . ' ' . $payload['last_name']),
        ];

        do_action('fp_resv_event_ticket_created', $ticketRow, $event, $payload);

        return Ticket::fromRow($ticketRow);
    }

    /**
     * Ottiene il nome della tabella ticket.
     */
    private function ticketsTable(): string
    {
        return $this->wpdb->prefix . 'fp_tickets';
    }
}















