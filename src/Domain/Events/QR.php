<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function base64_encode;
use function hash_hmac;
use function implode;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use function wp_salt;

final class QR
{
    public static function encode(string $text): string
    {
        $normalized = strtolower(trim($text));
        $signature  = hash_hmac('sha256', $normalized, wp_salt('fp_resv_qr'));

        $payload = [
            'FPRESV',
            base64_encode($normalized),
            strtoupper(substr($signature, 0, 20)),
        ];

        return implode('|', $payload);
    }
}
