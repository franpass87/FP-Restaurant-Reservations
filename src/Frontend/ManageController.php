<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\ServiceContainer;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\Service as ReservationsService;
use FP\Resv\Domain\Settings\Language;
use function add_action;
use function esc_html;
use function file_exists;
use function filter_var;
use function header;
use function http_response_code;
use function is_string;
use function sanitize_text_field;
use function sprintf;
use function substr;
use function strtolower;
use const FILTER_VALIDATE_INT;

final class ManageController
{
    public function boot(): void
    {
        add_action('template_redirect', [$this, 'maybeRenderManagePage']);
    }

    public function maybeRenderManagePage(): void
    {
        $reservationId = isset($_GET['fp_resv_manage']) ? sanitize_text_field((string) $_GET['fp_resv_manage']) : '';
        $token         = isset($_GET['fp_resv_token']) ? sanitize_text_field((string) $_GET['fp_resv_token']) : '';

        if ($reservationId === '' || $token === '') {
            return;
        }

        if (filter_var($reservationId, FILTER_VALIDATE_INT) === false) {
            $this->renderError(__('Parametro non valido.', 'fp-restaurant-reservations'));
            exit;
        }

        $id = (int) $reservationId;

        $container = ServiceContainer::getInstance();
        /** @var ReservationsRepository|null $repo */
        $repo = $container->get(ReservationsRepository::class);
        /** @var ReservationsService|null $service */
        $service = $container->get(ReservationsService::class);
        /** @var Language|null $language */
        $language = $container->get(Language::class);

        if (!$repo instanceof ReservationsRepository || !$service instanceof ReservationsService) {
            $this->renderError(__('Servizio non disponibile.', 'fp-restaurant-reservations'));
            exit;
        }

        // Feature flag: manage page enabled?
        /** @var \FP\Resv\Domain\Settings\Options|null $options */
        $options = $container->get(\FP\Resv\Domain\Settings\Options::class);
        $general = $options instanceof \FP\Resv\Domain\Settings\Options ? $options->getGroup('fp_resv_general', []) : [];
        $manageEnabled = (string) ($general['enable_manage_page'] ?? '1') === '1';
        if (!$manageEnabled) {
            $this->renderError(__('Funzionalità disabilitata.', 'fp-restaurant-reservations'));
            exit;
        }

        $reservation = $repo->findAgendaEntry($id);
        if (!is_array($reservation)) {
            $this->renderError(__('Prenotazione non trovata.', 'fp-restaurant-reservations'));
            exit;
        }

        $email = (string) ($reservation['email'] ?? '');
        $expected = $this->generateManageToken($id, $email);
        if (!is_string($token) || strtolower(trim($token)) !== strtolower($expected)) {
            $this->renderError(__('Link non valido o scaduto.', 'fp-restaurant-reservations'));
            exit;
        }

        $strings = $language instanceof Language ? $language->getStrings((string) ($reservation['customer_lang'] ?? 'it')) : [];

        // Handle action POST (cancel / change_time)
        $notice = '';
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
            $requestsEnabled = (string) ($general['enable_manage_requests'] ?? '1') === '1';
            $action = isset($_POST['fp_resv_action']) ? sanitize_text_field((string) $_POST['fp_resv_action']) : '';
            $desiredTime = isset($_POST['fp_resv_desired_time']) ? sanitize_text_field((string) $_POST['fp_resv_desired_time']) : '';
            $userNote = isset($_POST['fp_resv_user_note']) ? sanitize_text_field((string) $_POST['fp_resv_user_note']) : '';

            $status = (string) ($reservation['status'] ?? '');
            $allowed = !in_array($status, ['cancelled', 'visited', 'no-show'], true);

            if (($action === 'cancel_request' || $action === 'change_time_request') && $allowed && $requestsEnabled) {
                $sent = $this->notifyStaffAction($id, (array) $reservation, $action, $desiredTime, $userNote);
                $notice = $sent
                    ? __('Richiesta inviata. Ti contatteremo a breve.', 'fp-restaurant-reservations')
                    : __('Impossibile inviare la richiesta. Riprova più tardi.', 'fp-restaurant-reservations');
                if ($sent) {
                    $this->logAudit($id, $action, $userNote, $desiredTime);
                }
            } elseif (!$allowed) {
                $notice = __('Azione non disponibile per lo stato attuale della prenotazione.', 'fp-restaurant-reservations');
            } elseif (!$requestsEnabled) {
                $notice = __('Le richieste dal cliente sono disabilitate dal ristorante.', 'fp-restaurant-reservations');
            }
        }

        $context = [
            'id'         => $id,
            'date'       => (string) ($reservation['date'] ?? ''),
            'time'       => substr((string) ($reservation['time'] ?? ''), 0, 5),
            'party'      => (int) ($reservation['party'] ?? 0),
            'status'     => (string) ($reservation['status'] ?? ''),
            'first_name' => (string) ($reservation['first_name'] ?? ''),
            'last_name'  => (string) ($reservation['last_name'] ?? ''),
            'email'      => $email,
            'phone'      => (string) ($reservation['phone'] ?? ''),
            'notes'      => (string) ($reservation['notes'] ?? ''),
            'allergies'  => (string) ($reservation['allergies'] ?? ''),
            'notice'     => $notice,
            // Extras sono appesi nelle note dal Service; in futuro potrebbero essere campi dedicati
        ];

        $template = Plugin::$dir . 'templates/frontend/manage.php';
        if (!file_exists($template)) {
            $this->renderError(__('Template non trovato.', 'fp-restaurant-reservations'));
            exit;
        }

        // Render template minimale
        /** @var array<string, mixed> $context */
        $context = apply_filters('fp_resv_manage_context', $context, $reservation);
        $strings = apply_filters('fp_resv_language_strings', $strings, (string) ($reservation['customer_lang'] ?? 'it'));

        // Imposta status 200 e disabilita caching
        http_response_code(200);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        // Isola variabili per il template
        /** @var array<string, mixed> $context */
        /** @var array<string, mixed> $strings */
        include $template;
        exit;
    }

    private function notifyStaffAction(int $reservationId, array $reservation, string $action, string $desiredTime, string $userNote): bool
    {
        $container = ServiceContainer::getInstance();
        /** @var \FP\Resv\Domain\Settings\Options|null $options */
        $options = $container->get(\FP\Resv\Domain\Settings\Options::class);
        if (!$options instanceof \FP\Resv\Domain\Settings\Options) {
            return false;
        }

        $notifications = $options->getGroup('fp_resv_notifications', [
            'restaurant_emails' => [],
            'sender_name'       => get_bloginfo('name'),
            'sender_email'      => get_bloginfo('admin_email'),
            'reply_to_email'    => '',
        ]);

        $general = $options->getGroup('fp_resv_general', [
            'restaurant_name' => get_bloginfo('name'),
        ]);

        $recipients = is_array($notifications['restaurant_emails'] ?? null)
            ? array_values(array_filter($notifications['restaurant_emails']))
            : [];

        if ($recipients === []) {
            return false;
        }

        $subjectAction = $action === 'cancel_request' ? __('Richiesta annullo', 'fp-restaurant-reservations') : __('Richiesta modifica orario', 'fp-restaurant-reservations');
        $restaurantName = (string) ($general['restaurant_name'] ?? get_bloginfo('name'));
        $subject = sprintf('%s - %s #%d', $restaurantName, $subjectAction, $reservationId);

        // Build context for notifications templating system
        /** @var \FP\Resv\Domain\Notifications\Settings $notifSettings */
        $notifSettings = $container->get(\FP\Resv\Domain\Notifications\Settings::class);
        /** @var \FP\Resv\Domain\Notifications\TemplateRenderer $templates */
        $templates     = $container->get(\FP\Resv\Domain\Notifications\TemplateRenderer::class);
        /** @var \FP\Resv\Core\Mailer $mailer */
        $mailer        = $container->get(\FP\Resv\Core\Mailer::class);

        if (!$notifSettings instanceof \FP\Resv\Domain\Notifications\Settings || !$templates instanceof \FP\Resv\Domain\Notifications\TemplateRenderer || !$mailer instanceof \FP\Resv\Core\Mailer) {
            return false;
        }

        $language = (string) ($reservation['customer_lang'] ?? 'it');
        $context = [
            'id'        => $reservationId,
            'status'    => (string) ($reservation['status'] ?? ''),
            'date'      => (string) ($reservation['date'] ?? ''),
            'time'      => substr((string) ($reservation['time'] ?? ''), 0, 5),
            'party'     => (int) ($reservation['party'] ?? 0),
            'language'  => $language,
            'restaurant'=> [ 'name' => (string) ($general['restaurant_name'] ?? get_bloginfo('name')) ],
            'customer'  => [
                'first_name' => (string) ($reservation['first_name'] ?? ''),
                'last_name'  => (string) ($reservation['last_name'] ?? ''),
            ],
            'request'   => [
                'action'       => $action,
                'action_label' => $subjectAction,
                'desired_time' => substr($desiredTime, 0, 5),
                'user_note'    => $userNote,
            ],
        ];

        $rendered = $templates->render('staff', $context + [
            // reuse staff layout; provide minimal strings mapping
        ]);
        // Override body with dedicated manage-request partial for consistent look
        $message = $this->renderInlineTemplate('templates/emails/manage-request.html.php', $context, $templates);
        if ($message === '') {
            $message = $rendered['body'];
        }

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

        return $mailer->send(
            implode(',', $recipients),
            $subject,
            $message,
            $headers,
            [],
            [
                'reservation_id' => $reservationId,
                'channel'        => 'restaurant_notification',
                'content_type'   => 'text/html',
            ]
        );
    }

    private function renderInlineTemplate(string $relativePath, array $context, \FP\Resv\Domain\Notifications\TemplateRenderer $templates): string
    {
        $templatePath = Plugin::$dir . $relativePath;
        if (!file_exists($templatePath)) {
            return '';
        }

        $languageService = ServiceContainer::getInstance()->get(\FP\Resv\Domain\Settings\Language::class);
        $language = $languageService instanceof \FP\Resv\Domain\Settings\Language ? $languageService->ensureLanguage((string) ($context['language'] ?? 'it')) : 'it';
        $strings = $languageService instanceof \FP\Resv\Domain\Settings\Language ? $languageService->getStrings($language) : [];

        ob_start();
        /** @var array<string,mixed> $context */
        /** @var array<string,mixed> $strings */
        include $templatePath;
        return (string) ob_get_clean();
    }

    private function logAudit(int $reservationId, string $action, string $userNote, string $desiredTime): void
    {
        $container = ServiceContainer::getInstance();
        /** @var ReservationsRepository|null $repo */
        $repo = $container->get(ReservationsRepository::class);
        if (!$repo instanceof ReservationsRepository) {
            return;
        }

        $details = [
            'action'       => $action,
            'user_note'    => $userNote,
            'desired_time' => substr($desiredTime, 0, 5),
        ];

        $repo->logAudit([
            'actor_id'    => null,
            'actor_role'  => 'customer',
            'action'      => 'manage_request',
            'entity'      => 'reservation',
            'entity_id'   => $reservationId,
            'before_json' => null,
            'after_json'  => wp_json_encode($details) ?: null,
            'ip'          => \FP\Resv\Core\Helpers::clientIp(),
        ]);
    }

    private function generateManageToken(int $reservationId, string $email): string
    {
        $email = strtolower(trim($email));
        $data  = sprintf('%d|%s', $reservationId, $email);

        return hash_hmac('sha256', $data, wp_salt('fp_resv_manage'));
    }

    private function renderError(string $message): void
    {
        http_response_code(400);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo '<div class="fp-resv-manage fp-resv"><p>' . esc_html($message) . '</p></div>';
    }
}


