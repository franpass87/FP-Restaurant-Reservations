<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use function add_query_arg;
use function apply_filters;
use function esc_url_raw;
use function hash_hmac;
use function home_url;
use function strtolower;
use function sprintf;
use function trailingslashit;
use function trim;
use function wp_salt;

/**
 * Genera URL e token per la gestione prenotazioni.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class ManageUrlGenerator
{
    /**
     * Genera l'URL per la gestione della prenotazione.
     */
    public function generate(int $reservationId, string $email): string
    {
        $base = trailingslashit(apply_filters('fp_resv_manage_base_url', home_url('/')));
        $token = $this->generateToken($reservationId, $email);

        return esc_url_raw(add_query_arg([
            'fp_resv_manage' => $reservationId,
            'fp_resv_token'  => $token,
        ], $base));
    }

    /**
     * Genera un token per la gestione della prenotazione.
     */
    public function generateToken(int $reservationId, string $email): string
    {
        $email = strtolower(trim($email));
        $data  = sprintf('%d|%s', $reservationId, $email);

        return hash_hmac('sha256', $data, wp_salt('fp_resv_manage'));
    }
}















