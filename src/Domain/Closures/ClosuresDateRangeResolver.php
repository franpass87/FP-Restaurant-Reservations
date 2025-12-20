<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use WP_REST_Request;
use function sanitize_text_field;
use function wp_timezone;

/**
 * Risolve il range di date dalle richieste REST.
 * Estratto da REST per migliorare la manutenibilità.
 */
final class ClosuresDateRangeResolver
{
    /**
     * Risolve il range di date dalla richiesta.
     *
     * @return array<string, DateTimeImmutable>
     */
    public function resolve(WP_REST_Request $request): array
    {
        $timezone = wp_timezone();
        $start    = $this->parseDateParam($request->get_param('start') ?? $request->get_param('from'), $timezone);
        $end      = $this->parseDateParam($request->get_param('end') ?? $request->get_param('to'), $timezone);

        $body = $request->get_json_params();
        if (is_array($body)) {
            if ($start === null && isset($body['start'])) {
                $start = $this->parseDateParam($body['start'], $timezone);
            }
            if ($end === null && isset($body['end'])) {
                $end = $this->parseDateParam($body['end'], $timezone);
            }
        }

        if ($start === null) {
            $start = new DateTimeImmutable('today', $timezone);
        }

        if ($end === null) {
            $end = $start->add(new DateInterval('P30D'));
        }

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    /**
     * Parsa un parametro data.
     */
    private function parseDateParam(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $value = sanitize_text_field($value);

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value, $timezone);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            return null;
        }
    }
}















