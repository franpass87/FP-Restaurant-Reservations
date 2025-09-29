<?php
/**
 * Negative survey thank-you template.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    $context = [];
}

$strings  = isset($context['strings']) && is_array($context['strings']) ? $context['strings'] : [];
$negative = is_array($strings['negative'] ?? null) ? $strings['negative'] : [];

$headline = (string) ($negative['headline'] ?? __('Grazie per averci raccontato cosa non ha funzionato.', 'fp-restaurant-reservations'));
$body     = (string) ($negative['body'] ?? __('Il nostro staff analizzerà subito il tuo feedback e ti contatterà per trovare la soluzione migliore.', 'fp-restaurant-reservations'));
?>
<div class="fp-resv-survey__thanks fp-resv-survey__thanks--negative">
    <h3><?php echo esc_html($headline); ?></h3>
    <p><?php echo esc_html($body); ?></p>
</div>
