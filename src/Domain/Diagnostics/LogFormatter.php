<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use DateTimeImmutable;
use function __;
use function get_option;
use function sanitize_textarea_field;
use function str_contains;
use function strtolower;
use function trim;
use function ucfirst;
use function wp_date;
use function wp_kses_post;
use function wp_timezone;

/**
 * Gestisce la formattazione dei log per la visualizzazione.
 * Estratto da Service.php per migliorare modularità.
 */
final class LogFormatter
{
    public function normalizeContentType(string $contentType): string
    {
        $normalized = strtolower(trim($contentType));

        if ($normalized === '') {
            return 'text/html';
        }

        if (str_contains($normalized, 'plain')) {
            return 'text/plain';
        }

        if (str_contains($normalized, 'html')) {
            return 'text/html';
        }

        return 'text/html';
    }

    public function emailStatusLabel(string $status): string
    {
        return match ($status) {
            'sent'   => __('Inviata', 'fp-restaurant-reservations'),
            'failed' => __('Errore', 'fp-restaurant-reservations'),
            default  => ucfirst($status),
        };
    }

    public function formatTimestamp(string $timestamp): string
    {
        $timestamp = trim($timestamp);
        if ($timestamp === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp, wp_timezone());
        if (!$date instanceof DateTimeImmutable) {
            try {
                $date = new DateTimeImmutable($timestamp, wp_timezone());
            } catch (\Exception) {
                return $timestamp;
            }
        }

        $time = $date->getTimestamp();
        if ($time <= 0) {
            return $timestamp;
        }

        $dateFormat = (string) get_option('date_format', 'Y-m-d');
        $timeFormat = (string) get_option('time_format', 'H:i');

        return wp_date(trim($dateFormat . ' ' . $timeFormat), $time);
    }

    public function sanitizeBody(string $body, string $contentType): string
    {
        if ($contentType === 'text/html') {
            return wp_kses_post($body);
        }

        return sanitize_textarea_field($body);
    }
}
















