<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\StripeService as StripePayments;
use FP\Resv\Domain\Reservations\ManageTokens;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Core\Consent;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\ICS;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Mailer;
use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use Throwable;
use RuntimeException;
use InvalidArgumentException;
use function absint;
use function current_time;
use function add_query_arg;
use function apply_filters;
use function __;
use function array_filter;
use function array_map;
use function do_action;
use function esc_url_raw;
use function esc_html;
use function file_exists;
use function get_bloginfo;
use function implode;
use function filter_var;
use function gmdate;
use function hash_hmac;
use function home_url;
use function in_array;
use function is_bool;
use function is_array;
use function is_string;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function preg_match;
use function ob_get_clean;
use function ob_start;
use function str_replace;
use function strtolower;
use function strtoupper;
use function substr;
use function sprintf;
use function trailingslashit;
use function trim;
use function wp_parse_args;
use function wp_json_encode;
use function wp_salt;
use const FILTER_VALIDATE_EMAIL;

final class Service
{
    public const ALLOWED_STATUSES = [
        'pending',
        'pending_payment',
        'confirmed',
        'seated',
        'waitlist',
        'cancelled',
        'no-show',
        'visited',
    ];
    public function __construct(
        private readonly Repository $repository,
        private readonly Options $options,
        private readonly Language $language,
        private readonly Mailer $mailer,
        private readonly CustomersRepository $customers,
        private readonly StripePayments $stripe,
        private readonly TablesRepository $tables,
        private readonly Availability $availability,
        private readonly ?GoogleCalendarService $calendar = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $sanitized = $this->sanitizePayload($payload);
        $this->assertPayload($sanitized);

        $consentMeta = Consent::metadata();
        $policyVersion = $sanitized['policy_version'] !== ''
            ? $sanitized['policy_version']
            : ($consentMeta['version'] ?? $this->resolvePolicyVersion());
        if (!is_string($policyVersion) || trim($policyVersion) === '') {
            $policyVersion = $this->resolvePolicyVersion();
        }

        $sanitized['policy_version'] = (string) $policyVersion;

        if (($consentMeta['updated_at'] ?? 0) > 0) {
            $sanitized['consent_timestamp'] = gmdate('Y-m-d H:i:s', (int) $consentMeta['updated_at']);
        }

        if ($sanitized['consent_timestamp'] === '') {
            $sanitized['consent_timestamp'] = current_time('mysql');
        }

        $attribution = DataLayer::attribution();
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'msclkid', 'ttclid'] as $utmKey) {
            if ($sanitized[$utmKey] === '' && isset($attribution[$utmKey])) {
                $sanitized[$utmKey] = $attribution[$utmKey];
            }
        }

        $status = $this->resolveDefaultStatus();
        if (is_string($sanitized['status']) && $sanitized['status'] !== '') {
            $candidate = strtolower($sanitized['status']);
            if (in_array($candidate, self::ALLOWED_STATUSES, true)) {
                $status = $candidate;
            }
        }

        $requiresPayment = $this->stripe->shouldRequireReservationPayment($sanitized);
        if ($requiresPayment && ($sanitized['status'] === null || $sanitized['status'] === '' || $status === 'pending')) {
            $status = 'pending_payment';
        }

        $this->guardCalendarConflicts($sanitized['date'], $sanitized['time'], $status);

        if ($sanitized['room_id'] !== null) {
            $room = $this->tables->findRoom((int) $sanitized['room_id']);
            if ($room === null) {
                throw new RuntimeException(__('La sala selezionata non è valida.', 'fp-restaurant-reservations'));
            }
        }

        if ($sanitized['table_id'] !== null) {
            $table = $this->tables->findTable((int) $sanitized['table_id']);
            if ($table === null) {
                throw new RuntimeException(__('Il tavolo selezionato non è valido.', 'fp-restaurant-reservations'));
            }

            $tableRoomId = (int) ($table['room_id'] ?? 0);
            if ($tableRoomId > 0) {
                if ($sanitized['room_id'] !== null && $tableRoomId !== (int) $sanitized['room_id']) {
                    throw new RuntimeException(__('Il tavolo selezionato appartiene a un\'altra sala.', 'fp-restaurant-reservations'));
                }

                if ($sanitized['room_id'] === null) {
                    $sanitized['room_id'] = $tableRoomId;
                }
            }
        }

        $customerId = $this->customers->upsert($sanitized['email'], [
            'first_name' => $sanitized['first_name'],
            'last_name'  => $sanitized['last_name'],
            'phone'      => $sanitized['phone'],
            'phone_e164' => $sanitized['phone_e164'],
            'phone_country' => $sanitized['phone_country'],
            'phone_national'=> $sanitized['phone_national'],
            'lang'       => $sanitized['language'] ?: $sanitized['locale'],
            'marketing_consent' => $sanitized['marketing_consent'],
            'profiling_consent' => $sanitized['profiling_consent'],
            'consent_ts'        => $sanitized['consent_timestamp'],
            'consent_version'   => $sanitized['policy_version'],
        ]);

        $reservationTime = $sanitized['time'] . ':00';

        if ($this->repository->hasDuplicate($customerId, $sanitized['date'], $reservationTime, $sanitized['location'])) {
            throw new RuntimeException(__('Esiste già una prenotazione per questo cliente nello stesso orario.', 'fp-restaurant-reservations'));
        }

        $this->assertSlotAvailable($sanitized, $status);

        $reservationData = [
            'status'    => $status,
            'date'      => $sanitized['date'],
            'time'      => $reservationTime,
            'party'     => $sanitized['party'],
            'notes'     => $sanitized['notes'],
            'allergies' => $sanitized['allergies'],
            'utm_source'   => $sanitized['utm_source'],
            'utm_medium'   => $sanitized['utm_medium'],
            'utm_campaign' => $sanitized['utm_campaign'],
            'utm_content'  => $sanitized['utm_content'],
            'utm_term'     => $sanitized['utm_term'],
            'gclid'        => $sanitized['gclid'],
            'fbclid'       => $sanitized['fbclid'],
            'msclkid'      => $sanitized['msclkid'],
            'ttclid'       => $sanitized['ttclid'],
            'lang'         => $sanitized['language'],
            'location_id'  => $sanitized['location'],
            'value'        => $sanitized['value'],
            'currency'     => $sanitized['currency'],
            'price_per_person' => $sanitized['price_per_person'],
            'customer_id'  => $customerId,
            'room_id'      => $sanitized['room_id'],
            'table_id'     => $sanitized['table_id'],
        ];

        $reservationId = $this->repository->insert($reservationData);

        $reservation = $this->repository->find($reservationId);
        if ($reservation === null) {
            throw new RuntimeException(__('Prenotazione creata ma non recuperabile.', 'fp-restaurant-reservations'));
        }

        $manageUrl = $this->generateManageUrl($reservationId, $sanitized['email']);

        $paymentPayload = null;
        if ($requiresPayment) {
            try {
                $intentData = $this->stripe->createReservationIntent(
                    $reservationId,
                    array_merge($sanitized, ['id' => $reservationId])
                );

                $paymentPayload = array_merge([
                    'required'         => true,
                    'capture_strategy' => $this->stripe->captureStrategy(),
                    'publishable_key'  => $this->stripe->publishableKey(),
                ], $intentData);
            } catch (Throwable $exception) {
                Logging::log('payments', 'Failed to create Stripe payment intent', [
                    'reservation_id' => $reservationId,
                    'error'          => $exception->getMessage(),
                ]);

                $paymentPayload = [
                    'required' => true,
                    'status'   => 'error',
                    'message'  => __('Impossibile avviare il pagamento. Ti contatteremo a breve per completare la prenotazione.', 'fp-restaurant-reservations'),
                    'publishable_key' => $this->stripe->publishableKey(),
                ];

                if ($status === 'pending_payment') {
                    $status = 'pending';
                    $this->repository->update($reservationId, ['status' => $status]);
                }
            }
        }

        $auditPayload = wp_json_encode([
            'status' => $status,
            'date'   => $sanitized['date'],
            'time'   => $sanitized['time'],
            'party'  => $sanitized['party'],
        ]);

        $this->repository->logAudit([
            'entity_id'   => $reservationId,
            'after_json'  => is_string($auditPayload) ? $auditPayload : null,
            'ip'          => Helpers::clientIp(),
        ]);

        $this->sendCustomerEmail($sanitized, $reservationId, $manageUrl, $status);
        $this->sendStaffNotifications($sanitized, $reservationId, $manageUrl, $status, $reservation);

        do_action('fp_resv_reservation_created', $reservationId, $sanitized, $reservation, $manageUrl);

        $result = [
            'id'         => $reservationId,
            'status'     => $status,
            'manage_url' => $manageUrl,
        ];

        if ($paymentPayload !== null) {
            $result['payment'] = $paymentPayload;
        }

        return $result;
    }

    private function guardCalendarConflicts(string $date, string $time, string $status): void
    {
        if ($this->calendar === null || !$this->calendar->shouldBlockOnBusy()) {
            return;
        }

        if (!in_array($status, ['confirmed', 'pending_payment'], true)) {
            return;
        }

        if ($this->calendar->isWindowBusy($date, $time)) {
            throw new RuntimeException(
                __('Lo slot selezionato risulta occupato su Google Calendar. Scegli un altro orario.', 'fp-restaurant-reservations')
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertSlotAvailable(array $payload, string $status): void
    {
        if (!in_array($status, ['pending', 'pending_payment', 'confirmed', 'seated'], true)) {
            return;
        }

        $criteria = [
            'date'  => $payload['date'],
            'party' => (int) $payload['party'],
        ];

        if (is_string($payload['meal']) && $payload['meal'] !== '') {
            $criteria['meal'] = $payload['meal'];
        }

        if ($payload['room_id'] !== null) {
            $criteria['room'] = (int) $payload['room_id'];
        }

        if (is_string($payload['location']) && $payload['location'] !== '') {
            $criteria['location'] = $payload['location'];
        }

        try {
            $result = $this->availability->findSlots($criteria);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(
                __('Impossibile verificare la disponibilità per lo slot selezionato.', 'fp-restaurant-reservations'),
                0,
                $exception
            );
        }

        $targetLabel = substr((string) $payload['time'], 0, 5);
        $slots       = $result['slots'] ?? [];

        if (!is_array($slots)) {
            $slots = [];
        }

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $label = (string) ($slot['label'] ?? '');
            if ($label !== $targetLabel) {
                continue;
            }

            $slotStatus = (string) ($slot['status'] ?? '');
            if (!in_array($slotStatus, ['blocked', 'full'], true)) {
                return;
            }

            $message = __('Il turno selezionato non è più disponibile. Scegli un altro orario.', 'fp-restaurant-reservations');
            $reasons = $slot['reasons'] ?? [];
            if (is_array($reasons) && $reasons !== []) {
                $firstReason = $reasons[0];
                if (is_string($firstReason) && $firstReason !== '') {
                    $message = $firstReason;
                }
            }

            throw new RuntimeException($message);
        }

        throw new RuntimeException(__('Il turno selezionato non è disponibile.', 'fp-restaurant-reservations'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sanitizePayload(array $payload): array
    {
        $defaults = [
            'date'        => gmdate('Y-m-d'),
            'time'        => '19:00',
            'party'       => 2,
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'phone'       => '',
            'phone_e164'  => '',
            'phone_country' => '',
            'phone_national'=> '',
            'notes'       => '',
            'allergies'   => '',
            'meal'        => '',
            'language'    => 'it',
            'locale'      => 'it_IT',
            'location'    => 'default',
            'currency'    => $this->resolveDefaultCurrency(),
            'utm_source'  => '',
            'utm_medium'  => '',
            'utm_campaign'=> '',
            'utm_content' => '',
            'utm_term'    => '',
            'gclid'       => '',
            'fbclid'      => '',
            'msclkid'     => '',
            'ttclid'      => '',
            'marketing_consent' => false,
            'profiling_consent' => false,
            'policy_version'    => '',
            'consent_timestamp' => '',
            'status'      => null,
            'room_id'     => null,
            'table_id'    => null,
            'value'       => null,
            'price_per_person' => null,
        ];

        $payload = array_merge($defaults, $payload);

        $payload['date']       = sanitize_text_field((string) $payload['date']);
        $payload['time']       = substr(sanitize_text_field((string) $payload['time']), 0, 5);
        $payload['party']      = max(1, absint($payload['party']));
        $payload['first_name'] = sanitize_text_field((string) $payload['first_name']);
        $payload['last_name']  = sanitize_text_field((string) $payload['last_name']);
        $payload['email']      = sanitize_email((string) $payload['email']);
        $payload['phone']      = sanitize_text_field((string) $payload['phone']);
        $payload['phone_e164'] = sanitize_text_field((string) $payload['phone_e164']);
        $payload['phone_country'] = sanitize_text_field((string) $payload['phone_country']);
        $payload['phone_national'] = sanitize_text_field((string) $payload['phone_national']);
        $payload['notes']      = sanitize_textarea_field((string) $payload['notes']);
        $payload['allergies']  = sanitize_textarea_field((string) $payload['allergies']);
        $payload['meal']       = sanitize_text_field((string) $payload['meal']);
        $payload['language']   = sanitize_text_field((string) $payload['language']);
        $payload['locale']     = sanitize_text_field((string) $payload['locale']);
        $payload['location']   = sanitize_text_field((string) $payload['location']);
        $payload['currency']   = sanitize_text_field((string) $payload['currency']);
        if ($payload['currency'] === '') {
            $payload['currency'] = $this->resolveDefaultCurrency();
        }
        $payload['utm_source'] = sanitize_text_field((string) $payload['utm_source']);
        $payload['utm_medium'] = sanitize_text_field((string) $payload['utm_medium']);
        $payload['utm_campaign'] = sanitize_text_field((string) $payload['utm_campaign']);
        $payload['utm_content'] = sanitize_text_field((string) $payload['utm_content']);
        $payload['utm_term'] = sanitize_text_field((string) $payload['utm_term']);
        $payload['gclid'] = sanitize_text_field((string) $payload['gclid']);
        $payload['fbclid'] = sanitize_text_field((string) $payload['fbclid']);
        $payload['msclkid'] = sanitize_text_field((string) $payload['msclkid']);
        $payload['ttclid'] = sanitize_text_field((string) $payload['ttclid']);
        $payload['status']     = $payload['status'] !== null ? sanitize_text_field((string) $payload['status']) : '';
        $payload['status']     = $payload['status'] !== '' ? strtolower($payload['status']) : null;
        $payload['marketing_consent'] = $this->toBool($payload['marketing_consent']);
        $payload['profiling_consent'] = $this->toBool($payload['profiling_consent']);
        $payload['policy_version']    = sanitize_text_field((string) $payload['policy_version']);
        $payload['consent_timestamp'] = sanitize_text_field((string) $payload['consent_timestamp']);
        $payload['room_id']    = absint((int) $payload['room_id']);
        if ($payload['room_id'] === 0) {
            $payload['room_id'] = null;
        }
        $payload['table_id']   = absint((int) $payload['table_id']);
        if ($payload['table_id'] === 0) {
            $payload['table_id'] = null;
        }

        if (is_array($payload['value'])) {
            $payload['value'] = null;
        } elseif ($payload['value'] === null || $payload['value'] === '') {
            $payload['value'] = null;
        } else {
            $rawValue = is_string($payload['value']) ? str_replace(',', '.', $payload['value']) : (string) $payload['value'];
            $value    = (float) $rawValue;
            $payload['value'] = $value > 0 ? round($value, 2) : null;
        }

        if (is_array($payload['price_per_person'])) {
            $payload['price_per_person'] = null;
        } elseif ($payload['price_per_person'] === null || $payload['price_per_person'] === '') {
            $payload['price_per_person'] = null;
        } else {
            $rawPrice = is_string($payload['price_per_person']) ? str_replace(',', '.', $payload['price_per_person']) : (string) $payload['price_per_person'];
            $price    = (float) $rawPrice;
            $payload['price_per_person'] = $price > 0 ? round($price, 2) : null;
        }

        $payload['language'] = $this->language->ensureLanguage((string) $payload['language']);
        $locale = (string) $payload['locale'];
        if ($locale === '') {
            $locale = $this->language->getFallbackLocale();
        }
        $payload['locale'] = $this->language->normalizeLocale($locale);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertPayload(array $payload): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date'])) {
            throw new RuntimeException(__('La data della prenotazione non è valida.', 'fp-restaurant-reservations'));
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $payload['time'])) {
            throw new RuntimeException(__('L\'orario della prenotazione non è valido.', 'fp-restaurant-reservations'));
        }

        if ($payload['party'] < 1) {
            throw new RuntimeException(__('Il numero di coperti indicato non è valido.', 'fp-restaurant-reservations'));
        }

        if ($payload['first_name'] === '' || $payload['last_name'] === '') {
            throw new RuntimeException(__('È necessario indicare il nome e il cognome del cliente.', 'fp-restaurant-reservations'));
        }

        if ($payload['email'] === '' || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('È necessario fornire un indirizzo email valido.', 'fp-restaurant-reservations'));
        }
    }

    private function resolveDefaultStatus(): string
    {
        $defaults = [
            'default_reservation_status' => 'pending',
            'default_currency'           => 'EUR',
        ];

        $general = $this->options->getGroup('fp_resv_general', $defaults);
        $status  = (string) ($general['default_reservation_status'] ?? 'pending');

        $status = strtolower($status);
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'pending';
        }

        return $status;
    }

    private function resolveDefaultCurrency(): string
    {
        $general = $this->options->getGroup('fp_resv_general', [
            'default_currency' => 'EUR',
        ]);

        $currency = strtoupper((string) ($general['default_currency'] ?? 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        return $currency;
    }

    private function generateManageUrl(int $reservationId, string $email): string
    {
        $base = trailingslashit(apply_filters('fp_resv_manage_base_url', home_url('/')));
        $token = $this->generateManageToken($reservationId, $email);

        return esc_url_raw(add_query_arg([
            'fp_resv_manage' => $reservationId,
            'fp_resv_token'  => $token,
        ], $base));
    }

    private function generateManageToken(int $reservationId, string $email): string
    {
        return ManageTokens::issue($reservationId, $email);
    }

    public function verifyManageToken(int $reservationId, string $email, string $token): bool
    {
        return ManageTokens::verify($reservationId, $email, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendStaffNotifications(
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

        $restaurantRecipients = is_array($notifications['restaurant_emails'] ?? null)
            ? array_values(array_filter($notifications['restaurant_emails']))
            : [];
        $webmasterRecipients = is_array($notifications['webmaster_emails'] ?? null)
            ? array_values(array_filter($notifications['webmaster_emails']))
            : [];

        if ($restaurantRecipients === [] && $webmasterRecipients === []) {
            return;
        }

        $general = $this->options->getGroup('fp_resv_general', [
            'restaurant_name'          => get_bloginfo('name'),
            'restaurant_timezone'      => 'Europe/Rome',
            'table_turnover_minutes'   => '120',
        ]);

        $context = $this->buildReservationContext(
            $payload,
            $reservationId,
            $manageUrl,
            $status,
            $reservation,
            $general
        );

        $headers    = $this->buildNotificationHeaders($notifications);
        $icsContent = null;
        if (($notifications['attach_ics'] ?? '0') === '1') {
            $icsContent = $this->generateIcsContent($context);
        }

        $languageCode = $context['language'] ?? $this->language->getDefaultLanguage();
        $emailStrings = $this->language->getStrings($languageCode);
        $staffCopy    = is_array($emailStrings['emails']['staff'] ?? null) ? $emailStrings['emails']['staff'] : [];

        if ($restaurantRecipients !== []) {
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
                $message = $this->fallbackStaffMessage($context, $staffCopy);
            }

            $subject = apply_filters('fp_resv_restaurant_email_subject', $subject, $context);
            $message = apply_filters('fp_resv_restaurant_email_message', $message, $context);

            $this->mailer->send(
                implode(',', $restaurantRecipients),
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

        if ($webmasterRecipients !== []) {
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
                $message = $this->fallbackStaffMessage($context, $staffCopy);
            }

            $subject = apply_filters('fp_resv_webmaster_email_subject', $subject, $context);
            $message = apply_filters('fp_resv_webmaster_email_message', $message, $context);

            $this->mailer->send(
                implode(',', $webmasterRecipients),
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
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendCustomerEmail(array $payload, int $reservationId, string $manageUrl, string $status): void
    {
        $notifications = $this->options->getGroup('fp_resv_notifications', [
            'sender_name'  => get_bloginfo('name'),
            'sender_email' => get_bloginfo('admin_email'),
            'reply_to_email' => '',
        ]);

        $headers = [];
        $senderEmail = (string) ($notifications['sender_email'] ?? '');
        $senderName  = (string) ($notifications['sender_name'] ?? '');
        if ($senderEmail !== '') {
            $from = $senderEmail;
            if ($senderName !== '') {
                $from = sprintf('%s <%s>', $senderName, $senderEmail);
            }

            $headers[] = 'From: ' . $from;
        }

        $replyTo = (string) ($notifications['reply_to_email'] ?? '');
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $languageCode = $payload['language'] !== '' ? $payload['language'] : $this->language->getDefaultLanguage();
        $strings       = $this->language->getStrings($languageCode);
        $customerCopy  = is_array($strings['emails']['customer'] ?? null) ? $strings['emails']['customer'] : [];

        $subjectTemplate = (string) ($customerCopy['subject'] ?? __('La tua prenotazione per %s', 'fp-restaurant-reservations'));
        $formattedDate   = $this->language->formatDate($payload['date'], $languageCode);
        $formattedTime   = $this->language->formatTime($payload['time'], $languageCode);
        $statusLabel     = $this->statusLabel($status, $languageCode);

        $subject = sprintf($subjectTemplate, $formattedDate);

        $lines = [];
        $lines[] = sprintf(
            (string) ($customerCopy['intro'] ?? __('Ciao %1$s %2$s,', 'fp-restaurant-reservations')),
            $payload['first_name'],
            $payload['last_name']
        );
        $lines[] = '';
        $lines[] = sprintf(
            (string) ($customerCopy['body'] ?? __('Grazie per aver prenotato per %1$d persone il %2$s alle %3$s.', 'fp-restaurant-reservations')),
            $payload['party'],
            $formattedDate,
            $formattedTime
        );
        $lines[] = sprintf(
            (string) ($customerCopy['status'] ?? __('Stato della prenotazione: %s.', 'fp-restaurant-reservations')),
            $statusLabel
        );
        $lines[] = '';
        $lines[] = sprintf(
            (string) ($customerCopy['manage'] ?? __('Gestisci la tua prenotazione qui: %s', 'fp-restaurant-reservations')),
            $manageUrl
        );
        if (!empty($customerCopy['outro'])) {
            $lines[] = '';
            $lines[] = (string) $customerCopy['outro'];
        }

        $message = implode("\n", $lines);

        $message = apply_filters('fp_resv_customer_email_message', $message, $payload, $reservationId, $manageUrl, $status);
        $subject = apply_filters('fp_resv_customer_email_subject', $subject, $payload, $reservationId, $manageUrl, $status);

        $sent = $this->mailer->send(
            $payload['email'],
            $subject,
            $message,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'customer_confirmation',
                'content_type'   => 'text/plain',
            ]
        );
        if (!$sent) {
            Logging::log('mail', 'Failed to send reservation email', [
                'reservation_id' => $reservationId,
                'email'          => $payload['email'],
            ]);
        }
    }

    private function statusLabel(string $status, string $language): string
    {
        return $this->language->statusLabel($status, $language);
    }

    /**
     * @param array<string, mixed> $notifications
     *
     * @return array<int, string>
     */
    private function buildNotificationHeaders(array $notifications): array
    {
        $headers     = [];
        $senderEmail = (string) ($notifications['sender_email'] ?? '');
        $senderName  = (string) ($notifications['sender_name'] ?? '');

        if ($senderEmail !== '') {
            $from = $senderEmail;
            if ($senderName !== '') {
                $from = sprintf('%s <%s>', $senderName, $senderEmail);
            }

            $headers[] = 'From: ' . $from;
        }

        $replyTo = (string) ($notifications['reply_to_email'] ?? '');
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $variables
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

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $general
     *
     * @return array<string, mixed>
     */
    private function buildReservationContext(
        array $payload,
        int $reservationId,
        string $manageUrl,
        string $status,
        Models\Reservation $reservation,
        array $general
    ): array {
        $general = wp_parse_args($general, [
            'restaurant_name'        => get_bloginfo('name'),
            'restaurant_timezone'    => 'Europe/Rome',
            'table_turnover_minutes' => '120',
        ]);

        $timezone = (string) ($general['restaurant_timezone'] ?? 'Europe/Rome');
        if ($timezone === '') {
            $timezone = 'Europe/Rome';
        }

        $turnover = (int) ($general['table_turnover_minutes'] ?? 120);
        if ($turnover <= 0) {
            $turnover = 120;
        }

        $languageCode = $payload['language'] !== '' ? $payload['language'] : $this->language->getDefaultLanguage();

        $context = [
            'id'            => $reservationId,
            'status'        => $status,
            'status_label'  => $this->statusLabel($status, $languageCode),
            'date'          => $payload['date'],
            'time'          => $payload['time'],
            'party'         => $payload['party'],
            'manage_url'    => $manageUrl,
            'notes'         => $payload['notes'],
            'allergies'     => $payload['allergies'],
            'language'      => $payload['language'],
            'locale'        => $payload['locale'],
            'location'      => $payload['location'],
            'currency'      => $payload['currency'],
            'room_id'       => $payload['room_id'],
            'table_id'      => $payload['table_id'],
            'created_at'    => $reservation->created,
            'utm'           => [
                'source'   => $payload['utm_source'],
                'medium'   => $payload['utm_medium'],
                'campaign' => $payload['utm_campaign'],
            ],
            'customer'      => [
                'first_name' => $payload['first_name'],
                'last_name'  => $payload['last_name'],
                'email'      => $payload['email'],
                'phone'      => $payload['phone'],
            ],
            'restaurant'    => [
                'name'             => (string) ($general['restaurant_name'] ?? get_bloginfo('name')),
                'timezone'         => $timezone,
                'turnover_minutes' => $turnover,
            ],
        ];

        $context['date_formatted'] = $this->language->formatDate($payload['date'], $languageCode);
        $context['time_formatted'] = $this->language->formatTime($payload['time'], $languageCode);
        $context['datetime_formatted'] = $this->language->formatDateTime(
            $payload['date'],
            $payload['time'],
            $languageCode,
            $timezone
        );

        if ($reservation->created instanceof DateTimeImmutable) {
            $context['created_at_formatted'] = $this->language->formatDateTimeObject(
                $reservation->created,
                $languageCode,
                $timezone
            );
        } else {
            $context['created_at_formatted'] = '';
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function generateIcsContent(array $context): ?string
    {
        try {
            $timezone = new DateTimeZone((string) ($context['restaurant']['timezone'] ?? 'Europe/Rome'));
        } catch (\Exception $exception) {
            $timezone = new DateTimeZone('Europe/Rome');
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $context['date'] . ' ' . $context['time'], $timezone);
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }

        $turnover = (int) ($context['restaurant']['turnover_minutes'] ?? 120);
        if ($turnover <= 0) {
            $turnover = 120;
        }

        $end = $dateTime->modify('+' . $turnover . ' minutes');

        $summary = sprintf(
            /* translators: 1: customer name, 2: party size */
            __('Prenotazione %1$s (%2$d persone)', 'fp-restaurant-reservations'),
            trim($context['customer']['first_name'] . ' ' . $context['customer']['last_name']),
            (int) $context['party']
        );

        $descriptionLines = [
            sprintf(__('Cliente: %s', 'fp-restaurant-reservations'), $context['customer']['email']),
            sprintf(__('Telefono: %s', 'fp-restaurant-reservations'), $context['customer']['phone'] ?: '—'),
            sprintf(__('Note: %s', 'fp-restaurant-reservations'), $context['notes'] ?: '—'),
            sprintf(__('Allergie: %s', 'fp-restaurant-reservations'), $context['allergies'] ?: '—'),
            $context['manage_url'],
        ];

        $icsData = [
            'start'       => $dateTime,
            'end'         => $end,
            'timezone'    => $timezone->getName(),
            'summary'     => $summary,
            'description' => implode("\n", $descriptionLines),
            'location'    => (string) ($context['restaurant']['name'] ?? ''),
            'organizer'   => 'MAILTO:' . $context['customer']['email'],
        ];

        $icsData = apply_filters('fp_resv_staff_ics_payload', $icsData, $context);

        return ICS::generate($icsData);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function fallbackStaffMessage(array $context, array $staffCopy = []): string
    {
        $fallback = is_array($staffCopy['fallback'] ?? null) ? $staffCopy['fallback'] : [];

        $lines = [
            sprintf(
                (string) ($fallback['reservation'] ?? __('Prenotazione #%d', 'fp-restaurant-reservations')),
                $context['id']
            ),
            sprintf(
                (string) ($fallback['date_time'] ?? __('Data: %s alle %s', 'fp-restaurant-reservations')),
                $context['date_formatted'] ?? $context['date'],
                $context['time_formatted'] ?? $context['time']
            ),
            sprintf(
                (string) ($fallback['party'] ?? __('Coperti: %d', 'fp-restaurant-reservations')),
                (int) $context['party']
            ),
            sprintf(
                (string) ($fallback['customer'] ?? __('Cliente: %s %s', 'fp-restaurant-reservations')),
                $context['customer']['first_name'],
                $context['customer']['last_name']
            ),
            sprintf(
                (string) ($fallback['email'] ?? __('Email: %s', 'fp-restaurant-reservations')),
                $context['customer']['email']
            ),
        ];

        if ($context['customer']['phone'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['phone'] ?? __('Telefono: %s', 'fp-restaurant-reservations')),
                $context['customer']['phone']
            );
        }

        if ($context['notes'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['notes'] ?? __('Note: %s', 'fp-restaurant-reservations')),
                $context['notes']
            );
        }

        if ($context['allergies'] !== '') {
            $lines[] = sprintf(
                (string) ($fallback['allergies'] ?? __('Allergie: %s', 'fp-restaurant-reservations')),
                $context['allergies']
            );
        }

        $lines[] = sprintf(
            (string) ($fallback['manage'] ?? __('Gestione: %s', 'fp-restaurant-reservations')),
            $context['manage_url']
        );

        return '<p>' . implode('</p><p>', array_map('esc_html', $lines)) . '</p>';
    }

    private function resolvePolicyVersion(): string
    {
        $tracking = $this->options->getGroup('fp_resv_tracking', [
            'privacy_policy_version' => '1.0',
        ]);

        $version = trim((string) ($tracking['privacy_policy_version'] ?? '1.0'));
        if ($version === '') {
            $version = '1.0';
        }

        return $version;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
