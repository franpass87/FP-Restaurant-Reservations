<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Domain\Diagnostics\Logger as DiagnosticsLogger;
use Throwable;
use function error_log;
use function is_string;
use function wp_json_encode;

final class Logging
{
    public static function log(string $channel, string $message, array $context = []): void
    {
        $container = ServiceContainer::getInstance();
        $logger    = $container->get(DiagnosticsLogger::class);

        if ($logger instanceof DiagnosticsLogger) {
            try {
                $logger->log($channel, $message, $context);
            } catch (Throwable $exception) {
                error_log(self::formatFallback($channel, $message, $context, $exception));
            }
        } else {
            error_log(self::formatFallback($channel, $message, $context));
        }

        do_action('fp_resv_log', $channel, $message, $context);
    }

    private static function formatFallback(string $channel, string $message, array $context, ?Throwable $exception = null): string
    {
        $payload = [
            'channel' => $channel,
            'message' => $message,
            'context' => $context,
        ];

        if ($exception !== null) {
            $payload['error'] = $exception->getMessage();
        }

        $encoded = wp_json_encode($payload);

        return is_string($encoded) ? '[fp-resv] ' . $encoded : '[fp-resv] ' . $channel . ': ' . $message;
    }
}
