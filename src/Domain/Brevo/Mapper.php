<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use function array_filter;
use function strtolower;
use function trim;

final class Mapper
{
    /**
     * @param array<string, mixed> $reservation
     *
     * @return array<string, mixed>
     */
    public function mapReservation(array $reservation): array
    {
        $email = strtolower(trim((string) ($reservation['email'] ?? '')));
        $attributes = [
            'FIRSTNAME'                => $reservation['first_name'] ?? '',
            'LASTNAME'                 => $reservation['last_name'] ?? '',
            'PHONE'                    => $reservation['phone'] ?? '',
            'LANG'                     => $reservation['language'] ?? '',
            'RESERVATION_DATE'         => $reservation['date'] ?? '',
            'RESERVATION_TIME'         => $reservation['time'] ?? '',
            'RESERVATION_PARTY'        => $reservation['party'] ?? null,
            'RESERVATION_STATUS'       => $reservation['status'] ?? '',
            'RESERVATION_LOCATION'     => $reservation['location'] ?? '',
            'RESERVATION_MANAGE_LINK'  => $reservation['manage_url'] ?? '',
            'RESERVATION_UTM_SOURCE'   => $reservation['utm_source'] ?? '',
            'RESERVATION_UTM_MEDIUM'   => $reservation['utm_medium'] ?? '',
            'RESERVATION_UTM_CAMPAIGN' => $reservation['utm_campaign'] ?? '',
        ];

        return [
            'email'      => $email,
            'attributes' => array_filter(
                $attributes,
                static fn ($value): bool => $value !== null && $value !== ''
            ),
        ];
    }
}
