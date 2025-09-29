<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Surveys;

use function hash_equals;
use function hash_hmac;
use function strtolower;
use function trim;
use function wp_salt;

final class Token
{
    public static function generate(int $reservationId, string $email): string
    {
        $normalized = strtolower(trim($email));

        return hash_hmac('sha256', $reservationId . '|' . $normalized, wp_salt('fp_resv_survey'));
    }

    public static function verify(int $reservationId, string $email, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return hash_equals(self::generate($reservationId, $email), $token);
    }
}
