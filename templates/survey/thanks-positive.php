<?php
/**
 * Positive survey thank-you template.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    $context = [];
}

$result    = isset($context['result']) && is_array($context['result']) ? $context['result'] : [];
$strings   = isset($context['strings']) && is_array($context['strings']) ? $context['strings'] : [];
$positive  = is_array($strings['positive'] ?? null) ? $strings['positive'] : [];
$reviewUrl = isset($context['reviewUrl']) ? (string) $context['reviewUrl'] : ($result['review_url'] ?? '');

$headline = (string) ($positive['headline'] ?? __('Grazie per il tuo feedback!', 'fp-restaurant-reservations'));
$body     = (string) ($positive['body'] ?? __("Siamo felici che la tua esperienza sia stata all'altezza delle aspettative.", 'fp-restaurant-reservations'));
$cta      = (string) ($positive['cta'] ?? __('Lascia una recensione su Google', 'fp-restaurant-reservations'));
?>
<div class="fp-resv-survey__thanks fp-resv-survey__thanks--positive">
    <h3><?php echo esc_html($headline); ?></h3>
    <p><?php echo esc_html($body); ?></p>
    <?php if ($reviewUrl !== '') : ?>
        <p>
            <a class="fp-resv-button fp-resv-button--accent" href="<?php echo esc_url($reviewUrl); ?>" target="_blank" rel="noopener">
                <?php echo esc_html($cta); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
