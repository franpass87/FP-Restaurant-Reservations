<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Logging
{
    public static function log(string $channel, string $message, array $context = []): void
    {
        // @todo Implement logging strategy (database tables / WP logger).
        do_action('fp_resv_log', $channel, $message, $context);
    }
}
