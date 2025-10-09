<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tracking;

use FP\Resv\Core\Consent;
use FP\Resv\Core\DataLayer;
use FP\Resv\Domain\Reservations\Models\Reservation as ReservationModel;
use FP\Resv\Domain\Settings\Options;
use function add_action;
use function array_filter;
use function count;
use function esc_url_raw;
use function home_url;
use function is_admin;
use function is_numeric;
use function is_string;
use function max;
use function round;
use function sanitize_text_field;
use function strtolower;
use function substr;
use function wp_json_encode;
use function wp_unslash;
use function rawurlencode;
use function strpos;
use function uniqid;
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

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function __construct(Options $options, GA4 $ga4, Ads $ads, Meta $meta, Clarity $clarity)
    {
        $this->options = $options;
        $this->ga4     = $ga4;
        $this->ads     = $ads;
        $this->meta    = $meta;
        $this->clarity = $clarity;
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

        $params = [];
        $keys   = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'msclkid', 'ttclid'];

        foreach ($keys as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            if ($value === '') {
                continue;
            }

            $params[$key] = $value;
        }

        if ($params === []) {
            return;
        }

        if (!Consent::has('ads') && !Consent::has('analytics')) {
            return;
        }

        DataLayer::storeAttribution($params, (int) ($this->settings()['tracking_utm_cookie_days'] ?? 90));
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

        $gtagId = $this->ga4->measurementId() !== '' ? $this->ga4->measurementId() : $this->ads->gtagLoaderId();
        if ($gtagId !== '') {
            printf('<script async src="%s"></script>' . "\n", esc_url_raw('https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($gtagId)));
        }

        printf('<script id="fp-resv-tracking-config">%s</script>' . "\n", $this->generateBootstrapScript($json));
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

        printf('<script id="fp-resv-tracking-events">(function(w){w.dataLayer=w.dataLayer||[];var e=%s;if(!w.fpResvTracking){w.fpResvTracking={};}if(typeof w.fpResvTracking.dispatch!=="function"){w.fpResvTracking.dispatch=function(){return null;};}for(var i=0;i<e.length;i++){w.dataLayer.push(e[i]);w.fpResvTracking.dispatch(e[i]);}})(window);</script>' . "\n", $json);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleReservationCreated(int $reservationId, array $payload, ReservationModel $reservation): void
    {
        $status    = strtolower($reservation->status);
        $value     = isset($payload['value']) && is_numeric($payload['value']) ? (float) $payload['value'] : 0.0;
        $currency  = is_string($payload['currency'] ?? null) && $payload['currency'] !== '' ? (string) $payload['currency'] : 'EUR';
        $location  = is_string($payload['location'] ?? null) && $payload['location'] !== '' ? (string) $payload['location'] : 'default';

        // Genera event_id per deduplicazione
        $eventId = $this->generateEventId();

        $event = [
            'event'       => 'reservation_submit',
            'event_id'    => $eventId,
            'reservation' => [
                'id'       => $reservationId,
                'status'   => $status,
                'date'     => $reservation->date,
                'time'     => substr($reservation->time, 0, 5),
                'party'    => $reservation->party,
                'location' => $location,
            ],
            'ga4' => [
                'name'   => 'reservation_submit',
                'params' => array_filter([
                    'reservation_id'     => $reservationId,
                    'reservation_status' => $status,
                    'reservation_party'  => $reservation->party,
                    'reservation_date'   => $reservation->date,
                    'reservation_time'   => substr($reservation->time, 0, 5),
                    'reservation_location' => $location,
                    'value'              => $value > 0 ? $value : null,
                    'currency'           => $currency,
                    'event_id'           => $eventId,
                ], static fn ($val) => $val !== null && $val !== ''),
            ],
        ];

        if ($status === 'confirmed') {
            $event['ga4']['name']             = 'reservation_confirmed';
            $event['reservation']['status']   = 'confirmed';
            $adsPayload                       = $this->ads->conversionPayload($reservationId, $value, $currency);
            $metaPayload                      = $this->meta->eventPayload('Purchase', $value, $currency, $reservationId);
            if ($adsPayload !== null) {
                $event['ads'] = $adsPayload;
            }
            if ($metaPayload !== null) {
                $event['meta'] = $metaPayload;
                // Aggiungi event_id per deduplicazione
                $event['meta']['event_id'] = $eventId;
            }
        } elseif ($status === 'waitlist') {
            $event['ga4']['name'] = 'waitlist_joined';
        } elseif ($status === 'pending_payment') {
            $event['ga4']['name'] = 'reservation_payment_required';
        }

        DataLayer::push($event);

        // Invia eventi server-side
        $this->sendServerSideEvents($event, $reservation, $payload);

        $this->maybeDispatchEstimatedPurchase($payload, $reservation, $currency);
    }

    /**
     * @param array<string, mixed> $eventData
     * @param array<int, array<string, mixed>> $tickets
     * @param array<string, mixed> $payload
     */
    public function handleEventBooked(array $eventData, array $reservation, array $tickets, array $payload): void
    {
        $count    = count($tickets);
        $value    = isset($eventData['price']) && is_numeric($eventData['price']) ? (float) $eventData['price'] * max(1, $count) : 0.0;
        $currency = is_string($eventData['currency'] ?? null) && $eventData['currency'] !== '' ? (string) $eventData['currency'] : ($payload['currency'] ?? 'EUR');

        // Genera event_id per deduplicazione
        $eventId = $this->generateEventId();

        $metaPayload = $this->meta->eventPayload('Purchase', $value, $currency, (int) ($reservation['id'] ?? 0));
        $adsPayload  = $this->ads->conversionPayload((int) ($reservation['id'] ?? 0), $value, $currency);

        $eventPayload = [
            'event'  => 'event_ticket_purchase',
            'event_id' => $eventId,
            'event_meta' => [
                'event_id' => $eventData['id'] ?? null,
                'tickets'  => $count,
            ],
            'ga4'   => [
                'name'   => 'event_ticket_purchase',
                'params' => array_filter([
                    'items'   => [
                        [
                            'item_id'   => 'event-' . ($eventData['id'] ?? '0'),
                            'item_name' => $eventData['title'] ?? '',
                            'quantity'  => $count,
                            'price'     => $value > 0 && $count > 0 ? $value / $count : 0,
                        ],
                    ],
                    'value'    => $value > 0 ? $value : null,
                    'currency' => $currency,
                    'event_id' => $eventId,
                ], static fn ($val) => $val !== null),
            ],
        ];

        if ($metaPayload !== null) {
            $eventPayload['meta'] = $metaPayload;
            // Aggiungi event_id per deduplicazione
            $eventPayload['meta']['event_id'] = $eventId;
        }

        if ($adsPayload !== null) {
            $eventPayload['ads'] = $adsPayload;
        }

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
        $this->sendServerSideEvents($eventPayload, $reservationModel, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrackingConfig(array $settings): array
    {
        return [
            'debug'            => ($settings['tracking_enable_debug'] ?? '0') === '1',
            'ga4Id'            => $this->ga4->measurementId(),
            'googleAdsId'      => $this->ads->gtagLoaderId(),
            'googleAdsSendTo'  => $this->ads->conversionId(),
            'metaPixelId'      => $this->meta->pixelId(),
            'clarityId'        => $this->clarity->projectId(),
            'consent'          => Consent::all(),
            'gtagConsent'      => Consent::gtagState(),
            'cookieName'       => Consent::cookieName(),
            'cookieTtl'        => Consent::cookieTtlDays(),
            'consentVersion'   => Consent::version(),
            'attribution'      => DataLayer::attribution(),
            'homeUrl'          => esc_url_raw(home_url('/')),
        ];
    }

    private function generateBootstrapScript(string $jsonConfig): string
    {
        $script = <<<JS
(function(w,d){
    var cfg = $jsonConfig;
    w.fpResvTracking = w.fpResvTracking || {};
    var api = w.fpResvTracking;
    api.config = cfg;
    api.debug = !!cfg.debug;
    api.state = cfg.consent || {};
    api.dispatch = api.dispatch || function(){};
    api.getConsent = function(){return Object.assign({}, api.state);};
    api.log = function(){if(!api.debug){return;} if (typeof console !== 'undefined' && console.log) { console.log.apply(console, arguments); }};
    function normalize(value){
        if (typeof value === 'boolean'){return value ? 'granted':'denied';}
        if (typeof value === 'string'){var lower = value.toLowerCase(); return lower === 'granted' ? 'granted' : 'denied';}
        return 'denied';
    }
    function gtagConsent(){
        return {
            analytics_storage: api.state.analytics || 'denied',
            ad_storage: api.state.ads || 'denied',
            ad_user_data: api.state.ads || 'denied',
            ad_personalization: api.state.ads || 'denied',
            personalization_storage: api.state.personalization || 'denied',
            functionality_storage: 'granted',
            security_storage: 'granted'
        };
    }
    function ensureGtag(){
        w.dataLayer = w.dataLayer || [];
        if (typeof w.gtag === 'function'){return;}
        w.gtag = function(){w.dataLayer.push(arguments);};
        w.gtag('js', new Date());
        w.gtag('consent', 'default', cfg.gtagConsent || gtagConsent());
        if (cfg.ga4Id){ w.gtag('config', cfg.ga4Id, {send_page_view:false}); }
        if (cfg.googleAdsId){ w.gtag('config', cfg.googleAdsId); }
    }
    function loadMetaPixel(){
        if (!cfg.metaPixelId){return;}
        if (api.state.ads !== 'granted'){return;}
        if (w.fbq){ w.fbq('consent', 'grant'); return; }
        var n = function(){n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);};
        if (!w._fbq){ w._fbq = n; }
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        var s = d.createElement('script'); s.async = true; s.src = 'https://connect.facebook.net/en_US/fbevents.js';
        var f = d.getElementsByTagName('script')[0]; f.parentNode.insertBefore(s,f);
        w.fbq = n; w.fbq('init', cfg.metaPixelId); w.fbq('consent', 'grant');
    }
    function loadClarity(){
        if (!cfg.clarityId){return;}
        if (api.state.analytics !== 'granted' || api.state.clarity !== 'granted'){return;}
        if (w.clarity){return;}
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments);};
            t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window,document,'clarity','script',cfg.clarityId);
    }
    api.updateConsent = function(updates){
        updates = updates || {};
        var changed = false;
        ['analytics','ads','personalization','clarity'].forEach(function(key){
            if (!(key in updates)){return;}
            var normalized = normalize(updates[key]);
            if (api.state[key] !== normalized){
                api.state[key] = normalized;
                changed = true;
            }
        });
        api.state.functionality = 'granted';
        api.state.security = 'granted';
        if (!changed){return api.state;}
        ensureGtag();
        if (typeof w.gtag === 'function'){ w.gtag('consent','update', gtagConsent()); }
        if (typeof w.fbq === 'function'){ w.fbq('consent', api.state.ads === 'granted' ? 'grant' : 'revoke'); }
        loadMetaPixel();
        loadClarity();
        api.saveConsent();
        if (typeof w.CustomEvent === 'function'){ w.dispatchEvent(new CustomEvent('fp-resv-consent-change',{detail:api.state})); }
        return api.state;
    };
    api.saveConsent = function(){
        var ttl = parseInt(cfg.cookieTtl, 10) || 0;
        var expires = '';
        if (ttl > 0){
            var date = new Date();
            date.setTime(date.getTime() + ttl * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        var secure = location.protocol === 'https:' ? '; secure' : '';
        document.cookie = cfg.cookieName + '=' + encodeURIComponent(JSON.stringify(api.state)) + expires + '; path=/; samesite=Lax' + secure;
    };
    api.dispatch = function(evt){
        if (!evt || typeof evt !== 'object'){return;}
        if (api.debug){ api.log('FP Resv event', evt); }
        ensureGtag();
        if (evt.ga4 && evt.ga4.name && typeof w.gtag === 'function'){
            w.gtag('event', evt.ga4.name, evt.ga4.params || {});
        }
        if (evt.ads && evt.ads.name && typeof w.gtag === 'function' && evt.ads.params){
            var adsParams = evt.ads.params;
            if (cfg.googleAdsSendTo && !adsParams.send_to){
                adsParams = Object.assign({}, adsParams, { send_to: cfg.googleAdsSendTo });
            }
            w.gtag('event', evt.ads.name, adsParams);
        }
        if (evt.meta && evt.meta.name && typeof w.fbq === 'function'){
            var metaParams = evt.meta.params || {};
            var metaOptions = {};
            if (evt.event_id || evt.meta.event_id){
                metaOptions.eventID = evt.event_id || evt.meta.event_id;
            }
            w.fbq('track', evt.meta.name, metaParams, metaOptions);
        }
    };
    api.pushEvent = function(name, payload){
        if (!name){return;}
        var event = Object.assign({event:name}, payload || {});
        w.dataLayer = w.dataLayer || [];
        w.dataLayer.push(event);
        api.dispatch(event);
        return event;
    };
    ensureGtag();
    if (api.state.ads === 'granted'){ loadMetaPixel(); }
    if (api.state.analytics === 'granted' && api.state.clarity === 'granted'){ loadClarity(); }
})(window, document);
JS;

        return $script;
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

    /**
     * Invia eventi server-side a GA4 e Meta
     *
     * @param array<string, mixed> $event
     * @param ReservationModel $reservation
     * @param array<string, mixed> $payload
     */
    private function sendServerSideEvents(array $event, ReservationModel $reservation, array $payload): void
    {
        $eventId = $this->generateEventId();
        $clientId = $this->extractClientId();
        $userData = $this->buildUserData($reservation);
        $eventSourceUrl = home_url($_SERVER['REQUEST_URI'] ?? '/');

        // Invia a GA4 se configurato
        if ($this->ga4->isServerSideEnabled() && isset($event['ga4'])) {
            $eventName = $event['ga4']['name'] ?? '';
            $params = $event['ga4']['params'] ?? [];

            // Aggiungi event_id per deduplicazione
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
     * Genera un ID univoco per l'evento (per deduplicazione)
     */
    private function generateEventId(): string
    {
        return uniqid('evt_', true);
    }

    /**
     * Estrae il client_id dal cookie GA (_ga)
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
     * Costruisce i dati utente per Meta Conversions API
     *
     * @param ReservationModel $reservation
     * @return array<string, mixed>
     */
    private function buildUserData(ReservationModel $reservation): array
    {
        $userData = [];

        // Email
        if ($reservation->customer_email !== '') {
            $userData['email'] = $reservation->customer_email;
        }

        // Phone
        if ($reservation->customer_phone !== '') {
            $userData['phone'] = $reservation->customer_phone;
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
     * Ottiene l'IP del client gestendo proxy e load balancer
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

    /**
     * @param array<string, mixed> $payload
     */
    private function maybeDispatchEstimatedPurchase(array $payload, ReservationModel $reservation, string $currency): void
    {
        if (isset($payload['value']) && is_numeric($payload['value']) && (float) $payload['value'] > 0) {
            return;
        }

        if (!isset($payload['price_per_person']) || !is_numeric($payload['price_per_person'])) {
            return;
        }

        $price = (float) $payload['price_per_person'];
        if ($price <= 0.0) {
            return;
        }

        $party = isset($payload['party']) && is_numeric($payload['party'])
            ? max(1, (int) $payload['party'])
            : max(1, $reservation->party);

        $estimated = round($price * $party, 2);
        if ($estimated <= 0.0) {
            return;
        }

        $currency = $currency !== '' ? $currency : 'EUR';
        $mealType = isset($payload['meal']) && is_string($payload['meal']) ? (string) $payload['meal'] : '';

        $event = [
            'event'    => 'purchase',
            'purchase' => [
                'value'              => $estimated,
                'currency'           => $currency,
                'value_is_estimated' => true,
                'meal_type'          => $mealType,
                'party_size'         => $party,
            ],
            'reservation' => [
                'id'        => $reservation->id,
                'status'    => strtolower($reservation->status),
                'party'     => $party,
                'meal_type' => $mealType,
            ],
            'ga4' => [
                'name'   => 'purchase',
                'params' => array_filter([
                    'reservation_id'     => $reservation->id,
                    'reservation_party'  => $party,
                    'meal_type'          => $mealType !== '' ? $mealType : null,
                    'value'              => $estimated,
                    'currency'           => $currency,
                    'value_is_estimated' => true,
                ], static fn ($val) => $val !== null && $val !== ''),
            ],
        ];

        DataLayer::push($event);
    }
}
