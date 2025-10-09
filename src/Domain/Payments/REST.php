<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Payments;

use FP\Resv\Core\Logging;
use FP\Resv\Core\Roles;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function add_action;
use function current_user_can;
use function is_array;
use function is_string;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_verify_nonce;

final class REST
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly Repository $payments,
        private readonly ReservationsRepository $reservations
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'fp-resv/v1',
            '/payments/confirm',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleConfirm'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/payments/(?P<id>\d+)/capture',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCapture'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/payments/(?P<id>\d+)/void',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleVoid'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/payments/(?P<id>\d+)/refund',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleRefund'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        );
    }

    public function handleConfirm(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $nonce = $request->get_param('fp_resv_nonce') ?? $request->get_param('_wpnonce');
        if (!is_string($nonce)) {
            $nonce = $request->get_header('X-WP-Nonce');
        }

        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'fp_resv_submit')) {
            return new WP_Error('fp_resv_payment_nonce', __('Verifica di sicurezza non superata.', 'fp-restaurant-reservations'), ['status' => 403]);
        }

        $reservationId = absint((string) $request->get_param('reservation_id'));
        if ($reservationId <= 0) {
            return new WP_Error('fp_resv_payment_reservation', __('Prenotazione non valida.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $paymentId = absint((string) $request->get_param('payment_id'));
        $intentId  = sanitize_text_field((string) $request->get_param('payment_intent'));
        if ($intentId === '') {
            return new WP_Error('fp_resv_payment_intent', __('Identificativo pagamento mancante.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $payment = $paymentId > 0 ? $this->payments->find($paymentId) : $this->payments->findByReservation($reservationId);
        if (!is_array($payment)) {
            return new WP_Error('fp_resv_payment_not_found', __('Pagamento non trovato.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        if ((string) ($payment['external_id'] ?? '') !== $intentId) {
            return new WP_Error('fp_resv_payment_mismatch', __('Il pagamento non corrisponde alla prenotazione indicata.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        try {
            $updated = $this->stripe->refreshPayment((int) $payment['id']);
        } catch (Throwable $exception) {
            Logging::log('payments', 'Stripe confirm error', [
                'reservation_id' => $reservationId,
                'payment_id'     => $payment['id'] ?? null,
                'error'          => $exception->getMessage(),
            ]);

            return new WP_Error('fp_resv_payment_confirm_error', __('Impossibile verificare il pagamento.', 'fp-restaurant-reservations'), ['status' => 500]);
        }

        $reservationStatus = $this->applyReservationStatus((int) ($updated['reservation_id'] ?? $reservationId), $updated['status']);

        return rest_ensure_response([
            'payment'     => $updated,
            'reservation' => [
                'id'     => $reservationId,
                'status' => $reservationStatus,
            ],
        ]);
    }

    public function handleCapture(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        if ($id <= 0) {
            return new WP_Error('fp_resv_payment_id', __('ID pagamento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        try {
            $updated = $this->stripe->capturePayment($id);
        } catch (RuntimeException|Throwable $exception) {
            return new WP_Error('fp_resv_payment_capture', $exception->getMessage(), ['status' => 400]);
        }

        $reservationStatus = $this->applyReservationStatus((int) $updated['reservation_id'], $updated['status']);

        return rest_ensure_response([
            'payment'     => $updated,
            'reservation' => [
                'id'     => (int) $updated['reservation_id'],
                'status' => $reservationStatus,
            ],
        ]);
    }

    public function handleVoid(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        if ($id <= 0) {
            return new WP_Error('fp_resv_payment_id', __('ID pagamento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        try {
            $updated = $this->stripe->voidPayment($id);
        } catch (RuntimeException|Throwable $exception) {
            return new WP_Error('fp_resv_payment_void', $exception->getMessage(), ['status' => 400]);
        }

        $reservationStatus = $this->applyReservationStatus((int) $updated['reservation_id'], $updated['status']);

        return rest_ensure_response([
            'payment'     => $updated,
            'reservation' => [
                'id'     => (int) $updated['reservation_id'],
                'status' => $reservationStatus,
            ],
        ]);
    }

    public function handleRefund(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = absint((string) $request->get_param('id'));
        if ($id <= 0) {
            return new WP_Error('fp_resv_payment_id', __('ID pagamento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $amount = $request->offsetExists('amount') ? (float) $request->get_param('amount') : null;
        if ($amount !== null && $amount < 0) {
            $amount = null;
        }

        try {
            $updated = $this->stripe->refundPayment($id, $amount);
        } catch (RuntimeException|Throwable $exception) {
            return new WP_Error('fp_resv_payment_refund', $exception->getMessage(), ['status' => 400]);
        }

        $reservationStatus = $this->applyReservationStatus((int) $updated['reservation_id'], $updated['status']);

        return rest_ensure_response([
            'payment'     => $updated,
            'reservation' => [
                'id'     => (int) $updated['reservation_id'],
                'status' => $reservationStatus,
            ],
        ]);
    }

    private function checkAdminPermission(): bool
    {
        return current_user_can(Roles::MANAGE_RESERVATIONS);
    }

    private function applyReservationStatus(int $reservationId, string $paymentStatus): ?string
    {
        $status = match ($paymentStatus) {
            StripeService::STATUS_PAID, StripeService::STATUS_AUTHORIZED => 'confirmed',
            StripeService::STATUS_REFUNDED, StripeService::STATUS_VOID   => 'cancelled',
            default                                                     => null,
        };

        if ($status !== null) {
            try {
                $this->reservations->update($reservationId, ['status' => $status]);
            } catch (RuntimeException $exception) {
                Logging::log('payments', 'Failed to update reservation status after payment change', [
                    'reservation_id' => $reservationId,
                    'target_status'  => $status,
                    'error'          => $exception->getMessage(),
                ]);
            }
        }

        return $status;
    }
}
