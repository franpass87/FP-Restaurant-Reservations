<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use FP\Resv\Core\EmailList;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Mailer;
use FP\Resv\Domain\Notifications\ManageUrlGenerator;
use FP\Resv\Domain\Notifications\Settings as NotificationSettings;
use FP\Resv\Domain\Notifications\TemplateRenderer as NotificationTemplateRenderer;
use FP\Resv\Domain\Reservations\Email\EmailContextBuilder;
use FP\Resv\Domain\Reservations\Email\EmailHeadersBuilder;
use FP\Resv\Domain\Reservations\Email\FallbackMessageBuilder;
use FP\Resv\Domain\Reservations\Email\ICSGenerator;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use Throwable;
use function __;
use function add_action;
use function apply_filters;
use function array_diff;
use function array_map;
use function array_values;
use function esc_html;
use function file_exists;
use function get_bloginfo;
use function implode;
use function is_array;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function trim;
use function wp_parse_args;
use function wp_strip_all_tags;
use function wp_timezone_string;

/**
 * Servizio centralizzato per gestione email di prenotazioni.
 * Estratto da Service.php per migliorare modularità e testabilità.
 */
final class EmailService
{
    private bool $hooksRegistered = false;

    public function __construct(
        private readonly Mailer $mailer,
        private readonly Options $options,
        private readonly Language $language,
        private readonly NotificationSettings $notificationSettings,
        private readonly NotificationTemplateRenderer $notificationTemplates,
        private readonly EmailContextBuilder $contextBuilder,
        private readonly EmailHeadersBuilder $headersBuilder,
        private readonly ICSGenerator $icsGenerator,
        private readonly FallbackMessageBuilder $fallbackBuilder
    ) {
    }

    /**
     * Registra hook WordPress per email operative (es. annullamento → staff).
     */
    public function registerHooks(): void
    {
        if ($this->hooksRegistered) {
            return;
        }

        $this->hooksRegistered = true;
        add_action('fp_resv_reservation_status_changed', [$this, 'handleReservationCancelledStaffAlert'], 25, 4);
    }

    /**
     * Se l'opzione è attiva, notifica ristorante e webmaster quando lo stato diventa «annullata».
     *
     * @param array<string, mixed> $entry Riga prenotazione (formato agenda) dopo l'aggiornamento
     */
    public function handleReservationCancelledStaffAlert(
        int $reservationId,
        string $previousStatus,
        string $currentStatus,
        array $entry = []
    ): void {
        if ($currentStatus !== 'cancelled' || $previousStatus === 'cancelled') {
            return;
        }

        $notifications = $this->options->getGroup('fp_resv_notifications', []);
        if (($notifications['notify_on_cancel'] ?? '0') !== '1') {
            return;
        }

        if ($entry === []) {
            return;
        }

        try {
            $this->sendStaffCancellationNotifications($reservationId, $entry, $previousStatus);
        } catch (Throwable $exception) {
            Logging::log('mail', 'ERRORE invio email staff annullamento', [
                'reservation_id' => $reservationId,
                'error'          => $exception->getMessage(),
                'file'           => $exception->getFile(),
                'line'           => $exception->getLine(),
            ]);
        }
    }

    /**
     * Invia email di annullamento a destinatari configurati in Notifiche.
     *
     * @param array<string, mixed> $entry Riga agenda / DB join customers
     */
    public function sendStaffCancellationNotifications(int $reservationId, array $entry, string $previousStatus): void
    {
        $notifications = $this->options->getGroup('fp_resv_notifications', [
            'restaurant_emails' => [],
            'webmaster_emails'  => [],
            'sender_name'       => get_bloginfo('name'),
            'sender_email'      => get_bloginfo('admin_email'),
            'reply_to_email'    => '',
        ]);

        $restaurantRecipients = EmailList::parse($notifications['restaurant_emails'] ?? []);
        $webmasterRecipients  = EmailList::parse($notifications['webmaster_emails'] ?? []);
        $webmasterRecipients  = array_values(array_diff($webmasterRecipients, $restaurantRecipients));

        if ($restaurantRecipients === [] && $webmasterRecipients === []) {
            return;
        }

        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_name'        => get_bloginfo('name'),
            'restaurant_timezone'    => wp_timezone_string(),
            'table_turnover_minutes' => '120',
        ]);

        $payload    = $this->agendaEntryToPayload($entry);
        $customerEmail = $payload['email'] !== '' ? $payload['email'] : (string) ($entry['email'] ?? '');
        $manageUrl  = $customerEmail !== ''
            ? (new ManageUrlGenerator())->generate($reservationId, $customerEmail)
            : '';
        $reservation = ReservationModel::fromArray($entry);

        $context = $this->contextBuilder->build(
            $payload,
            $reservationId,
            $manageUrl,
            'cancelled',
            $reservation,
            $general
        );

        $headers      = $this->headersBuilder->build($notifications);
        $languageCode = $context['language'] !== '' ? (string) $context['language'] : $this->language->getDefaultLanguage();
        $emailStrings = $this->language->getStrings($languageCode);
        $staffCopy    = is_array($emailStrings['emails']['staff'] ?? null) ? $emailStrings['emails']['staff'] : [];

        $restaurantName = (string) ($context['restaurant']['name'] ?? get_bloginfo('name'));
        $prevLabel      = $this->language->statusLabel($previousStatus, $languageCode);

        $subject = sprintf(
            __('Prenotazione #%1$d annullata - %2$s', 'fp-restaurant-reservations'),
            $reservationId,
            $restaurantName
        );
        $subject = apply_filters('fp_resv_staff_cancel_email_subject', $subject, $context, $previousStatus);

        $lead = sprintf(
            __('La prenotazione #%1$d è stata annullata (stato precedente: %2$s).', 'fp-restaurant-reservations'),
            $reservationId,
            $prevLabel
        );
        $body  = '<p style="font-family:Arial,sans-serif;font-size:15px;"><strong>' . esc_html($lead) . '</strong></p>';
        $body .= $this->fallbackBuilder->build($context, $staffCopy);
        $body  = apply_filters('fp_resv_staff_cancel_email_message', $body, $context, $previousStatus);

        if ($restaurantRecipients !== []) {
            $this->mailer->send(
                implode(',', $restaurantRecipients),
                $subject,
                $body,
                $headers,
                [],
                [
                    'reservation_id' => $reservationId,
                    'channel'        => 'restaurant_cancel_notification',
                    'content_type'   => 'text/html',
                ]
            );
        }

        if ($webmasterRecipients !== []) {
            $this->mailer->send(
                implode(',', $webmasterRecipients),
                $subject,
                $body,
                $headers,
                [],
                [
                    'reservation_id' => $reservationId,
                    'channel'        => 'webmaster_cancel_notification',
                    'content_type'   => 'text/html',
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function agendaEntryToPayload(array $entry): array
    {
        return [
            'date'             => (string) ($entry['date'] ?? ''),
            'time'             => (string) ($entry['time'] ?? ''),
            'party'            => (int) ($entry['party'] ?? 0),
            'meal'             => (string) ($entry['meal'] ?? ''),
            'first_name'       => (string) ($entry['first_name'] ?? ''),
            'last_name'        => (string) ($entry['last_name'] ?? ''),
            'email'            => (string) ($entry['email'] ?? ''),
            'phone'            => (string) ($entry['phone'] ?? ''),
            'notes'            => (string) ($entry['notes'] ?? ''),
            'allergies'        => (string) ($entry['allergies'] ?? ''),
            'language'         => (string) ($entry['customer_lang'] ?? $entry['lang'] ?? ''),
            'locale'           => '',
            'location'         => (string) ($entry['location'] ?? ''),
            'currency'         => (string) ($entry['currency'] ?? ''),
            'utm_source'       => (string) ($entry['utm_source'] ?? ''),
            'utm_medium'       => (string) ($entry['utm_medium'] ?? ''),
            'utm_campaign'     => (string) ($entry['utm_campaign'] ?? ''),
            'room_id'          => $entry['room_id'] ?? null,
            'table_id'         => $entry['table_id'] ?? null,
            'high_chair_count' => (int) ($entry['high_chair_count'] ?? 0),
            'wheelchair_table' => (bool) ($entry['wheelchair_table'] ?? false),
            'pets'             => (bool) ($entry['pets'] ?? false),
        ];
    }

    /**
     * Invia email di conferma al cliente.
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @param int $reservationId ID prenotazione
     * @param string $manageUrl URL gestione prenotazione
     * @param string $status Stato prenotazione
     */
    public function sendCustomerEmail(array $payload, int $reservationId, string $manageUrl, string $status): void
    {
        $notifications = $this->options->getGroup('fp_resv_notifications', [
            'sender_name'    => get_bloginfo('name'),
            'sender_email'   => get_bloginfo('admin_email'),
            'reply_to_email' => '',
        ]);

        if ($this->notificationSettings->shouldUseBrevo(NotificationSettings::CHANNEL_CONFIRMATION)) {
            // Brevo viene gestito dal Service principale
            return;
        }

        if (!$this->notificationSettings->shouldUsePlugin(NotificationSettings::CHANNEL_CONFIRMATION)) {
            return;
        }

        $headers = $this->headersBuilder->build($notifications);

        $languageCode = $payload['language'] !== '' ? $payload['language'] : $this->language->getDefaultLanguage();
        $general      = $this->options->getGroup('fp_resv_general', [
            'restaurant_name' => get_bloginfo('name'),
        ]);

        $context = [
            'id'         => $reservationId,
            'status'     => $status,
            'date'       => $payload['date'],
            'time'       => $payload['time'],
            'party'      => $payload['party'],
            'meal'       => $payload['meal'] ?? '',
            'language'   => $languageCode,
            'manage_url' => $manageUrl,
            'customer'   => [
                'first_name' => $payload['first_name'],
                'last_name'  => $payload['last_name'],
            ],
            'restaurant' => [
                'name' => (string) ($general['restaurant_name'] ?? get_bloginfo('name')),
            ],
        ];

        $rendered = $this->notificationTemplates->render('confirmation', $context + [
            'review_url' => '',
        ]);

        $subject     = trim($rendered['subject']);
        $message     = trim($rendered['body']);
        $contentType = 'text/html';

        if ($subject === '' || wp_strip_all_tags($message) === '') {
            $strings      = $this->language->getStrings($languageCode);
            $customerCopy = is_array($strings['emails']['customer'] ?? null) ? $strings['emails']['customer'] : [];

            $subjectTemplate = (string) ($customerCopy['subject'] ?? __('La tua prenotazione per %s', 'fp-restaurant-reservations'));
            $formattedDate   = $this->language->formatDate($payload['date'], $languageCode);
            $formattedTime   = $this->language->formatTime($payload['time'], $languageCode);
            $statusLabel     = $this->language->statusLabel($status, $languageCode);

            $subject = sprintf($subjectTemplate, $formattedDate);

            $lines = [];
            $lines[] = sprintf((string) ($customerCopy['intro'] ?? 'Hi %1$s %2$s,'), $payload['first_name'], $payload['last_name']);
            $lines[] = '';
            $lines[] = sprintf(
                (string) ($customerCopy['body'] ?? 'Thank you for booking for %1$d guests on %2$s at %3$s.'),
                $payload['party'],
                $formattedDate,
                $formattedTime
            );
            $lines[] = sprintf((string) ($customerCopy['status'] ?? 'Reservation status: %s.'), $statusLabel);
            $lines[] = '';
            $lines[] = sprintf((string) ($customerCopy['manage'] ?? 'Manage your reservation here: %s'), $manageUrl);
            if (!empty($customerCopy['outro'])) {
                $lines[] = '';
                $lines[] = (string) $customerCopy['outro'];
            }

            $message     = implode("\n", $lines);
            $contentType = 'text/plain';
        }

        $message = apply_filters('fp_resv_customer_email_message', $message, $payload, $reservationId, $manageUrl, $status);
        $subject = apply_filters('fp_resv_customer_email_subject', $subject, $payload, $reservationId, $manageUrl, $status);

        Logging::log('mail', 'Invio email cliente tramite mailer', [
            'reservation_id' => $reservationId,
            'to' => $payload['email'],
            'subject' => $subject,
            'has_message' => !empty($message),
        ]);

        $sent = $this->mailer->send(
            $payload['email'],
            $subject,
            $message,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'customer_confirmation',
                'content_type'   => $contentType,
            ]
        );

        Logging::log('mail', 'Email cliente inviata', [
            'reservation_id' => $reservationId,
            'sent' => $sent,
        ]);

        if (!$sent) {
            Logging::log('mail', 'Failed to send reservation email', [
                'reservation_id' => $reservationId,
                'email'          => $payload['email'],
            ]);
        }
    }

    /**
     * Invia notifiche email allo staff (ristorante + webmaster).
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @param int $reservationId ID prenotazione
     * @param string $manageUrl URL gestione prenotazione
     * @param string $status Stato prenotazione
     * @param ReservationModel $reservation Modello prenotazione
     */
    public function sendStaffNotifications(
        array $payload,
        int $reservationId,
        string $manageUrl,
        string $status,
        ReservationModel $reservation
    ): void {
        $notifications = $this->options->getGroup('fp_resv_notifications', [
            'restaurant_emails' => [],
            'webmaster_emails'  => [],
            'attach_ics'        => '1',
            'sender_name'       => get_bloginfo('name'),
            'sender_email'      => get_bloginfo('admin_email'),
            'reply_to_email'    => '',
        ]);

        $restaurantRecipients = EmailList::parse($notifications['restaurant_emails'] ?? []);
        $webmasterRecipients  = EmailList::parse($notifications['webmaster_emails'] ?? []);

        // Deduplica: rimuovi dai destinatari webmaster quelli già presenti in restaurant
        $webmasterRecipients = array_values(array_diff($webmasterRecipients, $restaurantRecipients));

        if ($restaurantRecipients === [] && $webmasterRecipients === []) {
            return;
        }

        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_name'          => get_bloginfo('name'),
            'restaurant_timezone'      => wp_timezone_string(),
            'table_turnover_minutes'   => '120',
        ]);

        $context = $this->contextBuilder->build(
            $payload,
            $reservationId,
            $manageUrl,
            $status,
            $reservation,
            $general
        );

        $headers    = $this->headersBuilder->build($notifications);
        $icsContent = null;
        if (($notifications['attach_ics'] ?? '0') === '1') {
            $icsContent = $this->icsGenerator->generate($context);
        }

        $languageCode = $context['language'] ?? $this->language->getDefaultLanguage();
        $emailStrings = $this->language->getStrings($languageCode);
        $staffCopy    = is_array($emailStrings['emails']['staff'] ?? null) ? $emailStrings['emails']['staff'] : [];

        if ($restaurantRecipients !== []) {
            $this->sendRestaurantNotification(
                $restaurantRecipients,
                $reservationId,
                $context,
                $staffCopy,
                $headers,
                $icsContent
            );
        }

        if ($webmasterRecipients !== []) {
            $this->sendWebmasterNotification(
                $webmasterRecipients,
                $reservationId,
                $context,
                $staffCopy,
                $headers,
                $icsContent
            );
        }
    }

    /**
     * Invia notifica al ristorante.
     *
     * @param array<int, string> $recipients Destinatari
     * @param int $reservationId ID prenotazione
     * @param array<string, mixed> $context Contesto prenotazione
     * @param array<string, mixed> $staffCopy Testi email staff
     * @param array<int, string> $headers Headers email
     * @param string|null $icsContent Contenuto ICS
     */
    private function sendRestaurantNotification(
        array $recipients,
        int $reservationId,
        array $context,
        array $staffCopy,
        array $headers,
        ?string $icsContent
    ): void {
        $restaurantSubject = (string) ($staffCopy['restaurant_subject'] ?? __('Nuova prenotazione #%1$d - %2$s', 'fp-restaurant-reservations'));
        $subject = sprintf(
            $restaurantSubject,
            $reservationId,
            $context['restaurant']['name'] ?? get_bloginfo('name')
        );

        $message = $this->renderEmailTemplate('restaurant.html.php', [
            'reservation' => $context,
            'strings'     => $staffCopy,
            'variant'     => 'restaurant',
        ]);

        if ($message === '') {
            $message = $this->fallbackBuilder->build($context, $staffCopy);
        }

        $subject = apply_filters('fp_resv_restaurant_email_subject', $subject, $context);
        $message = apply_filters('fp_resv_restaurant_email_message', $message, $context);

        $this->mailer->send(
            implode(',', $recipients),
            $subject,
            $message,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'restaurant_notification',
                'content_type'   => 'text/html',
                'ics_content'    => $icsContent,
                'ics_filename'   => sprintf('reservation-%d.ics', $reservationId),
            ]
        );
    }

    /**
     * Invia notifica al webmaster.
     *
     * @param array<int, string> $recipients Destinatari
     * @param int $reservationId ID prenotazione
     * @param array<string, mixed> $context Contesto prenotazione
     * @param array<string, mixed> $staffCopy Testi email staff
     * @param array<int, string> $headers Headers email
     * @param string|null $icsContent Contenuto ICS
     */
    private function sendWebmasterNotification(
        array $recipients,
        int $reservationId,
        array $context,
        array $staffCopy,
        array $headers,
        ?string $icsContent
    ): void {
        $webmasterSubject = (string) ($staffCopy['webmaster_subject'] ?? __('Copia webmaster prenotazione #%1$d - %2$s', 'fp-restaurant-reservations'));
        $subject = sprintf(
            $webmasterSubject,
            $reservationId,
            $context['restaurant']['name'] ?? get_bloginfo('name')
        );

        $message = $this->renderEmailTemplate('webmaster.html.php', [
            'reservation' => $context,
            'strings'     => $staffCopy,
            'variant'     => 'webmaster',
        ]);

        if ($message === '') {
            $message = $this->fallbackBuilder->build($context, $staffCopy);
        }

        $subject = apply_filters('fp_resv_webmaster_email_subject', $subject, $context);
        $message = apply_filters('fp_resv_webmaster_email_message', $message, $context);

        $this->mailer->send(
            implode(',', $recipients),
            $subject,
            $message,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'webmaster_notification',
                'content_type'   => 'text/html',
                'ics_content'    => $icsContent,
                'ics_filename'   => sprintf('reservation-%d.ics', $reservationId),
            ]
        );
    }

    /**
     * Costruisce il contesto completo per le email.
     *
     * @param array<string, mixed> $payload Dati prenotazione
     * @param int $reservationId ID prenotazione
     * @param string $manageUrl URL gestione prenotazione
     * @param string $status Stato prenotazione
     * @param ReservationModel $reservation Modello prenotazione
     * @param array<string, mixed> $general Impostazioni generali
     * @return array<string, mixed> Contesto completo
     */
    public function buildReservationContext(
        array $payload,
        int $reservationId,
        string $manageUrl,
        string $status,
        ReservationModel $reservation,
        array $general
    ): array {
        return $this->contextBuilder->build($payload, $reservationId, $manageUrl, $status, $reservation, $general);
    }

    /**
     * Renderizza un template email.
     *
     * @param string $template Nome template
     * @param array<string, mixed> $variables Variabili per il template
     * @return string Contenuto renderizzato
     */
    private function renderEmailTemplate(string $template, array $variables): string
    {
        $path = Helpers::pluginDir() . 'templates/emails/' . ltrim($template, '/');
        if (!file_exists($path)) {
            return '';
        }

        ob_start();
        extract($variables, EXTR_SKIP);
        include $path;

        $output = ob_get_clean();
        if (!is_string($output)) {
            return '';
        }

        return trim($output);
    }

}


