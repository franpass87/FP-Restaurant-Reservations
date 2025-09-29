<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $previewData
 */

$contrast = isset($previewData['contrast']) && is_array($previewData['contrast']) ? $previewData['contrast'] : [];
$hash     = isset($previewData['hash']) ? (string) $previewData['hash'] : '';
$encoded  = wp_json_encode($contrast);
if (!is_string($encoded)) {
    $encoded = '[]';
}
?>
<div
    class="fp-resv-style-preview"
    data-fp-resv-style-preview
    data-contrast="<?php echo esc_attr($encoded); ?>"
>
    <header class="fp-resv-style-preview__header">
        <div>
            <h2><?php echo esc_html__('Anteprima live', 'fp-restaurant-reservations'); ?></h2>
            <p class="fp-resv-style-preview__subtitle">
                <?php echo esc_html__('Aggiorna i campi a sinistra per vedere in tempo reale colori, tipografia e pulsanti del form.', 'fp-restaurant-reservations'); ?>
            </p>
        </div>
    </header>
    <div class="fp-resv-style-preview__widget">
        <div class="fp-resv-widget" id="fp-resv-style-preview-widget" data-style-hash="<?php echo esc_attr($hash); ?>">
            <div class="fp-resv-widget__topbar">
                <div class="fp-resv-widget__titles">
                    <h2 class="fp-resv-widget__headline">Cena per due</h2>
                    <p class="fp-resv-widget__subheadline">Scegli data, orario e completa i tuoi dati.</p>
                </div>
                <a class="fp-resv-widget__pdf" href="#">
                    <?php echo esc_html__('Scarica PDF menù', 'fp-restaurant-reservations'); ?>
                </a>
            </div>
            <form class="fp-resv-widget__form" action="#" method="post">
                <ol class="fp-resv-widget__steps">
                    <li class="fp-resv-step" data-step="date">
                        <header class="fp-resv-step__header">
                            <span class="fp-resv-step__label"><?php echo esc_html__('Step 1', 'fp-restaurant-reservations'); ?></span>
                            <h3 class="fp-resv-step__title"><?php echo esc_html__('Scegli il giorno', 'fp-restaurant-reservations'); ?></h3>
                            <p class="fp-resv-step__description"><?php echo esc_html__('Seleziona la data preferita', 'fp-restaurant-reservations'); ?></p>
                        </header>
                        <div class="fp-resv-step__body">
                            <label class="fp-resv-field">
                                <span><?php echo esc_html__('Data', 'fp-restaurant-reservations'); ?></span>
                                <input type="date" value="2024-07-12" readonly>
                            </label>
                            <label class="fp-resv-field">
                                <span><?php echo esc_html__('Orario', 'fp-restaurant-reservations'); ?></span>
                                <input type="time" value="20:00" readonly>
                            </label>
                        </div>
                        <footer class="fp-resv-step__footer">
                            <button type="button" class="fp-resv-button fp-resv-button--primary"><?php echo esc_html__('Prosegui', 'fp-restaurant-reservations'); ?></button>
                        </footer>
                    </li>
                    <li class="fp-resv-step" data-step="slots">
                        <header class="fp-resv-step__header">
                            <span class="fp-resv-step__label"><?php echo esc_html__('Step 2', 'fp-restaurant-reservations'); ?></span>
                            <h3 class="fp-resv-step__title"><?php echo esc_html__('Disponibilità orari', 'fp-restaurant-reservations'); ?></h3>
                        </header>
                        <div class="fp-resv-step__body">
                            <div class="fp-resv-slots">
                                <p class="fp-resv-slots__status"><?php echo esc_html__('Tre orari disponibili', 'fp-restaurant-reservations'); ?></p>
                                <ul class="fp-resv-slots__list">
                                    <li><button type="button">19:30</button></li>
                                    <li><button type="button" aria-pressed="true">20:00</button></li>
                                    <li><button type="button">20:30</button></li>
                                </ul>
                                <p class="fp-resv-slots__empty" hidden><?php echo esc_html__('Nessun orario libero', 'fp-restaurant-reservations'); ?></p>
                            </div>
                        </div>
                        <footer class="fp-resv-step__footer">
                            <button type="button" class="fp-resv-button fp-resv-button--ghost"><?php echo esc_html__('Indietro', 'fp-restaurant-reservations'); ?></button>
                            <button type="button" class="fp-resv-button fp-resv-button--primary"><?php echo esc_html__('Continua', 'fp-restaurant-reservations'); ?></button>
                        </footer>
                    </li>
                    <li class="fp-resv-step" data-step="confirm">
                        <header class="fp-resv-step__header">
                            <span class="fp-resv-step__label"><?php echo esc_html__('Step 3', 'fp-restaurant-reservations'); ?></span>
                            <h3 class="fp-resv-step__title"><?php echo esc_html__('Riepilogo', 'fp-restaurant-reservations'); ?></h3>
                        </header>
                        <div class="fp-resv-step__body">
                            <section class="fp-resv-summary">
                                <h4 class="fp-resv-summary__title"><?php echo esc_html__('Dettagli prenotazione', 'fp-restaurant-reservations'); ?></h4>
                                <dl class="fp-resv-summary__list">
                                    <div>
                                        <dt><?php echo esc_html__('Data', 'fp-restaurant-reservations'); ?></dt>
                                        <dd>12/07/2024</dd>
                                    </div>
                                    <div>
                                        <dt><?php echo esc_html__('Orario', 'fp-restaurant-reservations'); ?></dt>
                                        <dd>20:00</dd>
                                    </div>
                                    <div>
                                        <dt><?php echo esc_html__('Coperti', 'fp-restaurant-reservations'); ?></dt>
                                        <dd>2</dd>
                                    </div>
                                    <div>
                                        <dt><?php echo esc_html__('Contatto', 'fp-restaurant-reservations'); ?></dt>
                                        <dd>Francesco • +39 333 1234567</dd>
                                    </div>
                                </dl>
                                <p class="fp-resv-summary__disclaimer"><?php echo esc_html__('Ti invieremo conferma via email entro pochi minuti.', 'fp-restaurant-reservations'); ?></p>
                            </section>
                        </div>
                        <footer class="fp-resv-step__footer">
                            <button type="button" class="fp-resv-button fp-resv-button--primary"><?php echo esc_html__('Conferma prenotazione', 'fp-restaurant-reservations'); ?></button>
                        </footer>
                    </li>
                </ol>
            </form>
        </div>
    </div>
    <section class="fp-resv-style-preview__contrast" data-contrast-list>
        <?php foreach ($contrast as $item) :
            $ratio = isset($item['ratio']) ? (float) $item['ratio'] : 0.0;
            $grade = isset($item['grade']) ? (string) $item['grade'] : 'AA';
            $isCompliant = !empty($item['is_compliant']);
            ?>
            <article
                class="fp-resv-style-preview__contrast-item<?php echo $isCompliant ? '' : ' is-warning'; ?>"
                data-contrast-item="<?php echo esc_attr((string) ($item['id'] ?? '')); ?>"
            >
                <span
                    class="fp-resv-style-preview__swatch"
                    style="background: <?php echo esc_attr((string) ($item['background'] ?? '#000000')); ?>; color: <?php echo esc_attr((string) ($item['foreground'] ?? '#ffffff')); ?>;"
                >
                    Aa
                </span>
                <div class="fp-resv-style-preview__contrast-copy">
                    <h4><?php echo esc_html((string) ($item['label'] ?? '')); ?></h4>
                    <p>
                        <span class="fp-resv-style-preview__ratio"><?php echo esc_html(sprintf('%.2f', $ratio)); ?></span>
                        <span class="fp-resv-style-preview__grade"><?php echo esc_html($grade); ?></span>
                    </p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
