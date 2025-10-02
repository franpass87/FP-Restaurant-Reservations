<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function do_action;
use function error_log;
use function function_exists;
use function sprintf;

final class Logging
{
    public static function log(string $channel, string $message, array $context = []): void
    {
        // @todo Implement logging strategy (database tables / WP logger).
        if (!function_exists('do_action')) {
            error_log(sprintf('[fp-resv][%s] %s', $channel, $message));

            return;
        }

        do_action('fp_resv_log', $channel, $message, $context);
    }
}
