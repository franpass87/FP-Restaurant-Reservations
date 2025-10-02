<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Surveys;

use FP\Resv\Core\Plugin;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use wpdb;
use function __;
use function absint;
use function add_action;
use function current_time;
use function do_action;
use function esc_html;
use function file_exists;
use function is_array;
use function is_email;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function rawurlencode;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_email;
use function sanitize_textarea_field;
use function strtolower;
use function trim;
use function wp_verify_nonce;

final class REST
{
    private NPS $nps;

    public function __construct(
        private readonly Options $options,
        private readonly Language $language,
        private readonly ReservationsRepository $reservations,
        private readonly wpdb $wpdb
    ) {
        $this->nps = new NPS();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'fp-resv/v1',
            '/surveys',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleSubmit'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handleSubmit(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $reservationId = absint((string) $request->get_param('reservation_id'));
        if ($reservationId <= 0) {
            return new WP_Error('fp_resv_survey_invalid_reservation', __('Prenotazione non valida.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $nonce = $request->get_param('fp_resv_survey_nonce') ?? $request->get_param('_wpnonce');
        if (!is_string($nonce)) {
            $nonce = $request->get_header('X-WP-Nonce');
        }

        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'fp_resv_submit_survey')) {
            return new WP_Error(
                'fp_resv_survey_invalid_nonce',
                __('Verifica di sicurezza non superata. Aggiorna la pagina e riprova.', 'fp-restaurant-reservations'),
                ['status' => 403]
            );
        }

        $email = sanitize_email((string) $request->get_param('email'));
        if ($email === '' || !is_email($email)) {
            return new WP_Error('fp_resv_survey_invalid_email', __('Email non valida.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $token = (string) $request->get_param('token');
        if (!Token::verify($reservationId, $email, $token)) {
            return new WP_Error('fp_resv_survey_invalid_token', __('Link survey non valido o scaduto.', 'fp-restaurant-reservations'), ['status' => 403]);
        }

        $reservation = $this->reservations->findAgendaEntry($reservationId);
        if (!is_array($reservation)) {
            return new WP_Error('fp_resv_survey_reservation_missing', __('Prenotazione non trovata.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        $storedEmail = strtolower(trim((string) ($reservation['email'] ?? '')));
        if ($storedEmail !== strtolower(trim($email))) {
            return new WP_Error('fp_resv_survey_email_mismatch', __('L\'email non corrisponde alla prenotazione.', 'fp-restaurant-reservations'), ['status' => 403]);
        }

        $scores = [
            'food'        => $this->normalizeStars($request->get_param('stars_food')),
            'service'     => $this->normalizeStars($request->get_param('stars_service')),
            'atmosphere'  => $this->normalizeStars($request->get_param('stars_atmosphere')),
        ];
        $nps = $this->normalizeNps($request->get_param('nps'));
        $comment = sanitize_textarea_field((string) $request->get_param('comment'));

        $average = $this->nps->average($scores);
        $brevoSettings = $this->options->getGroup('fp_resv_brevo', []);
        $averageThreshold = (float) ($brevoSettings['brevo_review_threshold'] ?? 4.5);
        $npsThreshold = (int) ($brevoSettings['brevo_review_nps_threshold'] ?? 9);

        $positive = $this->nps->isPositive($average, $nps, $averageThreshold, $npsThreshold);
        $placeId = (string) ($brevoSettings['brevo_review_place_id'] ?? '');
        $reviewUrl = '';
        if ($positive && $placeId !== '') {
            $reviewUrl = 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId);
        }

        $this->persistSurvey($reservationId, $email, $reservation, $scores, $nps, $comment, $positive && $reviewUrl !== '');

        $languageCode = (string) ($reservation['customer_lang'] ?? '');
        $strings      = $this->language->getStrings($languageCode);
        $surveyStrings = is_array($strings['survey'] ?? null) ? $strings['survey'] : [];

        $result = [
            'reservation_id' => $reservationId,
            'email'          => $email,
            'scores'         => $scores,
            'nps'            => $nps,
            'average'        => $average,
            'positive'       => $positive,
            'comment'        => $comment,
            'review_url'     => $reviewUrl,
            'lang'           => $reservation['customer_lang'] ?? '',
        ];

        /**
         * @param int $reservationId
         * @param array<string, mixed> $result
         */
        do_action('fp_resv_survey_submitted', $reservationId, $result);

        $template = $positive ? 'thanks-positive.php' : 'thanks-negative.php';
        $html = $this->renderTemplate($template, [
            'result'    => $result,
            'reviewUrl' => $reviewUrl,
            'strings'   => $surveyStrings,
        ]);

        if ($html === '') {
            $message = $positive
                ? (string) ($surveyStrings['positive']['message'] ?? __('Grazie per aver condiviso la tua esperienza!', 'fp-restaurant-reservations'))
                : (string) ($surveyStrings['negative']['message'] ?? __('Grazie per il tuo feedback, il nostro staff ti ricontatterà al più presto.', 'fp-restaurant-reservations'));
            $html = '<p>' . esc_html($message) . '</p>';
        }

        return rest_ensure_response([
            'positive'   => $positive,
            'review_url' => $reviewUrl,
            'html'       => $html,
        ]);
    }

    private function tableName(): string
    {
        return $this->wpdb->prefix . 'fp_surveys';
    }

    private function normalizeStars(mixed $value): ?int
    {
        $value = absint((string) $value);
        if ($value < 1 || $value > 5) {
            return null;
        }

        return $value;
    }

    private function normalizeNps(mixed $value): int
    {
        $value = absint((string) $value);
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 10) {
            $value = 10;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $reservation
     * @param array<string, int|null> $scores
     */
    private function persistSurvey(
        int $reservationId,
        string $email,
        array $reservation,
        array $scores,
        int $nps,
        string $comment,
        bool $reviewShown
    ): void {
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT id FROM ' . $this->tableName() . ' WHERE reservation_id = %d', $reservationId),
            ARRAY_A
        );

        $data = [
            'reservation_id'     => $reservationId,
            'email'              => $email,
            'lang'               => $reservation['customer_lang'] ?? '',
            'stars_food'         => $scores['food'],
            'stars_service'      => $scores['service'],
            'stars_atmosphere'   => $scores['atmosphere'],
            'nps'                => $nps,
            'comment'            => $comment,
            'review_link_shown'  => $reviewShown ? 1 : 0,
            'updated_at'         => current_time('mysql'),
        ];

        if ($existing === null) {
            $data['created_at'] = current_time('mysql');
            $this->wpdb->insert($this->tableName(), $data);
        } else {
            $this->wpdb->update($this->tableName(), $data, ['id' => (int) $existing['id']]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): string
    {
        $path = Plugin::$dir . 'templates/survey/' . ltrim($template, '/');
        if (!file_exists($path)) {
            return '';
        }

        ob_start();
        /** @var array<string, mixed> $context */
        $context = $context;
        include $path;

        $output = ob_get_clean();
        if (!is_string($output)) {
            return '';
        }

        return trim($output);
    }
}
