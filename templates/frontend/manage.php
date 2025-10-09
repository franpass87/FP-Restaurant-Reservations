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

// Traduzioni dalla lingua del cliente
$managePage = $strings['manage_page'] ?? [];

?>
<div class="fp-resv-manage fp-resv fp-card">
    <header class="fp-resv-manage__header fp-section">
        <div class="fp-resv-manage__title-wrapper">
            <h2 class="fp-resv-manage__title"><?php echo esc_html($managePage['title'] ?? 'Gestisci prenotazione'); ?></h2>
            <?php
            $statusLabels = $managePage['status_labels'] ?? [];
            $statusBadgeClass = '';
            $statusLabel = '';
            switch ($status) {
                case 'confirmed':
                    $statusBadgeClass = 'fp-badge--success';
                    $statusLabel = $statusLabels['confirmed'] ?? 'Confermata';
                    break;
                case 'pending':
                    $statusBadgeClass = 'fp-badge--warning';
                    $statusLabel = $statusLabels['pending'] ?? 'In attesa';
                    break;
                case 'cancelled':
                    $statusBadgeClass = 'fp-badge--error';
                    $statusLabel = $statusLabels['cancelled'] ?? 'Annullata';
                    break;
                case 'visited':
                    $statusBadgeClass = 'fp-badge--visited';
                    $statusLabel = $statusLabels['visited'] ?? 'Visitata';
                    break;
                case 'no-show':
                    $statusBadgeClass = 'fp-badge--no-show';
                    $statusLabel = $statusLabels['no-show'] ?? 'No-show';
                    break;
                default:
                    $statusLabel = $status;
            }
            if ($statusLabel !== '') : ?>
                <span class="fp-badge fp-resv-manage__status-badge <?php echo esc_attr($statusBadgeClass); ?>">
                    <?php echo esc_html($statusLabel); ?>
                </span>
            <?php endif; ?>
        </div>
        <p class="fp-resv-manage__subtitle"><?php echo esc_html(sprintf($managePage['subtitle'] ?? 'Prenotazione #%d', $id)); ?></p>
    </header>
    <section class="fp-resv-manage__body fp-section">
        <?php if ($notice !== '') : ?>
            <aside class="fp-alert fp-alert--info" role="status">
                <p><?php echo esc_html($notice); ?></p>
            </aside>
        <?php endif; ?>
        <dl class="fp-resv-summary__list">
            <div>
                <dt>
                    <span class="fp-resv-manage__icon" aria-hidden="true">📅</span>
                    <?php echo esc_html($managePage['fields']['date'] ?? 'Data'); ?>
                </dt>
                <dd><?php echo esc_html($date); ?> • <?php echo esc_html($time); ?></dd>
            </div>
            <div>
                <dt>
                    <span class="fp-resv-manage__icon" aria-hidden="true">👥</span>
                    <?php echo esc_html($managePage['fields']['party'] ?? 'Coperti'); ?>
                </dt>
                <dd><?php echo esc_html((string) $party); ?></dd>
            </div>
            <div>
                <dt>
                    <span class="fp-resv-manage__icon" aria-hidden="true">👤</span>
                    <?php echo esc_html($managePage['fields']['customer'] ?? 'Cliente'); ?>
                </dt>
                <dd><?php echo esc_html(trim($firstName . ' ' . $lastName)); ?></dd>
            </div>
            <div>
                <dt>
                    <span class="fp-resv-manage__icon" aria-hidden="true">📧</span>
                    <?php echo esc_html($managePage['fields']['contacts'] ?? 'Contatti'); ?>
                </dt>
                <dd>
                    <a href="mailto:<?php echo esc_attr($email); ?>" class="fp-resv-manage__contact-link"><?php echo esc_html($email); ?></a>
                    <?php if ($phone !== '') : ?>
                        <br>
                        <a href="tel:<?php echo esc_attr($phone); ?>" class="fp-resv-manage__contact-link">
                            <span class="fp-resv-manage__icon fp-resv-manage__icon--inline" aria-hidden="true">📱</span>
                            <?php echo esc_html($phone); ?>
                        </a>
                    <?php endif; ?>
                </dd>
            </div>
            <?php if ($notes !== '') : ?>
                <div>
                    <dt>
                        <span class="fp-resv-manage__icon" aria-hidden="true">📝</span>
                        <?php echo esc_html($managePage['fields']['notes'] ?? 'Note'); ?>
                    </dt>
                    <dd><?php echo nl2br(esc_html($notes)); ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($allergies !== '') : ?>
                <div class="fp-resv-manage__allergies">
                    <dt>
                        <span class="fp-resv-manage__icon" aria-hidden="true">⚠️</span>
                        <?php echo esc_html($managePage['fields']['allergies'] ?? 'Allergie'); ?>
                    </dt>
                    <dd><?php echo nl2br(esc_html($allergies)); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <div class="fp-resv-manage__info-box">
            <span class="fp-resv-manage__info-icon" aria-hidden="true">ℹ️</span>
            <p class="fp-resv-summary__disclaimer fp-hint"><?php echo esc_html($managePage['info_message'] ?? 'Per modifiche o annullo contatta il ristorante rispondendo alla mail di conferma.'); ?></p>
        </div>
        <hr>
        <?php $actionsAllowed = !in_array($status, ['cancelled', 'visited', 'no-show'], true); ?>
        <form method="post" class="fp-resv-manage__actions" <?php echo $actionsAllowed ? '' : 'aria-disabled="true"'; ?> id="fp-resv-manage-form">
            <fieldset class="fp-fieldset">
                <legend><?php echo esc_html__('Richiedi un’azione', 'fp-restaurant-reservations'); ?></legend>
                <div class="fp-resv-fields fp-resv-fields--grid">
                    <label class="fp-resv-field fp-field fp-resv-field--select">
                        <span><?php echo esc_html($actions['select_action'] ?? 'Seleziona azione'); ?></span>
                        <select class="fp-input" name="fp_resv_action" id="fp_resv_action" required <?php echo $actionsAllowed ? '' : 'disabled'; ?>>
                            <option value=""><?php echo esc_html($actions['select_placeholder'] ?? '-- Seleziona un\'azione --'); ?></option>
                            <option value="cancel_request"><?php echo esc_html($actions['cancel_request'] ?? 'Richiedi annullo'); ?></option>
                            <option value="change_time_request"><?php echo esc_html($actions['change_time_request'] ?? 'Richiedi modifica orario'); ?></option>
                        </select>
                    </label>
                    <label class="fp-resv-field fp-field fp-resv-field--time" id="fp_resv_time_field" style="display: none;">
                        <span><?php echo esc_html($actions['desired_time'] ?? 'Nuovo orario desiderato'); ?></span>
                        <input class="fp-input" type="time" name="fp_resv_desired_time" id="fp_resv_desired_time" value="" <?php echo $actionsAllowed ? '' : 'disabled'; ?>>
                        <span class="fp-hint"><?php echo esc_html($actions['desired_time_hint'] ?? 'Indica l\'orario preferito per la tua prenotazione'); ?></span>
                    </label>
                    <label class="fp-resv-field fp-field">
                        <span><?php echo esc_html($actions['staff_note'] ?? 'Nota per lo staff (opzionale)'); ?></span>
                        <textarea class="fp-textarea" name="fp_resv_user_note" id="fp_resv_user_note" rows="3" placeholder="<?php echo esc_attr($actions['staff_note_placeholder'] ?? 'Aggiungi eventuali dettagli o richieste specifiche...'); ?>" <?php echo $actionsAllowed ? '' : 'disabled'; ?>></textarea>
                    </label>
                </div>
            </fieldset>
            <div class="fp-resv-manage__actions-footer">
                <button type="submit" class="fp-btn fp-btn--primary" id="fp-resv-submit-btn" <?php echo $actionsAllowed ? '' : 'disabled'; ?>>
                    <span class="fp-btn__label"><?php echo esc_html($actions['submit'] ?? 'Invia richiesta'); ?></span>
                </button>
                <?php if (!$actionsAllowed) : ?>
                    <p class="fp-hint fp-resv-manage__disabled-hint"><?php echo esc_html($actions['disabled_hint'] ?? 'Le azioni non sono disponibili per questo stato.'); ?></p>
                <?php endif; ?>
            </div>
        </form>
        <script>
        (function() {
            var actionSelect = document.getElementById('fp_resv_action');
            var timeField = document.getElementById('fp_resv_time_field');
            var timeInput = document.getElementById('fp_resv_desired_time');
            var form = document.getElementById('fp-resv-manage-form');
            
            if (actionSelect && timeField && timeInput) {
                actionSelect.addEventListener('change', function() {
                    if (this.value === 'change_time_request') {
                        timeField.style.display = '';
                        timeInput.setAttribute('required', 'required');
                    } else {
                        timeField.style.display = 'none';
                        timeInput.removeAttribute('required');
                        timeInput.value = '';
                    }
                });
            }
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    var action = actionSelect ? actionSelect.value : '';
                    if (!action) {
                        e.preventDefault();
                        alert('<?php $validation = $managePage['validation'] ?? []; echo esc_js($validation['select_action'] ?? 'Seleziona un\'azione prima di inviare.'); ?>');
                        return false;
                    }
                    if (action === 'change_time_request' && timeInput && !timeInput.value) {
                        e.preventDefault();
                        alert('<?php echo esc_js($validation['specify_time'] ?? 'Specifica il nuovo orario desiderato.'); ?>');
                        timeInput.focus();
                        return false;
                    }
                    return confirm('<?php echo esc_js($validation['confirm_request'] ?? 'Confermi l\'invio della richiesta?'); ?>');
                });
            }
        })();
        </script>
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


