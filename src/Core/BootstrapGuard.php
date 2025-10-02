<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use Throwable;

use function __;
use function add_action;
use function array_key_exists;
use function class_exists;
use function current_time;
use function date;
use function defined;
use function deactivate_plugins;
use function delete_option;
use function esc_html;
use function esc_html__;
use function function_exists;
use function get_option;
use function implode;
use function in_array;
use function is_admin;
use function is_array;
use function is_string;
use function plugin_basename;
use function register_shutdown_function;
use function sprintf;
use function time;
use function update_option;
use function wp_date;
use function error_get_last;

final class BootstrapGuard
{
    private const FAILURE_OPTION = 'fp_resv_last_bootstrap_failure';

    private const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    private static bool $bootstrapInProgress = false;

    private static bool $shutdownRegistered = false;

    private static bool $failureRecorded = false;

    /**
     * @param callable():void $bootstrapper
     */
    public static function run(string $pluginFile, callable $bootstrapper): void
    {
        self::$failureRecorded = false;

        $displayedFailure = self::displayPreviousFailure();
        self::registerShutdownHandler($pluginFile);
        self::$bootstrapInProgress = true;

        try {
            $bootstrapper();
        } catch (Throwable $exception) {
            self::handleFailure($pluginFile, $exception);
        } finally {
            self::$bootstrapInProgress = false;
        }

        if ($displayedFailure && !self::$failureRecorded) {
            self::clearRememberedFailure();
        }
    }

    private static function handleFailure(string $pluginFile, Throwable $exception): void
    {
        self::logFailure($exception);
        self::rememberFailure([
            'type'      => 'exception',
            'class'     => $exception::class,
            'message'   => $exception->getMessage(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'timestamp' => self::timestamp(),
        ]);
        self::deactivatePlugin($pluginFile);
        self::notifyAdmin($exception);
        self::notifyCli($exception);
    }

    private static function logFailure(Throwable $exception): void
    {
        require_once __DIR__ . '/Logging.php';

        Logging::log('bootstrap', 'Plugin bootstrap failed', [
            'exception' => $exception::class,
            'message'   => $exception->getMessage(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTraceAsString(),
        ]);
    }

    private static function rememberFailure(array $payload): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        self::$failureRecorded = true;
        update_option(self::FAILURE_OPTION, $payload, false);
    }

    private static function clearRememberedFailure(): void
    {
        if (!function_exists('delete_option')) {
            return;
        }

        delete_option(self::FAILURE_OPTION);
    }

    private static function deactivatePlugin(string $pluginFile): void
    {
        if (!function_exists('deactivate_plugins') || !function_exists('plugin_basename')) {
            return;
        }

        deactivate_plugins(plugin_basename($pluginFile));
    }

    private static function notifyAdmin(Throwable $exception): void
    {
        if (!function_exists('add_action') || !function_exists('is_admin') || !is_admin()) {
            return;
        }

        $message = sprintf(
            /* translators: 1: Exception message, 2: Exception class, 3: File path, 4: Line number, 5: Help text. */
            __('FP Restaurant Reservations è stato disattivato a causa di un errore critico: %1$s (%2$s in %3$s:%4$d). %5$s', 'fp-restaurant-reservations'),
            $exception->getMessage() !== '' ? $exception->getMessage() : __('errore sconosciuto', 'fp-restaurant-reservations'),
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            __('Controlla il file wp-content/debug.log per i dettagli completi.', 'fp-restaurant-reservations')
        );

        add_action('admin_notices', static function () use ($message): void {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
    }

    private static function notifyCli(Throwable $exception): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\\WP_CLI')) {
            return;
        }

        $message = sprintf(
            __('FP Restaurant Reservations bootstrap fallito: %1$s (%2$s:%3$d)', 'fp-restaurant-reservations'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        \WP_CLI::error($message);
    }

    private static function registerShutdownHandler(string $pluginFile): void
    {
        if (self::$shutdownRegistered || !function_exists('register_shutdown_function')) {
            return;
        }

        self::$shutdownRegistered = true;

        register_shutdown_function(static function () use ($pluginFile): void {
            if (!self::$bootstrapInProgress) {
                return;
            }

            $error = error_get_last();
            if (!is_array($error) || !array_key_exists('type', $error)) {
                return;
            }

            if (!in_array((int) $error['type'], self::FATAL_TYPES, true)) {
                return;
            }

            self::handleFatalError($pluginFile, $error);
        });
    }

    /**
     * @param array<string, mixed> $error
     */
    private static function handleFatalError(string $pluginFile, array $error): void
    {
        $payload = [
            'type'        => 'fatal_error',
            'php_type'    => (int) ($error['type'] ?? 0),
            'message'     => is_string($error['message'] ?? null) ? $error['message'] : '',
            'file'        => is_string($error['file'] ?? null) ? $error['file'] : null,
            'line'        => isset($error['line']) ? (int) $error['line'] : null,
            'timestamp'   => self::timestamp(),
        ];

        self::rememberFailure($payload);
        self::logFatal($payload);
        self::deactivatePlugin($pluginFile);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function logFatal(array $payload): void
    {
        require_once __DIR__ . '/Logging.php';

        $context = $payload;
        $context['source'] = 'fatal_error';

        Logging::log('bootstrap', 'Plugin bootstrap terminated by fatal error', $context);
    }

    private static function displayPreviousFailure(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $failure = get_option(self::FAILURE_OPTION);
        if (!is_array($failure) || !array_key_exists('message', $failure)) {
            return false;
        }

        $displayed = false;

        if (function_exists('is_admin') && is_admin() && function_exists('add_action')) {
            $notice = self::formatFailureNotice($failure);

            add_action('admin_notices', static function () use ($notice): void {
                echo '<div class="notice notice-error"><p>' . esc_html($notice['headline']) . '</p>';

                if ($notice['details'] !== []) {
                    echo '<ul>';
                    foreach ($notice['details'] as $detail) {
                        echo '<li>' . esc_html($detail) . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</div>';
            });

            $displayed = true;
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            $notice = self::formatFailureNotice($failure);
            \WP_CLI::warning($notice['headline'] . ' ' . implode(' ', $notice['details']));

            $displayed = true;
        }

        return $displayed;
    }

    /**
     * @return array{headline: string, details: list<string>}
     */
    private static function formatFailureNotice(array $failure): array
    {
        $timestamp = $failure['timestamp'] ?? null;
        $formattedTime = null;

        if ($timestamp !== null) {
            if (function_exists('wp_date')) {
                $formattedTime = wp_date('Y-m-d H:i:s', (int) $timestamp);
            } else {
                // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                $formattedTime = date('Y-m-d H:i:s', (int) $timestamp);
            }
        }

        $headline = esc_html__(
            'FP Restaurant Reservations non è riuscito ad avviarsi durante l\'ultima esecuzione.',
            'fp-restaurant-reservations'
        );

        $details = [];
        $message = is_string($failure['message'] ?? null) && $failure['message'] !== ''
            ? $failure['message']
            : __('Errore sconosciuto.', 'fp-restaurant-reservations');

        $details[] = sprintf(
            /* translators: 1: Error message, 2: Error class or type, 3: File path, 4: Line number. */
            __('Messaggio: %1$s (%2$s in %3$s:%4$s).', 'fp-restaurant-reservations'),
            $message,
            is_string($failure['class'] ?? null) ? $failure['class'] : self::phpErrorLabel((int) ($failure['php_type'] ?? 0)),
            is_string($failure['file'] ?? null) ? $failure['file'] : __('n/d', 'fp-restaurant-reservations'),
            isset($failure['line']) ? (string) $failure['line'] : __('n/d', 'fp-restaurant-reservations')
        );

        if ($formattedTime !== null) {
            $details[] = sprintf(
                /* translators: %s: Date and time when the error happened. */
                __('Ultimo errore rilevato il %s.', 'fp-restaurant-reservations'),
                $formattedTime
            );
        }

        $details[] = __('Consulta il file wp-content/debug.log per i dettagli completi.', 'fp-restaurant-reservations');

        return [
            'headline' => $headline,
            'details'  => $details,
        ];
    }

    private static function phpErrorLabel(int $type): string
    {
        return match ($type) {
            E_ERROR       => 'E_ERROR',
            E_PARSE       => 'E_PARSE',
            E_CORE_ERROR  => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_USER_ERROR  => 'E_USER_ERROR',
            default       => __('Errore PHP', 'fp-restaurant-reservations'),
        };
    }

    private static function timestamp(): int
    {
        if (function_exists('current_time')) {
            return (int) current_time('timestamp');
        }

        return time();
    }
}
