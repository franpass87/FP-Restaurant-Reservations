<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use RuntimeException;
use Throwable;
use function __;

/**
 * Utility centralizzata per gestione errori consistente.
 * Fornisce logging automatico e wrapping di eccezioni.
 */
final class ErrorHandler
{
    /**
     * Gestisce un'eccezione con logging automatico e lancia RuntimeException.
     *
     * @param Throwable $exception Eccezione da gestire
     * @param string $context Contesto dell'errore (es: 'reservations', 'payments')
     * @param string|null $userMessage Messaggio user-friendly (opzionale)
     * @return never
     * @throws RuntimeException
     */
    public static function handle(Throwable $exception, string $context, ?string $userMessage = null): never
    {
        self::logAndThrow($exception, $context, $userMessage ?? $exception->getMessage());
    }

    /**
     * Esegue una funzione con gestione errori automatica.
     *
     * @param callable $fn Funzione da eseguire
     * @param string $context Contesto dell'errore
     * @param string|null $userMessage Messaggio user-friendly in caso di errore
     * @return mixed Risultato della funzione
     * @throws RuntimeException Se la funzione lancia un'eccezione
     */
    public static function wrap(callable $fn, string $context, ?string $userMessage = null): mixed
    {
        try {
            return $fn();
        } catch (Throwable $exception) {
            self::logAndThrow($exception, $context, $userMessage);
        }
    }

    /**
     * Logga un'eccezione e lancia RuntimeException con messaggio user-friendly.
     *
     * @param Throwable $exception Eccezione originale
     * @param string $context Contesto dell'errore
     * @param string $userMessage Messaggio user-friendly
     * @return never
     * @throws RuntimeException
     */
    public static function logAndThrow(Throwable $exception, string $context, string $userMessage): never
    {
        Logging::log($context, 'Errore gestito da ErrorHandler', [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_message' => $userMessage,
        ]);

        throw new RuntimeException($userMessage, 0, $exception);
    }

    /**
     * Logga un'eccezione senza lanciarla (per errori non critici).
     *
     * @param Throwable $exception Eccezione da loggare
     * @param string $context Contesto dell'errore
     * @param string $message Messaggio aggiuntivo
     */
    public static function log(Throwable $exception, string $context, string $message = ''): void
    {
        Logging::log($context, $message !== '' ? $message : 'Errore non critico', [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    /**
     * Converte un'eccezione in un array per risposte API.
     *
     * @param Throwable $exception Eccezione da convertire
     * @param bool $includeTrace Includere stack trace (default: false in produzione)
     * @return array<string, mixed> Array con dettagli errore
     */
    public static function toArray(Throwable $exception, bool $includeTrace = false): array
    {
        $result = [
            'message' => $exception->getMessage(),
            'type' => get_class($exception),
        ];

        if ($includeTrace || (defined('WP_DEBUG') && WP_DEBUG)) {
            $result['file'] = $exception->getFile();
            $result['line'] = $exception->getLine();
            $result['trace'] = explode("\n", $exception->getTraceAsString());
        }

        if ($exception->getPrevious() !== null) {
            $result['previous'] = self::toArray($exception->getPrevious(), $includeTrace);
        }

        return $result;
    }
}
















