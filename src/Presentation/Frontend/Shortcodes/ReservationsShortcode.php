<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\Frontend\Shortcodes;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Frontend\ShortcodeRenderer;
use function wp_verify_nonce;
use function wp_nonce_field;
use function sanitize_text_field;
use function sanitize_email;
use function sanitize_textarea_field;
use function absint;
use function esc_html;

/**
 * Reservations Shortcode
 * 
 * Frontend shortcode that uses Use Cases for business logic.
 * This is a thin presentation layer.
 *
 * @package FP\Resv\Presentation\Frontend\Shortcodes
 */
final class ReservationsShortcode
{
    private ?ShortcodeRenderer $renderer = null;

    public function __construct(
        private readonly CreateReservationUseCase $createUseCase,
        private readonly LoggerInterface $logger
    ) {
    }
    
    private function getRenderer(): ShortcodeRenderer
    {
        if ($this->renderer === null) {
            $this->renderer = new ShortcodeRenderer();
        }
        return $this->renderer;
    }
    
    /**
     * Render shortcode
     * 
     * @param array<string, mixed> $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered HTML
     */
    public function render(array $atts = [], ?string $content = null): string
    {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fp_resv_submit'])) {
            return $this->handleSubmission();
        }
        
        // Render form
        return $this->renderForm($atts);
    }
    
    /**
     * Handle form submission
     * 
     * @return string Result message or form
     */
    private function handleSubmission(): string
    {
        // Verify nonce
        if (!isset($_POST['fp_resv_nonce']) || !wp_verify_nonce($_POST['fp_resv_nonce'], 'fp_resv_submit')) {
            return '<div class="fp-resv-error">Security check failed. Please try again.</div>' . $this->renderForm();
        }
        
        try {
            // Sanitize and prepare data
            $data = [
                'date' => sanitize_text_field($_POST['date'] ?? ''),
                'time' => sanitize_text_field($_POST['time'] ?? ''),
                'party' => absint($_POST['party'] ?? 0),
                'meal' => sanitize_text_field($_POST['meal'] ?? 'dinner'),
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'allergies' => sanitize_textarea_field($_POST['allergies'] ?? ''),
            ];
            
            // Execute use case
            $reservation = $this->createUseCase->execute($data);
            
            // Success message
            return '<div class="fp-resv-success">
                <h3>Reservation Confirmed!</h3>
                <p>Your reservation for ' . esc_html($reservation->getDate()) . ' at ' . esc_html($reservation->getTime()) . ' has been confirmed.</p>
                <p>Confirmation ID: ' . esc_html((string) $reservation->getId()) . '</p>
            </div>';
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $errorHtml = '<div class="fp-resv-error"><ul>';
            foreach ($errors as $field => $message) {
                $errorHtml .= '<li>' . esc_html(ucfirst($field) . ': ' . $message) . '</li>';
            }
            $errorHtml .= '</ul></div>';
            
            return $errorHtml . $this->renderForm();
        } catch (\Throwable $e) {
            $this->logger->error('Shortcode reservation creation error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            
            return '<div class="fp-resv-error">An error occurred. Please try again later.</div>' . $this->renderForm();
        }
    }
    
    /**
     * Render reservation form
     * 
     * @param array<string, mixed> $atts Attributes
     * @return string Form HTML
     */
    private function renderForm(array $atts = []): string
    {
        // Use the existing ShortcodeRenderer for form rendering
        // This maintains compatibility with the existing form template system
        return $this->getRenderer()->render($atts);
    }
}

