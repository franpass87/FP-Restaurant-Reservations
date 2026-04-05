<?php

declare(strict_types=1);

/**
 * Form Colors Admin Page
 *
 * @var FP\Resv\Domain\Settings\FormColors $formColors
 */

use FP\Resv\Core\Plugin;

$colors = $formColors->getColors();
$presets = $formColors->getPresets();
$heading_id = 'fp-resv-form-colors-heading';

?>
<div class="wrap fp-resv-admin-outer">
    <h1 class="screen-reader-text" id="<?php echo esc_attr($heading_id); ?>">
        <?php esc_html_e('Personalizza Colori Form', 'fp-restaurant-reservations'); ?>
    </h1>
    <div class="fp-resv-admin fp-resv-admin--form-colors" role="region" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <header class="fp-resv-admin__topbar">
            <div class="fp-resv-admin__identity">
                <nav class="fp-resv-admin__breadcrumbs" aria-label="<?php echo esc_attr__('Percorso', 'fp-restaurant-reservations'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-resv-settings')); ?>">
                        <?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?>
                    </a>
                    <span class="fp-resv-admin__breadcrumb-separator" aria-hidden="true">/</span>
                    <span class="fp-resv-admin__breadcrumb-current">
                        <?php esc_html_e('Colori Form', 'fp-restaurant-reservations'); ?>
                    </span>
                </nav>
                <h2 class="fp-resv-admin__title" aria-hidden="true">
                    <?php esc_html_e('Personalizza Colori Form', 'fp-restaurant-reservations'); ?>
                </h2>
                <p class="fp-resv-admin__subtitle">
                    <?php esc_html_e('Scegli i colori per il form di prenotazione con anteprima in tempo reale', 'fp-restaurant-reservations'); ?>
                </p>
            </div>
            <div class="fp-resv-admin__actions">
                <span class="fpresv-page-header-badge">v<?php echo esc_html(Plugin::VERSION); ?></span>
            </div>
        </header>

        <main class="fp-resv-admin__main">
            <?php
            $saved_flag = isset($_GET['fp_resv_colors_saved']);
            $reset_flag = isset($_GET['fp_resv_colors_reset']);
            if ($saved_flag || $reset_flag) :
                ?>
                <div class="fp-resv-settings__notices">
                    <?php if ($saved_flag) : ?>
                        <div class="notice notice-success is-dismissible">
                            <p><?php esc_html_e('Colori salvati con successo!', 'fp-restaurant-reservations'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($reset_flag) : ?>
                        <div class="notice notice-success is-dismissible">
                            <p><?php esc_html_e('Colori ripristinati ai valori predefiniti!', 'fp-restaurant-reservations'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="fp-resv-form-colors-container">
                <div class="fp-resv-form-colors-controls fp-resv-surface">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fp-resv-form-colors-form">
                        <?php wp_nonce_field('fp_resv_save_form_colors', 'fp_resv_colors_nonce'); ?>
                        <input type="hidden" name="action" value="fp_resv_save_form_colors">

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Preset Rapidi', 'fp-restaurant-reservations'); ?></h3>
                            <p class="description">
                                <?php esc_html_e('Seleziona un preset per iniziare rapidamente', 'fp-restaurant-reservations'); ?>
                            </p>
                            <div class="fp-resv-presets">
                                <?php foreach ($presets as $presetKey => $preset) : ?>
                                    <button
                                        type="button"
                                        class="fp-resv-preset-btn button"
                                        data-preset="<?php echo esc_attr($presetKey); ?>"
                                        data-colors="<?php echo esc_attr(wp_json_encode($preset['colors'])); ?>"
                                    >
                                        <?php echo esc_html($preset['name']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Colori Principali', 'fp-restaurant-reservations'); ?></h3>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_primary">
                                    <?php esc_html_e('Colore Primario', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[primary]"
                                        id="fp_color_primary"
                                        value="<?php echo esc_attr($colors['primary']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['primary']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_primary"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale colore primario', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                                <span class="description"><?php esc_html_e('Bottoni principali, link, accenti', 'fp-restaurant-reservations'); ?></span>
                            </div>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_primary_hover">
                                    <?php esc_html_e('Colore Hover', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[primary_hover]"
                                        id="fp_color_primary_hover"
                                        value="<?php echo esc_attr($colors['primary_hover']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['primary_hover']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_primary_hover"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale hover', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                                <span class="description"><?php esc_html_e('Al passaggio del mouse', 'fp-restaurant-reservations'); ?></span>
                            </div>
                        </div>

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Colori Sfondo', 'fp-restaurant-reservations'); ?></h3>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_surface">
                                    <?php esc_html_e('Sfondo Principale', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[surface]"
                                        id="fp_color_surface"
                                        value="<?php echo esc_attr($colors['surface']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['surface']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_surface"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale sfondo principale', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_surface_alt">
                                    <?php esc_html_e('Sfondo Alternativo', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[surface_alt]"
                                        id="fp_color_surface_alt"
                                        value="<?php echo esc_attr($colors['surface_alt']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['surface_alt']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_surface_alt"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale sfondo alternativo', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                                <span class="description"><?php esc_html_e('Card e sezioni', 'fp-restaurant-reservations'); ?></span>
                            </div>
                        </div>

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Colori Testo', 'fp-restaurant-reservations'); ?></h3>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_text">
                                    <?php esc_html_e('Testo Principale', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[text]"
                                        id="fp_color_text"
                                        value="<?php echo esc_attr($colors['text']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['text']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_text"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale testo principale', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_text_muted">
                                    <?php esc_html_e('Testo Secondario', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[text_muted]"
                                        id="fp_color_text_muted"
                                        value="<?php echo esc_attr($colors['text_muted']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['text_muted']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_text_muted"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale testo secondario', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Colori Bordi', 'fp-restaurant-reservations'); ?></h3>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_border">
                                    <?php esc_html_e('Bordo Principale', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[border]"
                                        id="fp_color_border"
                                        value="<?php echo esc_attr($colors['border']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['border']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_border"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale bordo', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="fp-resv-color-section">
                            <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Colori Bottoni', 'fp-restaurant-reservations'); ?></h3>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_button_bg">
                                    <?php esc_html_e('Sfondo Bottone', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[button_bg]"
                                        id="fp_color_button_bg"
                                        value="<?php echo esc_attr($colors['button_bg']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['button_bg']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_button_bg"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale sfondo bottone', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>

                            <div class="fp-resv-color-field">
                                <label for="fp_color_button_text">
                                    <?php esc_html_e('Testo Bottone', 'fp-restaurant-reservations'); ?>
                                </label>
                                <div class="fp-resv-color-field__inputs">
                                    <input
                                        type="color"
                                        name="colors[button_text]"
                                        id="fp_color_button_text"
                                        value="<?php echo esc_attr($colors['button_text']); ?>"
                                        class="fp-color-picker"
                                    >
                                    <input
                                        type="text"
                                        value="<?php echo esc_attr($colors['button_text']); ?>"
                                        class="fp-color-text regular-text code"
                                        data-for="fp_color_button_text"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        aria-label="<?php echo esc_attr__('Valore esadecimale testo bottone', 'fp-restaurant-reservations'); ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="fp-resv-form-colors-actions">
                            <?php submit_button(__('Salva Colori', 'fp-restaurant-reservations'), 'primary', 'submit', false); ?>
                            <a
                                href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fp_resv_reset_form_colors'), 'fp_resv_reset_form_colors')); ?>"
                                class="button button-secondary"
                                onclick="return confirm('<?php echo esc_js(__('Ripristinare i colori predefiniti?', 'fp-restaurant-reservations')); ?>');"
                            >
                                <?php esc_html_e('Ripristina Default', 'fp-restaurant-reservations'); ?>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="fp-resv-form-colors-preview fp-resv-surface">
                    <div class="fp-resv-preview-header">
                        <h3 class="fp-resv-form-colors-section-title"><?php esc_html_e('Anteprima Live', 'fp-restaurant-reservations'); ?></h3>
                        <p class="description"><?php esc_html_e('I cambiamenti vengono mostrati in tempo reale', 'fp-restaurant-reservations'); ?></p>
                    </div>
                    <div class="fp-resv-preview-wrapper">
                        <iframe
                            id="fp-resv-preview-iframe"
                            title="<?php echo esc_attr__('Anteprima form prenotazione', 'fp-restaurant-reservations'); ?>"
                            frameborder="0"
                        ></iframe>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
