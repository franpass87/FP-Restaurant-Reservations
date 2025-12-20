<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use function count;
use function explode;
use function filter_var;
use function home_url;
use function is_string;
use function strpos;
use function trim;
use function uniqid;
use const FILTER_VALIDATE_IP;

/**
 * Gestisce l'invio di eventi server-side a GA4 e Meta.
 * Estratto da Manager per migliorare la manutenibilità.
 */
final class ServerSideEventDispatcher
{
    public function __construct(
        private readonly GA4 $ga4,
        private readonly Meta $meta
    ) {
    }

    /**
     * Invia eventi server-side a GA4 e Meta.
     *
     * @param array<string, mixed> $event
     * @param ReservationModel $reservation
     * @param array<string, mixed> $payload
     */
    public function dispatch(array $event, ReservationModel $reservation, array $payload): void
    {
        $eventId = $this->generateEventId();
        $clientId = $this->extractClientId();
        $userData = $this->buildUserData($reservation, $payload);
        $eventSourceUrl = home_url($_SERVER['REQUEST_URI'] ?? '/');

        // Invia a GA4 se configurato
        if ($this->ga4->isServerSideEnabled() && isset($event['ga4'])) {
            $eventName = $event['ga4']['name'] ?? '';
            $params = $event['ga4']['params'] ?? [];
            $params['event_id'] = $eventId;

            $this->ga4->sendEvent($eventName, $params, $clientId);
        }

        // Invia a Meta se configurato
        if ($this->meta->isServerSideEnabled() && isset($event['meta'])) {
            $eventName = $event['meta']['name'] ?? '';
            $customData = $event['meta']['params'] ?? [];

            $this->meta->sendEvent($eventName, $customData, $userData, $eventSourceUrl, $eventId);
        }
    }

    /**
     * Genera un ID univoco per l'evento (per deduplicazione).
     */
    public function generateEventId(): string
    {
        return uniqid('evt_', true);
    }

    /**
     * Estrae il client_id dal cookie GA (_ga).
     */
    private function extractClientId(): string
    {
        if (!isset($_COOKIE['_ga'])) {
            return '';
        }

        $gaCookie = (string) $_COOKIE['_ga'];
        // Il formato del cookie _ga è: GA1.2.XXXXXXXXXX.YYYYYYYYYY
        // Ci interessa la parte XXXXXXXXXX.YYYYYYYYYY
        $parts = explode('.', $gaCookie);
        if (count($parts) >= 4) {
            return $parts[2] . '.' . $parts[3];
        }

        return '';
    }

    /**
     * Costruisce i dati utente per Meta Conversions API.
     *
     * @param ReservationModel $reservation
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildUserData(ReservationModel $reservation, array $payload): array
    {
        $userData = [];

        // Email
        $email = $this->extractEmail($reservation, $payload);
        if ($email !== '') {
            $userData['email'] = trim($email);
        }

        // Phone
        $phone = $this->extractPhone($reservation, $payload);
        if ($phone !== '') {
            $userData['phone'] = trim($phone);
        }

        // IP address
        $clientIp = $this->getClientIp();
        if ($clientIp !== '') {
            $userData['client_ip_address'] = $clientIp;
        }

        // User agent
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $userData['client_user_agent'] = (string) $_SERVER['HTTP_USER_AGENT'];
        }

        // Cookie fbc (Facebook Click ID)
        if (isset($_COOKIE['_fbc'])) {
            $userData['fbc'] = (string) $_COOKIE['_fbc'];
        }

        // Cookie fbp (Facebook Pixel)
        if (isset($_COOKIE['_fbp'])) {
            $userData['fbp'] = (string) $_COOKIE['_fbp'];
        }

        return $userData;
    }

    /**
     * Estrae l'email dalla prenotazione o dal payload.
     *
     * @param ReservationModel $reservation
     * @param array<string, mixed> $payload
     */
    private function extractEmail(ReservationModel $reservation, array $payload): string
    {
        // Try to get email from reservation using getter method
        $email = $reservation->getEmail();
        if (is_string($email) && $email !== '') {
            return $email;
        }
        
        // Fallback to payload
        if (isset($payload['email']) && is_string($payload['email']) && $payload['email'] !== '') {
            return $payload['email'];
        }

        return '';
    }

    /**
     * Estrae il telefono dalla prenotazione o dal payload.
     *
     * @param ReservationModel $reservation
     * @param array<string, mixed> $payload
     */
    private function extractPhone(ReservationModel $reservation, array $payload): string
    {
        // Try to get phone from reservation using getter method
        $phone = $reservation->getPhone();
        if (is_string($phone) && $phone !== '') {
            return $phone;
        }
        
        // Fallback to payload
        if (isset($payload['phone']) && is_string($payload['phone']) && $payload['phone'] !== '') {
            return $payload['phone'];
        }

        return '';
    }

    /**
     * Ottiene l'IP del client gestendo proxy e load balancer.
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!isset($_SERVER[$header])) {
                continue;
            }

            $ip = (string) $_SERVER[$header];
            // Se ci sono più IP, prendi il primo
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }
}















