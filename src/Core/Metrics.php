<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use function do_action;
use function microtime;

final class Metrics
{
    /**
     * Record a timing metric in milliseconds.
     * 
     * @param string $metric Metric name (e.g., 'availability.calculation')
     * @param float $duration Duration in seconds (use microtime(true) difference)
     * @param array<string, mixed> $tags Additional metadata
     */
    public static function timing(string $metric, float $duration, array $tags = []): void
    {
        $entry = [
            'type'      => 'timing',
            'metric'    => $metric,
            'value'     => round($duration * 1000, 2), // Convert to milliseconds
            'tags'      => $tags,
            'timestamp' => microtime(true),
        ];

        do_action('fp_resv_metric', $entry);

        if (defined('FP_RESV_METRICS_ENABLED') && FP_RESV_METRICS_ENABLED) {
            self::sendToMonitoring($entry);
        }
    }

    /**
     * Increment a counter metric.
     * 
     * @param string $metric Metric name (e.g., 'reservation.created')
     * @param int $value Increment value (default: 1)
     * @param array<string, mixed> $tags Additional metadata
     */
    public static function increment(string $metric, int $value = 1, array $tags = []): void
    {
        $entry = [
            'type'      => 'counter',
            'metric'    => $metric,
            'value'     => $value,
            'tags'      => $tags,
            'timestamp' => microtime(true),
        ];

        do_action('fp_resv_metric', $entry);

        if (defined('FP_RESV_METRICS_ENABLED') && FP_RESV_METRICS_ENABLED) {
            self::sendToMonitoring($entry);
        }
    }

    /**
     * Record a gauge metric (point-in-time value).
     * 
     * @param string $metric Metric name (e.g., 'availability.slots_available')
     * @param float $value Current value
     * @param array<string, mixed> $tags Additional metadata
     */
    public static function gauge(string $metric, float $value, array $tags = []): void
    {
        $entry = [
            'type'      => 'gauge',
            'metric'    => $metric,
            'value'     => $value,
            'tags'      => $tags,
            'timestamp' => microtime(true),
        ];

        do_action('fp_resv_metric', $entry);

        if (defined('FP_RESV_METRICS_ENABLED') && FP_RESV_METRICS_ENABLED) {
            self::sendToMonitoring($entry);
        }
    }

    /**
     * Start a timer and return a closure to stop it and record the metric.
     * 
     * @param string $metric Metric name
     * @param array<string, mixed> $tags Additional metadata
     * @return callable Closure to call when operation completes
     */
    public static function timer(string $metric, array $tags = []): callable
    {
        $start = microtime(true);

        return static function () use ($metric, $tags, $start): void {
            $duration = microtime(true) - $start;
            self::timing($metric, $duration, $tags);
        };
    }

    /**
     * Send metrics to external monitoring system.
     * Override this method or use the 'fp_resv_metrics_send' filter to integrate with your monitoring.
     * 
     * @param array<string, mixed> $entry
     */
    private static function sendToMonitoring(array $entry): void
    {
        $handler = apply_filters('fp_resv_metrics_handler', null);

        if (is_callable($handler)) {
            $handler($entry);
            return;
        }

        // Default: log to debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            Logging::log('metrics', sprintf(
                '[%s] %s: %s %s',
                $entry['type'],
                $entry['metric'],
                $entry['value'],
                !empty($entry['tags']) ? json_encode($entry['tags']) : ''
            ));
        }
    }
}
