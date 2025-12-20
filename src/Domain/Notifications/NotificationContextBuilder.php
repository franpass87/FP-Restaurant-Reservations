<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use FP\Resv\Domain\Settings\Options;
use function substr;

/**
 * Costruisce il contesto per le notifiche.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class NotificationContextBuilder
{
    public function __construct(
        private readonly Options $options
    ) {
    }

    /**
     * Costruisce il contesto per una notifica.
     *
     * @param array<string, mixed> $reservation
     * @return array<string, mixed>
     */
    public function build(int $reservationId, array $reservation, string $manageUrl): array
    {
        $time = (string) ($reservation['time'] ?? '');
        if ($time !== '') {
            $time = substr($time, 0, 5);
        }

        $restaurantName = $this->restaurantName();

        return [
            'id'         => $reservationId,
            'status'     => (string) ($reservation['status'] ?? ''),
            'date'       => (string) ($reservation['date'] ?? ''),
            'time'       => $time,
            'party'      => isset($reservation['party']) ? (int) $reservation['party'] : '',
            'language'   => (string) ($reservation['customer_lang'] ?? ''),
            'manage_url' => $manageUrl,
            'customer'   => [
                'first_name' => (string) ($reservation['first_name'] ?? ''),
                'last_name'  => (string) ($reservation['last_name'] ?? ''),
            ],
            'restaurant' => [
                'name' => $restaurantName,
            ],
        ];
    }

    /**
     * Ottiene il nome del ristorante.
     */
    private function restaurantName(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_name' => '',
        ]);

        return (string) ($general['restaurant_name'] ?? '');
    }
}















