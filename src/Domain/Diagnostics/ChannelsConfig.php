<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

/**
 * Configurazione canali diagnostica (email, webhooks, stripe, api, queue).
 * Estratto da Service.php per migliorare la manutenibilità.
 */
final class ChannelsConfig
{
    /**
     * Restituisce la configurazione di tutti i canali diagnostica.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getChannels(): array
    {
        return [
            'email' => [
                'label'       => 'Email',
                'description' => 'Log invio notifiche e ricevute.',
                'statuses'    => [
                    'sent',
                    'failed',
                ],
                'columns'     => [
                    ['key' => 'created_at', 'label' => 'Registrato'],
                    ['key' => 'recipient', 'label' => 'Destinatari'],
                    ['key' => 'subject', 'label' => 'Oggetto'],
                    ['key' => 'status', 'label' => 'Stato'],
                    ['key' => 'excerpt', 'label' => 'Estratto'],
                    ['key' => 'preview', 'label' => 'Anteprima'],
                    ['key' => 'error', 'label' => 'Errore'],
                ],
            ],
            'webhooks' => [
                'label'       => 'Webhook',
                'description' => 'Eventi Brevo, Stripe e Google Calendar.',
                'statuses'    => [
                    'success',
                    'error',
                    'info',
                ],
                'columns'     => [
                    ['key' => 'created_at', 'label' => 'Registrato'],
                    ['key' => 'source', 'label' => 'Sorgente'],
                    ['key' => 'action', 'label' => 'Evento'],
                    ['key' => 'status', 'label' => 'Stato'],
                    ['key' => 'summary', 'label' => 'Dettagli'],
                    ['key' => 'error', 'label' => 'Errore'],
                ],
            ],
            'stripe' => [
                'label'       => 'Stripe',
                'description' => 'Intenti di pagamento, capture e refund.',
                'statuses'    => [
                    'pending',
                    'authorized',
                    'paid',
                    'refunded',
                    'void',
                    'failed',
                ],
                'columns'     => [
                    ['key' => 'created_at', 'label' => 'Registrato'],
                    ['key' => 'type', 'label' => 'Tipo'],
                    ['key' => 'status', 'label' => 'Stato'],
                    ['key' => 'amount', 'label' => 'Importo'],
                    ['key' => 'currency', 'label' => 'Valuta'],
                    ['key' => 'external_id', 'label' => 'Intent / Charge'],
                    ['key' => 'meta', 'label' => 'Dettagli'],
                ],
            ],
            'api' => [
                'label'       => 'API & REST',
                'description' => 'Richieste REST e webhook API con errori 4xx/5xx.',
                'statuses'    => [
                    'info',
                    'warning',
                    'error',
                ],
                'columns'     => [
                    ['key' => 'created_at', 'label' => 'Registrato'],
                    ['key' => 'action', 'label' => 'Azione'],
                    ['key' => 'status', 'label' => 'Severità'],
                    ['key' => 'entity', 'label' => 'Entità'],
                    ['key' => 'actor', 'label' => 'Ruolo'],
                    ['key' => 'ip', 'label' => 'IP'],
                    ['key' => 'details', 'label' => 'Dettagli'],
                ],
            ],
            'queue' => [
                'label'       => 'Cron & Queue',
                'description' => 'Job pianificati e code post-visita.',
                'statuses'    => [
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                ],
                'columns'     => [
                    ['key' => 'updated_at', 'label' => 'Aggiornato'],
                    ['key' => 'run_at', 'label' => 'Esecuzione'],
                    ['key' => 'channel', 'label' => 'Canale'],
                    ['key' => 'status', 'label' => 'Stato'],
                    ['key' => 'reservation_id', 'label' => 'Prenotazione'],
                    ['key' => 'error', 'label' => 'Errore'],
                ],
            ],
        ];
    }
}
