<?php
/**
 * Reservation survey form template.
 *
 * @var array<string, mixed> $context
 */

if (!isset($context) || !is_array($context)) {
    $context = [];
}

$action         = isset($context['action']) ? (string) $context['action'] : rest_url('fp-resv/v1/surveys');
$reservationId  = isset($context['reservation_id']) ? (int) $context['reservation_id'] : 0;
$email          = isset($context['email']) ? (string) $context['email'] : '';
$token          = isset($context['token']) ? (string) $context['token'] : '';
$strings        = isset($context['strings']) && is_array($context['strings']) ? $context['strings'] : [];
$labels         = $strings['labels'] ?? [];
$actions        = $strings['actions'] ?? [];

?>
<form class="fp-resv-survey" method="post" action="<?php echo esc_url($action); ?>" data-fp-resv-survey>
    <input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) $reservationId); ?>">
    <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
    <?php wp_nonce_field('fp_resv_submit_survey', 'fp_resv_survey_nonce'); ?>

    <fieldset class="fp-resv-survey__group">
        <legend><?php echo esc_html($labels['headline'] ?? __('Com\'è andata la tua esperienza?', 'fp-restaurant-reservations')); ?></legend>
        <label>
            <span><?php echo esc_html($labels['food'] ?? __('Cibo', 'fp-restaurant-reservations')); ?></span>
            <input type="number" name="stars_food" min="1" max="5" required>
        </label>
        <label>
            <span><?php echo esc_html($labels['service'] ?? __('Servizio', 'fp-restaurant-reservations')); ?></span>
            <input type="number" name="stars_service" min="1" max="5" required>
        </label>
        <label>
            <span><?php echo esc_html($labels['atmosphere'] ?? __('Atmosfera', 'fp-restaurant-reservations')); ?></span>
            <input type="number" name="stars_atmosphere" min="1" max="5" required>
        </label>
    </fieldset>

    <fieldset class="fp-resv-survey__group">
        <legend><?php echo esc_html($labels['nps'] ?? __('Quanto consiglieresti il ristorante ad amici o colleghi? (0-10)', 'fp-restaurant-reservations')); ?></legend>
        <input type="number" name="nps" min="0" max="10" required>
    </fieldset>

    <label class="fp-resv-survey__group">
        <span><?php echo esc_html($labels['comment'] ?? __('Note aggiuntive', 'fp-restaurant-reservations')); ?></span>
        <textarea name="comment" rows="4" placeholder="<?php echo esc_attr($labels['comment_placeholder'] ?? __('Raccontaci di più...', 'fp-restaurant-reservations')); ?>"></textarea>
    </label>

    <button type="submit" class="fp-resv-button fp-resv-button--primary">
        <?php echo esc_html($actions['submit'] ?? __('Invia feedback', 'fp-restaurant-reservations')); ?>
    </button>

    <div class="fp-resv-survey__message" data-fp-resv-survey-result hidden></div>
</form>
