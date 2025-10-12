<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateTimeImmutable;
use DateTimeZone;
use FP\Resv\Core\Exceptions\ConflictException;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Metrics;
use FP\Resv\Core\PhoneHelper;
use FP\Resv\Core\ReservationValidator;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Payments\StripeService as StripePayments;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Notifications\Settings as NotificationSettings;
use FP\Resv\Domain\Notifications\TemplateRenderer as NotificationTemplateRenderer;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use FP\Resv\Core\Consent;
use FP\Resv\Core\EmailList;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\ICS;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Mailer;
use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use Throwable;
use RuntimeException;
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
use function json_decode;
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
use function preg_replace;
use function ob_get_clean;
use function ob_start;
use function strcmp;
use function strlen;
use function ltrim;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function substr;
use function sprintf;
use function trailingslashit;
use function trim;
use function uksort;
use function wp_parse_args;
use function wp_json_encode;
use function wp_salt;
use const FILTER_VALIDATE_EMAIL;

class Service
{
    public const ALLOWED_STATUSES = ReservationStatuses::ALLOWED_STATUSES;

    /**
     * Stati considerati "attivi" che occupano capacità.
     */
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_AVAILABILITY;

    public function __construct(
        private readonly Repository $repository,
        private readonly Availability $availability,
        private readonly Options $options,
        private readonly Language $language,
        private readonly Mailer $mailer,
        private readonly CustomersRepository $customers,
        private readonly StripePayments $stripe,
        private readonly NotificationSettings $notificationSettings,
        private readonly NotificationTemplateRenderer $notificationTemplates,
        private readonly ?GoogleCalendarService $calendar = null,
        private readonly ?BrevoClient $brevoClient = null,
        private readonly ?\FP\Resv\Domain\Brevo\Repository $brevoRepository = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        Logging::log('reservations', '=== CREAZIONE PRENOTAZIONE START ===', [
            'email' => $payload['email'] ?? '',
            'date' => $payload['date'] ?? '',
            'time' => $payload['time'] ?? '',
            'party' => $payload['party'] ?? 0,
        ]);
        
        $stopTimer = Metrics::timer('reservation.create', [
            'party' => $payload['party'] ?? 0,
        ]);

        $sanitized = $this->sanitizePayload($payload);
        Logging::log('reservations', 'Payload sanitizzato OK');
        
        $this->assertPayload($sanitized);
        Logging::log('reservations', 'Payload validato OK');

        // Controllo anti-duplicati: previene doppi submit della stessa prenotazione
        // Cerca prenotazioni recenti (ultimi 60 secondi) con stessa email, data e ora
        $recentDuplicates = $this->repository->findRecentDuplicates(
            $sanitized['email'],
            $sanitized['date'],
            $sanitized['time'],
            60
        );

        if ($recentDuplicates !== []) {
            $duplicate = $recentDuplicates[0];
            
            Logging::log('reservations', 'Duplicato rilevato: prenotazione già creata pochi secondi fa', [
                'email' => $sanitized['email'],
                'date' => $sanitized['date'],
                'time' => $sanitized['time'],
                'existing_id' => $duplicate['id'] ?? null,
                'existing_created_at' => $duplicate['created_at'] ?? null,
            ]);
            
            Metrics::increment('reservation.duplicate_prevented', 1, [
                'method' => 'recent_duplicate_check',
            ]);
            
            // Restituisce la prenotazione esistente invece di crearne una nuova
            $existingId = (int) ($duplicate['id'] ?? 0);
            $manageUrl = $this->generateManageUrl($existingId, $sanitized['email']);
            
            return [
                'id'         => $existingId,
                'status'     => (string) ($duplicate['status'] ?? 'pending'),
                'manage_url' => $manageUrl,
                'duplicate_prevented' => true,
            ];
        }

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
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid', 'msclkid', 'ttclid'] as $utmKey) {
            if (($sanitized[$utmKey] === '' || $sanitized[$utmKey] === null) && isset($attribution[$utmKey])) {
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

        $customerId = $this->customers->upsert($sanitized['email'], [
            'first_name' => $sanitized['first_name'],
            'last_name'  => $sanitized['last_name'],
            'phone'      => $sanitized['phone'],
            'lang'       => $sanitized['language'] ?: $sanitized['locale'],
            'marketing_consent' => $sanitized['marketing_consent'],
            'profiling_consent' => $sanitized['profiling_consent'],
            'consent_ts'        => $sanitized['consent_timestamp'],
            'consent_version'   => $sanitized['policy_version'],
        ]);

        // Verifica atomica della disponibilità dentro una transazione
        // per prevenire race conditions quando arrivano prenotazioni simultanee
        $this->repository->beginTransaction();
        
        try {
            $this->guardAvailabilityForSlot(
                $sanitized['date'],
                $sanitized['time'],
                $sanitized['party'],
                $sanitized['room_id'],
                $sanitized['meal'],
                $status
            );
            
            Logging::log('reservations', 'Disponibilità verificata OK, procedo con insert');

        // Append extras into notes for staff visibility
        $extrasNoteParts = [];
        if ($sanitized['high_chair_count'] > 0) {
            $extrasNoteParts[] = sprintf(__('Seggioloni: %d', 'fp-restaurant-reservations'), (int) $sanitized['high_chair_count']);
        }
        if ($sanitized['wheelchair_table']) {
            $extrasNoteParts[] = __('Tavolo accessibile per sedia a rotelle', 'fp-restaurant-reservations');
        }
        if ($sanitized['pets']) {
            $extrasNoteParts[] = __('Animali domestici', 'fp-restaurant-reservations');
        }
        if ($extrasNoteParts !== []) {
            $suffix = ' [' . implode('; ', $extrasNoteParts) . ']';
            $sanitized['notes'] = trim(($sanitized['notes'] ?? '') . $suffix);
        }

        $reservationData = [
            'status'    => $status,
            'date'      => $sanitized['date'],
            'time'      => $sanitized['time'] . ':00',
            'party'     => $sanitized['party'],
            'meal'      => $sanitized['meal'],
            'notes'     => $sanitized['notes'],
            'allergies' => $sanitized['allergies'],
            'utm_source'   => $sanitized['utm_source'],
            'utm_medium'   => $sanitized['utm_medium'],
            'utm_campaign' => $sanitized['utm_campaign'],
            'lang'         => $sanitized['language'],
            'location_id'  => $sanitized['location'],
            'value'        => $sanitized['value'],
            'currency'     => $sanitized['currency'],
            'customer_id'  => $customerId,
            'room_id'      => $sanitized['room_id'],
            'table_id'     => $sanitized['table_id'],
            'request_id'   => $sanitized['request_id'],
        ];

            Logging::log('reservations', 'Pronto per INSERT nel database', [
                'date' => $reservationData['date'],
                'time' => $reservationData['time'],
                'party' => $reservationData['party'],
            ]);

            $reservationId = $this->repository->insert($reservationData);

            Logging::log('reservations', '✅ PRENOTAZIONE SALVATA NEL DB', [
                'reservation_id' => $reservationId,
            ]);

            $reservation = $this->repository->find($reservationId);
            if ($reservation === null) {
                Logging::log('reservations', '❌ ERRORE: Prenotazione salvata ma non trovata!', [
                    'reservation_id' => $reservationId,
                ]);
                throw new RuntimeException('Reservation created but could not be retrieved.');
            }
            
            // Commit della transazione solo se tutto è andato a buon fine
            $this->repository->commit();
            
            Logging::log('reservations', '✅ TRANSAZIONE COMMITTATA');
        } catch (Throwable $exception) {
            // Rollback in caso di errore
            $this->repository->rollback();
            
            Logging::log('reservations', 'Errore durante creazione prenotazione, rollback eseguito', [
                'date' => $sanitized['date'],
                'time' => $sanitized['time'],
                'party' => $sanitized['party'],
                'error' => $exception->getMessage(),
            ]);
            
            throw $exception;
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

        // Invio email in un try-catch separato per non bloccare la risposta al cliente
        // anche se l'invio email fallisce (la prenotazione è già salvata)
        try {
            $this->sendCustomerEmail($sanitized, $reservationId, $manageUrl, $status);
        } catch (Throwable $emailException) {
            Logging::log('mail', 'ERRORE invio email cliente', [
                'reservation_id' => $reservationId,
                'error' => $emailException->getMessage(),
                'file' => $emailException->getFile(),
                'line' => $emailException->getLine(),
            ]);
        }
        
        try {
            $this->sendStaffNotifications($sanitized, $reservationId, $manageUrl, $status, $reservation);
        } catch (Throwable $emailException) {
            Logging::log('mail', 'ERRORE invio email staff', [
                'reservation_id' => $reservationId,
                'error' => $emailException->getMessage(),
                'file' => $emailException->getFile(),
                'line' => $emailException->getLine(),
            ]);
        }

        do_action('fp_resv_reservation_created', $reservationId, $sanitized, $reservation, $manageUrl);

        $result = [
            'id'         => $reservationId,
            'status'     => $status,
            'manage_url' => $manageUrl,
        ];

        if ($paymentPayload !== null) {
            $result['payment'] = $paymentPayload;
        }

        Metrics::increment('reservation.created', 1, [
            'status' => $status,
            'requires_payment' => $requiresPayment ? 'yes' : 'no',
        ]);

        $stopTimer();

        Logging::log('reservations', '✅ RETURN result to REST endpoint', [
            'id' => $result['id'] ?? null,
            'status' => $result['status'] ?? null,
            'has_manage_url' => isset($result['manage_url']),
        ]);

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
            throw new ConflictException(
                __('Lo slot selezionato risulta occupato su Google Calendar. Scegli un altro orario.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'status' => $status]
            );
        }
    }

    /**
     * Verifica atomicamente la disponibilità per uno slot specifico.
     * DEVE essere chiamato dentro una transazione database per garantire
     * che il controllo e l'inserimento siano atomici.
     *
     * @param string $date Data nel formato Y-m-d
     * @param string $time Orario nel formato H:i
     * @param int $party Numero di persone
     * @param int|null $roomId Sala richiesta (opzionale)
     * @param string $meal Identificatore del meal plan (pranzo/cena)
     * @param string $status Lo stato della prenotazione che si sta per creare
     * @throws ConflictException Se non c'è disponibilità
     */
    private function guardAvailabilityForSlot(
        string $date,
        string $time,
        int $party,
        ?int $roomId,
        string $meal,
        string $status
    ): void {
        // Skip per stati che non occupano capacità
        if (!in_array($status, self::ACTIVE_STATUSES, true)) {
            return;
        }

        // Calcola la disponibilità per lo slot richiesto
        $criteria = [
            'date'  => $date,
            'party' => $party,
        ];
        
        if ($roomId !== null && $roomId > 0) {
            $criteria['room'] = $roomId;
        }
        
        if ($meal !== '' && $meal !== null) {
            $criteria['meal'] = $meal;
        }

        try {
            $availability = $this->availability->findSlots($criteria);
        } catch (Throwable $exception) {
            Logging::log('reservations', 'Errore durante calcolo disponibilità atomica', [
                'date'  => $date,
                'time'  => $time,
                'party' => $party,
                'meal'  => $meal,
                'error' => $exception->getMessage(),
            ]);
            
            throw new ConflictException(
                __('Impossibile verificare la disponibilità. Riprova tra qualche secondo.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        if (!isset($availability['slots']) || !is_array($availability['slots'])) {
            throw new ConflictException(
                __('Nessuno slot disponibile per la data selezionata.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        // Cerca lo slot specifico richiesto
        $requestedTime = substr($time, 0, 5); // Assicura formato H:i
        $slotFound = false;
        $slotAvailable = false;
        
        // DEBUG: Log dettagliato degli slot disponibili
        $availableSlotLabels = [];
        foreach ($availability['slots'] as $slot) {
            if (is_array($slot) && isset($slot['label'])) {
                $availableSlotLabels[] = $slot['label'] . ' (' . ($slot['status'] ?? 'unknown') . ')';
            }
        }

        foreach ($availability['slots'] as $slot) {
            if (!is_array($slot) || !isset($slot['label'])) {
                continue;
            }

            // Confronta il label dello slot (formato H:i) con l'orario richiesto
            if ($slot['label'] === $requestedTime) {
                $slotFound = true;
                $slotStatus = $slot['status'] ?? 'full';
                
                // Accetta solo slot disponibili o con disponibilità limitata
                if (in_array($slotStatus, ['available', 'limited'], true)) {
                    $slotAvailable = true;
                }
                
                break;
            }
        }

        if (!$slotFound) {
            Logging::log('reservations', 'Slot non trovato durante verifica atomica', [
                'date'           => $date,
                'time'           => $time,
                'requested_time' => $requestedTime,
                'party'          => $party,
                'meal'           => $meal,
                'available_slots'=> count($availability['slots']),
                'available_slot_labels' => $availableSlotLabels,
                'criteria_used' => $criteria,
            ]);
            
            throw new ConflictException(
                __('L\'orario selezionato non è disponibile. Scegli un altro orario.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        if (!$slotAvailable) {
            Logging::log('reservations', 'Slot non disponibile durante verifica atomica', [
                'date'  => $date,
                'time'  => $time,
                'party' => $party,
                'meal'  => $meal,
                'slot_found' => $slotFound,
            ]);
            
            throw new ConflictException(
                __('L\'orario selezionato è ora esaurito. Scegli un altro orario o contattaci.', 'fp-restaurant-reservations'),
                ['date' => $date, 'time' => $time, 'party' => $party]
            );
        }

        Metrics::increment('reservation.availability_check_passed', 1, [
            'date' => $date,
            'time' => $time,
        ]);
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
            'phone_country' => '',
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
            // extras
            'high_chair_count' => 0,
            'wheelchair_table' => false,
            'pets'             => false,
        ];

        $payload = array_merge($defaults, $payload);

        $payload['date']       = sanitize_text_field((string) $payload['date']);
        $payload['time']       = substr(sanitize_text_field((string) $payload['time']), 0, 5);
        $payload['party']      = max(1, absint($payload['party']));
        // Clamp party size to a reasonable upper bound based on settings (default room capacity)
        // This prevents excessively large values from slipping through client-side validation.
        $maxCapacity = (int) $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40');
        if ($maxCapacity > 0) {
            $payload['party'] = min($payload['party'], $maxCapacity);
        }
        $payload['first_name'] = sanitize_text_field((string) $payload['first_name']);
        $payload['last_name']  = sanitize_text_field((string) $payload['last_name']);
        $payload['email']      = sanitize_email((string) $payload['email']);
        $payload['phone']         = sanitize_text_field((string) $payload['phone']);
        $payload['phone_country'] = sanitize_text_field((string) $payload['phone_country']);
        $detectedLanguage         = $this->detectLanguageFromPhone($payload['phone'], $payload['phone_country']);
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
        // extras normalization
        $payload['high_chair_count'] = max(0, absint($payload['high_chair_count']));
        if ($payload['high_chair_count'] > 5) {
            $payload['high_chair_count'] = 5;
        }
        $payload['wheelchair_table'] = $this->toBool($payload['wheelchair_table']);
        $payload['pets']             = $this->toBool($payload['pets']);
        $payload['room_id']    = absint((int) $payload['room_id']);
        if ($payload['room_id'] === 0) {
            $payload['room_id'] = null;
        }
        $payload['table_id']   = absint((int) $payload['table_id']);
        if ($payload['table_id'] === 0) {
            $payload['table_id'] = null;
        }
        $payload['request_id'] = isset($payload['request_id']) && is_string($payload['request_id'])
            ? sanitize_text_field($payload['request_id'])
            : null;

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
        if ($detectedLanguage !== null) {
            $payload['language'] = $detectedLanguage;
        }
        $locale = (string) $payload['locale'];
        if ($locale === '') {
            $locale = $this->language->getFallbackLocale();
        }
        $payload['locale'] = $this->language->normalizeLocale($locale);

        unset($payload['phone_country']);

        return $payload;
    }

    private function detectLanguageFromPhone(string $phone, string $phoneCountry): ?string
    {
        return PhoneHelper::detectLanguage($phone, $phoneCountry);
    }

    private function normalizePhonePrefix(string $prefix): string
    {
        return PhoneHelper::normalizePrefix($prefix);
    }

    private function normalizePhoneNumber(string $phone): string
    {
        return PhoneHelper::normalizeNumber($phone);
    }

    private function normalizePhoneLanguage(string $value): string
    {
        return PhoneHelper::normalizeLanguage($value);
    }

    /**
     * @param array<string, mixed> $payload
     * 
     * @throws \FP\Resv\Core\Exceptions\ValidationException
     */
    private function assertPayload(array $payload): void
    {
        $validator = new ReservationValidator();
        
        $validator->assertValidDate($payload['date']);
        $validator->assertValidTime($payload['time']);
        
        $maxCapacity = (int) $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40');
        $validator->assertValidParty($payload['party'], $maxCapacity);
        
        $validator->assertValidContact($payload);
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
        $email = strtolower(trim($email));
        $data  = sprintf('%d|%s', $reservationId, $email);

        return hash_hmac('sha256', $data, wp_salt('fp_resv_manage'));
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

        $restaurantRecipients = EmailList::parse($notifications['restaurant_emails'] ?? []);
        $webmasterRecipients  = EmailList::parse($notifications['webmaster_emails'] ?? []);

        // Deduplica: rimuovi dai destinatari webmaster quelli già presenti in restaurant
        // per evitare di inviare due email alla stessa persona
        $webmasterRecipients = array_values(array_diff($webmasterRecipients, $restaurantRecipients));

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
            'sender_name'    => get_bloginfo('name'),
            'sender_email'   => get_bloginfo('admin_email'),
            'reply_to_email' => '',
        ]);

        if ($this->notificationSettings->shouldUseBrevo(NotificationSettings::CHANNEL_CONFIRMATION)) {
            $this->sendBrevoConfirmationEvent($payload, $reservationId, $manageUrl, $status);
            return;
        }

        if (!$this->notificationSettings->shouldUsePlugin(NotificationSettings::CHANNEL_CONFIRMATION)) {
            return;
        }

        $headers = $this->buildNotificationHeaders($notifications);

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
            $statusLabel     = $this->statusLabel($status, $languageCode);

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
            'extras'        => [
                'high_chair_count' => (int) ($payload['high_chair_count'] ?? 0),
                'wheelchair_table' => (bool) ($payload['wheelchair_table'] ?? false),
                'pets'             => (bool) ($payload['pets'] ?? false),
            ],
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
                'logo_url'         => $this->notificationSettings->logoUrl(),
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

    /**
     * Invia un evento a Brevo per far partire l'automazione di conferma
     *
     * @param array<string, mixed> $payload
     */
    private function sendBrevoConfirmationEvent(array $payload, int $reservationId, string $manageUrl, string $status): void
    {
        if ($this->brevoClient === null || !$this->brevoClient->isConnected()) {
            Logging::log('brevo', 'Brevo client non disponibile per invio evento confirmation', [
                'reservation_id' => $reservationId,
                'email'          => $payload['email'] ?? '',
            ]);
            return;
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            return;
        }

        // Controlla se l'evento è già stato inviato con successo per evitare duplicati
        if ($this->brevoRepository !== null && $this->brevoRepository->hasSuccessfulLog($reservationId, 'email_confirmation')) {
            Logging::log('brevo', 'Evento email_confirmation già inviato, skip per evitare duplicati', [
                'reservation_id' => $reservationId,
                'email'          => $email,
            ]);
            return;
        }

        $eventProperties = [
            'reservation' => array_filter([
                'id'         => $reservationId,
                'date'       => $payload['date'] ?? '',
                'time'       => isset($payload['time']) ? substr((string) $payload['time'], 0, 5) : '',
                'party'      => $payload['party'] ?? 0,
                'status'     => $status,
                'location'   => $payload['location'] ?? '',
                'manage_url' => $manageUrl,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'contact' => array_filter([
                'first_name' => $payload['first_name'] ?? '',
                'last_name'  => $payload['last_name'] ?? '',
                'phone'      => $payload['phone'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'meta' => array_filter([
                'language'          => $payload['language'] ?? '',
                'notes'             => $payload['notes'] ?? '',
                'marketing_consent' => $payload['marketing_consent'] ?? null,
                'utm_source'        => $payload['utm_source'] ?? '',
                'utm_medium'        => $payload['utm_medium'] ?? '',
                'utm_campaign'      => $payload['utm_campaign'] ?? '',
                'gclid'             => $payload['gclid'] ?? '',
                'fbclid'            => $payload['fbclid'] ?? '',
                'msclkid'           => $payload['msclkid'] ?? '',
                'ttclid'            => $payload['ttclid'] ?? '',
                'value'             => $payload['value'] ?? null,
                'currency'          => $payload['currency'] ?? '',
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];

        $response = $this->brevoClient->sendEvent('email_confirmation', [
            'email'      => strtolower(trim($email)),
            'properties' => $eventProperties,
        ]);

        // Logga l'evento nel repository Brevo se disponibile
        $success = $response['success'] ?? false;
        if ($this->brevoRepository !== null) {
            $this->brevoRepository->log(
                $reservationId,
                'email_confirmation',
                [
                    'email'      => $email,
                    'properties' => $eventProperties,
                    'response'   => $response,
                ],
                $success ? 'success' : 'error',
                $success ? null : ($response['message'] ?? null)
            );
        }

        Logging::log('brevo', 'Evento email_confirmation inviato a Brevo', [
            'reservation_id' => $reservationId,
            'email'          => $email,
            'success'        => $success,
            'response'       => $response,
        ]);
    }
}
