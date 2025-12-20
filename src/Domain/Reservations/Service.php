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
use FP\Resv\Core\Sanitizer;
use FP\Resv\Domain\Reservations\ReservationStatuses;
use FP\Resv\Domain\Reservations\EmailService;
use FP\Resv\Domain\Reservations\AvailabilityGuard;
use FP\Resv\Domain\Reservations\PaymentService;
use FP\Resv\Domain\Notifications\ManageUrlGenerator;
use FP\Resv\Domain\Calendar\GoogleCalendarService;
use FP\Resv\Domain\Customers\Repository as CustomersRepository;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Notifications\Settings as NotificationSettings;
use FP\Resv\Domain\Notifications\TemplateRenderer as NotificationTemplateRenderer;
use FP\Resv\Domain\Brevo\Client as BrevoClient;
use FP\Resv\Core\Consent;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\Logging;
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
        private readonly EmailService $emailService,
        private readonly AvailabilityGuard $availabilityGuard,
        private readonly PaymentService $paymentService,
        private readonly CustomersRepository $customers,
        private readonly NotificationSettings $notificationSettings,
        private readonly ReservationPayloadSanitizer $payloadSanitizer,
        private readonly SettingsResolver $settingsResolver,
        private readonly BrevoConfirmationEventSender $brevoEventSender,
        private readonly ManageUrlGenerator $manageUrlGenerator,
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

        // #region agent log
        $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'Before sanitize',
            'data' => [
                'payload_keys' => array_keys($payload),
                'payload_email' => $payload['email'] ?? null,
                'payload_date' => $payload['date'] ?? null
            ],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        $sanitized = $this->payloadSanitizer->sanitize($payload);
        
        // #region agent log
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'After sanitize',
            'data' => [
                'sanitized_keys' => array_keys($sanitized),
                'sanitized_email' => $sanitized['email'] ?? null,
                'sanitized_date' => $sanitized['date'] ?? null,
                'sanitized_party' => $sanitized['party'] ?? null
            ],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        Logging::log('reservations', 'Payload sanitizzato OK');
        
        $this->assertPayload($sanitized);
        
        // #region agent log
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'After assertPayload',
            'data' => ['validation_passed' => true],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
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
            $manageUrl = $this->manageUrlGenerator->generate($existingId, $sanitized['email']);
            
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
            : ($consentMeta['version'] ?? $this->settingsResolver->resolvePolicyVersion());
        if (!is_string($policyVersion) || trim($policyVersion) === '') {
            $policyVersion = $this->settingsResolver->resolvePolicyVersion();
        }

        $sanitized['policy_version'] = (string) $policyVersion;

        if (($consentMeta['updated_at'] ?? 0) > 0) {
            $sanitized['consent_timestamp'] = wp_date('Y-m-d H:i:s', (int) $consentMeta['updated_at']);
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

        $status = $this->settingsResolver->resolveDefaultStatus();
        if (is_string($sanitized['status']) && $sanitized['status'] !== '') {
            $candidate = strtolower($sanitized['status']);
            if (in_array($candidate, self::ALLOWED_STATUSES, true)) {
                $status = $candidate;
            }
        }

        $status = $this->paymentService->resolveStatus($sanitized, $status);

        $this->availabilityGuard->guardCalendarConflicts($sanitized['date'], $sanitized['time'], $status);

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
        
        if ($customerId <= 0) {
            Logging::log('reservations', 'ERRORE: customer_id non valido dopo upsert', [
                'email' => $sanitized['email'],
                'customer_id' => $customerId,
            ]);
            throw new RuntimeException('Impossibile creare o recuperare il cliente');
        }
        
        Logging::log('reservations', 'Cliente creato/trovato con ID: ' . $customerId);

        // Verifica atomica della disponibilità dentro una transazione
        // per prevenire race conditions quando arrivano prenotazioni simultanee
        // #region agent log
        $logFile = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))))) . '.cursor/debug.log';
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'Before beginTransaction',
            'data' => [
                'date' => $sanitized['date'],
                'time' => $sanitized['time'],
                'party' => $sanitized['party']
            ],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'D'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        $this->repository->beginTransaction();
        
        // #region agent log
        $logData = json_encode([
            'id' => 'log_' . time() . '_' . uniqid(),
            'timestamp' => time() * 1000,
            'location' => __FILE__ . ':' . __LINE__,
            'message' => 'Transaction started',
            'data' => ['transaction_started' => true],
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'D'
        ]) . "\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);
        // #endregion
        
        try {
            // #region agent log
            $guardStartTime = microtime(true);
            // #endregion
            
            $this->availabilityGuard->guardAvailabilityForSlot(
                $sanitized['date'],
                $sanitized['time'],
                $sanitized['party'],
                $sanitized['room_id'],
                $sanitized['meal'],
                $status
            );
            
            // #region agent log
            $guardEndTime = microtime(true);
            $guardDuration = ($guardEndTime - $guardStartTime) * 1000;
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'Availability guard passed',
                'data' => [
                    'guard_duration_ms' => round($guardDuration, 2),
                    'availability_ok' => true
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'D'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            
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

            // #region agent log
            $insertStartTime = microtime(true);
            // #endregion
            
            $reservationId = $this->repository->insert($reservationData);
            
            // #region agent log
            $insertEndTime = microtime(true);
            $insertDuration = ($insertEndTime - $insertStartTime) * 1000;
            $logData = json_encode([
                'id' => 'log_' . time() . '_' . uniqid(),
                'timestamp' => time() * 1000,
                'location' => __FILE__ . ':' . __LINE__,
                'message' => 'After insert',
                'data' => [
                    'reservation_id' => $reservationId,
                    'insert_duration_ms' => round($insertDuration, 2),
                    'insert_success' => $reservationId > 0
                ],
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'D'
            ]) . "\n";
            @file_put_contents($logFile, $logData, FILE_APPEND);
            // #endregion
            
            if ($reservationId <= 0) {
                Logging::log('reservations', '❌ ERRORE: Insert ha restituito ID non valido', [
                    'reservation_id' => $reservationId,
                    'data' => $reservationData,
                ]);
                throw new RuntimeException('Impossibile salvare la prenotazione nel database');
            }

            Logging::log('reservations', '✅ PRENOTAZIONE SALVATA NEL DB', [
                'reservation_id' => $reservationId,
                'customer_id' => $reservationData['customer_id'],
            ]);

            $reservation = $this->repository->find($reservationId);
            if ($reservation === null) {
                Logging::log('reservations', '❌ ERRORE: Prenotazione salvata ma non trovata!', [
                    'reservation_id' => $reservationId,
                    'customer_id' => $reservationData['customer_id'],
                ]);
                throw new RuntimeException('Prenotazione creata ma impossibile recuperarla. ID: ' . $reservationId);
            }
            
            Logging::log('reservations', '✅ Prenotazione trovata e verificata', [
                'reservation_id' => $reservation->getId(),
                'status' => $reservation->getStatus(),
            ]);
            
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

        $manageUrl = $this->manageUrlGenerator->generate($reservationId, $sanitized['email']);

        $paymentPayload = null;
        $requiresPayment = $this->paymentService->requiresPayment($sanitized);
        if ($requiresPayment) {
            $paymentPayload = $this->paymentService->createPaymentIntent($reservationId, $sanitized);

            // Se il payment intent fallisce e lo status è pending_payment, cambia a pending
            if (($paymentPayload['status'] ?? '') === 'error' && $status === 'pending_payment') {
                $status = 'pending';
                $this->repository->update($reservationId, ['status' => $status]);
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
            // Gestisce Brevo se configurato, altrimenti usa EmailService
            if ($this->notificationSettings->shouldUseBrevo(NotificationSettings::CHANNEL_CONFIRMATION)) {
                $this->brevoEventSender->send($sanitized, $reservationId, $manageUrl, $status);
            } else {
                $this->emailService->sendCustomerEmail($sanitized, $reservationId, $manageUrl, $status);
            }
        } catch (Throwable $emailException) {
            Logging::log('mail', 'ERRORE invio email cliente', [
                'reservation_id' => $reservationId,
                'error' => $emailException->getMessage(),
                'file' => $emailException->getFile(),
                'line' => $emailException->getLine(),
            ]);
        }
        
        try {
            $this->emailService->sendStaffNotifications($sanitized, $reservationId, $manageUrl, $status, $reservation);
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
        $validator->assertValidDateTime($payload['date'], $payload['time']); // Controlla che data+ora non sia nel passato
        
        $maxCapacity = (int) $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40');
        $validator->assertValidParty($payload['party'], $maxCapacity);
        
        $validator->assertValidContact($payload);
    }





}
