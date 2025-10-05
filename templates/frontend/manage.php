<?php
/**
 * Frontend reservation manage page.
 *
 * @var array<string, mixed> $context
 * @var array<string, mixed> $strings
 */

if (!isset($context) || !is_array($context)) {
    return;
}

$id        = (int) ($context['id'] ?? 0);
$date      = (string) ($context['date'] ?? '');
$time      = (string) ($context['time'] ?? '');
$party     = (int) ($context['party'] ?? 0);
$status    = (string) ($context['status'] ?? '');
$firstName = (string) ($context['first_name'] ?? '');
$lastName  = (string) ($context['last_name'] ?? '');
$email     = (string) ($context['email'] ?? '');
$phone     = (string) ($context['phone'] ?? '');
$notes     = (string) ($context['notes'] ?? '');
$allergies = (string) ($context['allergies'] ?? '');
$notice    = (string) ($context['notice'] ?? '');

?>
<div class="fp-resv-manage fp-resv fp-card">
    <header class="fp-resv-manage__header fp-section">
        <h2 class="fp-resv-manage__title"><?php echo esc_html__('Gestisci prenotazione', 'fp-restaurant-reservations'); ?></h2>
        <p class="fp-resv-manage__subtitle"><?php echo esc_html(sprintf(__('Prenotazione #%d', 'fp-restaurant-reservations'), $id)); ?></p>
    </header>
    <section class="fp-resv-manage__body fp-section">
        <?php if ($notice !== '') : ?>
            <aside class="fp-alert fp-alert--info" role="status">
                <p><?php echo esc_html($notice); ?></p>
            </aside>
        <?php endif; ?>
        <dl class="fp-resv-summary__list">
            <div>
                <dt><?php echo esc_html__('Data', 'fp-restaurant-reservations'); ?></dt>
                <dd><?php echo esc_html($date); ?> • <?php echo esc_html($time); ?></dd>
            </div>
            <div>
                <dt><?php echo esc_html__('Coperti', 'fp-restaurant-reservations'); ?></dt>
                <dd><?php echo esc_html((string) $party); ?></dd>
            </div>
            <div>
                <dt><?php echo esc_html__('Cliente', 'fp-restaurant-reservations'); ?></dt>
                <dd><?php echo esc_html(trim($firstName . ' ' . $lastName)); ?></dd>
            </div>
            <div>
                <dt><?php echo esc_html__('Contatti', 'fp-restaurant-reservations'); ?></dt>
                <dd>
                    <?php echo esc_html($email); ?>
                    <?php if ($phone !== '') : ?> / <?php echo esc_html($phone); ?><?php endif; ?>
                </dd>
            </div>
            <?php if ($notes !== '') : ?>
                <div>
                    <dt><?php echo esc_html__('Note', 'fp-restaurant-reservations'); ?></dt>
                    <dd><?php echo nl2br(esc_html($notes)); ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($allergies !== '') : ?>
                <div>
                    <dt><?php echo esc_html__('Allergie', 'fp-restaurant-reservations'); ?></dt>
                    <dd><?php echo nl2br(esc_html($allergies)); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <p class="fp-resv-summary__disclaimer fp-hint"><?php echo esc_html__('Per modifiche o annullo contatta il ristorante rispondendo alla mail di conferma.', 'fp-restaurant-reservations'); ?></p>
        <hr>
        <?php $actionsAllowed = !in_array($status, ['cancelled', 'visited', 'no-show'], true); ?>
        <form method="post" class="fp-resv-manage__actions" <?php echo $actionsAllowed ? '' : 'aria-disabled="true"'; ?>>
            <fieldset class="fp-fieldset">
                <legend><?php echo esc_html__('Richiedi un’azione', 'fp-restaurant-reservations'); ?></legend>
                <div class="fp-resv-fields fp-resv-fields--grid">
                    <label class="fp-resv-field fp-field fp-resv-field--select">
                        <span><?php echo esc_html__('Seleziona azione', 'fp-restaurant-reservations'); ?></span>
                        <select class="fp-input" name="fp_resv_action" required <?php echo $actionsAllowed ? '' : 'disabled'; ?>>
                            <option value="">--</option>
                            <option value="cancel_request"><?php echo esc_html__('Richiedi annullo', 'fp-restaurant-reservations'); ?></option>
                            <option value="change_time_request"><?php echo esc_html__('Richiedi modifica orario', 'fp-restaurant-reservations'); ?></option>
                        </select>
                    </label>
                    <label class="fp-resv-field fp-field">
                        <span><?php echo esc_html__('Nuovo orario desiderato (opzionale)', 'fp-restaurant-reservations'); ?></span>
                        <input class="fp-input" type="time" name="fp_resv_desired_time" value="" <?php echo $actionsAllowed ? '' : 'disabled'; ?>>
                    </label>
                    <label class="fp-resv-field fp-field">
                        <span><?php echo esc_html__('Nota per lo staff (opzionale)', 'fp-restaurant-reservations'); ?></span>
                        <textarea class="fp-textarea" name="fp_resv_user_note" rows="2" <?php echo $actionsAllowed ? '' : 'disabled'; ?>></textarea>
                    </label>
                </div>
            </fieldset>
            <button type="submit" class="fp-btn fp-btn--primary" <?php echo $actionsAllowed ? '' : 'disabled'; ?>><?php echo esc_html__('Invia richiesta', 'fp-restaurant-reservations'); ?></button>
            <?php if (!$actionsAllowed) : ?>
                <p class="fp-hint"><?php echo esc_html__('Le azioni non sono disponibili per questo stato.', 'fp-restaurant-reservations'); ?></p>
            <?php endif; ?>
        </form>
        <?php
        $options = \FP\Resv\Core\ServiceContainer::getInstance()->get(\FP\Resv\Domain\Settings\Options::class);
        $general = $options instanceof \FP\Resv\Domain\Settings\Options ? $options->getGroup('fp_resv_general', []) : [];
        $manageNotice = (string) ($general['manage_requests_notice'] ?? '');
        if ($manageNotice !== '') : ?>
            <p class="fp-hint" style="margin-top:8px;">
                <?php echo nl2br(esc_html($manageNotice)); ?>
            </p>
        <?php endif; ?>
    </section>
</div>


