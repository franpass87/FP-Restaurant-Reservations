<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function do_action;
use function error_log;
use function function_exists;
use function has_action;
use function is_string;
use function json_encode;
use function preg_replace;
use function sprintf;
use function trim;
use function var_export;
use function wp_json_encode;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class Logging
{
    public static function log(string $channel, string $message, array $context = []): void
    {
        $dispatched = false;

        if (function_exists('do_action')) {
            do_action('fp_resv_log', $channel, $message, $context);

            $hasListeners = function_exists('has_action')
                ? has_action('fp_resv_log') !== false
                : false;

            $dispatched = $hasListeners;
        }

        if ($dispatched) {
            return;
        }

        error_log(sprintf('[fp-resv][%s] %s%s', $channel, $message, self::formatContext($context)));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $encoded = null;

        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($context, $options);
        }

        if (!is_string($encoded) || $encoded === '') {
            $encoded = json_encode($context, $options);
        }

        if (is_string($encoded) && $encoded !== '' && $encoded !== '[]' && $encoded !== '{}') {
            return ' ' . $encoded;
        }

        $fallback = var_export($context, true);
        $normalized = preg_replace('/\s+/', ' ', trim($fallback));

        if (!is_string($normalized) || $normalized === '') {
            return '';
        }

        return ' ' . $normalized;
    }
}
