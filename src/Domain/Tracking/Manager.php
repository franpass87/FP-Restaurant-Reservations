<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Core\Consent;
use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Settings\Options;
use function add_action;
use function esc_url_raw;
use function home_url;
use function is_admin;
use function is_string;
use function printf;
use function rawurlencode;
use function sprintf;
use function wp_json_encode;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class Manager
{
    private const DEFAULTS = [
        'ga4_measurement_id'      => '',
        'ga4_api_secret'          => '',
        'google_ads_conversion_id'=> '',
        'meta_pixel_id'           => '',
        'meta_access_token'       => '',
        'clarity_project_id'      => '',
        'tracking_use_gtm'        => '0',
        'tracking_enable_debug'   => '0',
        'tracking_utm_cookie_days'=> '90',
        'tracking_cookie_ttl_days'=> '180',
        'consent_mode_default'    => 'auto',
    ];

    private Options $options;

    private GA4 $ga4;

    private Ads $ads;

    private Meta $meta;

    private Clarity $clarity;

    private UTMAttributionHandler $utmHandler;

    private TrackingScriptGenerator $scriptGenerator;

    private ReservationEventBuilder $eventBuilder;

    private ServerSideEventDispatcher $serverSideDispatcher;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function __construct(
        Options $options,
        GA4 $ga4,
        Ads $ads,
        Meta $meta,
        Clarity $clarity,
        UTMAttributionHandler $utmHandler,
        TrackingScriptGenerator $scriptGenerator,
        ReservationEventBuilder $eventBuilder,
        ServerSideEventDispatcher $serverSideDispatcher
    ) {
        $this->options = $options;
        $this->ga4     = $ga4;
        $this->ads     = $ads;
        $this->meta    = $meta;
        $this->clarity = $clarity;
        $this->utmHandler = $utmHandler;
        $this->scriptGenerator = $scriptGenerator;
        $this->eventBuilder = $eventBuilder;
        $this->serverSideDispatcher = $serverSideDispatcher;
    }

    public function boot(): void
    {
        add_action('init', [$this, 'captureAttribution']);
        add_action('wp_head', [$this, 'renderHead'], 1);
        add_action('wp_footer', [$this, 'renderFooter'], 90);
        add_action('fp_resv_reservation_created', [$this, 'handleReservationCreated'], 10, 3);
        add_action('fp_resv_event_booked', [$this, 'handleEventBooked'], 10, 4);
    }

    public function captureAttribution(): void
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $cookieDays = (int) ($this->settings()['tracking_utm_cookie_days'] ?? 90);
        $this->utmHandler->capture($cookieDays);
    }

    public function renderHead(): void
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $settings = $this->settings();
        $config   = $this->buildTrackingConfig($settings);
        $json     = wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return;
        }

        $useGtm = ($settings['tracking_use_gtm'] ?? '0') === '1';
        if (!$useGtm) {
            $gtagId = $this->ga4->measurementId() !== '' ? $this->ga4->measurementId() : $this->ads->gtagLoaderId();
            if ($gtagId !== '') {
                printf('<script async src="%s"></script>' . "\n", esc_url_raw('https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($gtagId)));
            }
        }

        printf('<script id="fp-resv-tracking-config">%s</script>' . "\n", $this->scriptGenerator->generateBootstrap($json));
    }

    public function renderFooter(): void
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!DataLayer::hasEvents()) {
            return;
        }

        $events = DataLayer::consume();
        $json   = wp_json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return;
        }

        printf('<script id="fp-resv-tracking-events">%s</script>' . "\n", $this->scriptGenerator->generateEventDispatcher($json));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleReservationCreated(int $reservationId, array $payload, ReservationModel $reservation): void
    {
        $eventId = $this->serverSideDispatcher->generateEventId();
        $event = $this->eventBuilder->buildReservationEvent($reservationId, $payload, $reservation, $eventId);

        DataLayer::push($event);

        // Invia eventi server-side
        $this->serverSideDispatcher->dispatch($event, $reservation, $payload);

        // Dispatch acquisto stimato se applicabile
        $currency = is_string($payload['currency'] ?? null) && $payload['currency'] !== '' ? (string) $payload['currency'] : 'EUR';
        $estimatedEvent = $this->eventBuilder->buildEstimatedPurchaseEvent($payload, $reservation, $currency);
        if ($estimatedEvent !== null) {
            DataLayer::push($estimatedEvent);
        }
    }

    /**
     * @param array<string, mixed> $eventData
     * @param array<int, array<string, mixed>> $tickets
     * @param array<string, mixed> $payload
     */
    public function handleEventBooked(array $eventData, array $reservation, array $tickets, array $payload): void
    {
        $eventId = $this->serverSideDispatcher->generateEventId();
        $eventPayload = $this->eventBuilder->buildEventTicketEvent($eventData, $reservation, $tickets, $payload, $eventId);

        DataLayer::push($eventPayload);

        // Invia eventi server-side
        $reservationModel = new ReservationModel(
            (int) ($reservation['id'] ?? 0),
            (string) ($reservation['customer_name'] ?? ''),
            (string) ($reservation['customer_email'] ?? ''),
            (string) ($reservation['customer_phone'] ?? ''),
            (string) ($reservation['date'] ?? ''),
            (string) ($reservation['time'] ?? ''),
            (int) ($reservation['party'] ?? 1),
            (string) ($reservation['status'] ?? 'pending'),
            (string) ($reservation['notes'] ?? ''),
            null
        );
        $this->serverSideDispatcher->dispatch($eventPayload, $reservationModel, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrackingConfig(array $settings): array
    {
        $useGtm = ($settings['tracking_use_gtm'] ?? '0') === '1';

        return [
            'debug'            => ($settings['tracking_enable_debug'] ?? '0') === '1',
            'gtmOnly'          => $useGtm,
            'ga4Id'            => $useGtm ? '' : $this->ga4->measurementId(),
            'googleAdsId'      => $useGtm ? '' : $this->ads->gtagLoaderId(),
            'googleAdsSendTo'  => $this->ads->conversionId(),
            'metaPixelId'      => $useGtm ? '' : $this->meta->pixelId(),
            'clarityId'        => $useGtm ? '' : $this->clarity->projectId(),
            'consent'          => Consent::all(),
            'gtagConsent'      => Consent::gtagState(),
            'cookieName'       => Consent::cookieName(),
            'cookieTtl'        => Consent::cookieTtlDays(),
            'consentVersion'   => Consent::version(),
            'attribution'      => DataLayer::attribution(),
            'homeUrl'          => esc_url_raw(home_url('/')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        if ($this->settings === null) {
            $this->settings = $this->options->getGroup('fp_resv_tracking', self::DEFAULTS);
        }

        return $this->settings;
    }
}
