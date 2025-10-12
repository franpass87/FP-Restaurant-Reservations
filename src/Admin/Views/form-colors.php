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
$version = Plugin::assetVersion();

?>
<div class="fp-resv-form-colors-admin">
    <div class="fp-resv-admin__topbar">
        <div class="fp-resv-admin__identity">
            <nav class="fp-resv-admin__breadcrumbs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-resv-settings')); ?>">
                    <?php esc_html_e('FP Reservations', 'fp-restaurant-reservations'); ?>
                </a>
                <span class="fp-resv-admin__breadcrumb-separator">/</span>
                <span class="fp-resv-admin__breadcrumb-current">
                    <?php esc_html_e('Colori Form', 'fp-restaurant-reservations'); ?>
                </span>
            </nav>
            <h1 class="fp-resv-admin__title">
                <?php esc_html_e('Personalizza Colori Form', 'fp-restaurant-reservations'); ?>
            </h1>
            <p class="fp-resv-admin__subtitle">
                <?php esc_html_e('Scegli i colori per il form di prenotazione con anteprima in tempo reale', 'fp-restaurant-reservations'); ?>
            </p>
        </div>
    </div>

    <?php if (isset($_GET['fp_resv_colors_saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Colori salvati con successo!', 'fp-restaurant-reservations'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['fp_resv_colors_reset'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Colori ripristinati ai valori predefiniti!', 'fp-restaurant-reservations'); ?></p>
        </div>
    <?php endif; ?>

    <div class="fp-resv-form-colors-container">
        <!-- Color Pickers -->
        <div class="fp-resv-form-colors-controls">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fp-resv-form-colors-form">
                <?php wp_nonce_field('fp_resv_save_form_colors', 'fp_resv_colors_nonce'); ?>
                <input type="hidden" name="action" value="fp_resv_save_form_colors">

                <!-- Quick Presets -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Preset Rapidi', 'fp-restaurant-reservations'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Seleziona un preset per iniziare rapidamente', 'fp-restaurant-reservations'); ?>
                    </p>
                    <div class="fp-resv-presets">
                        <?php foreach ($presets as $presetKey => $preset) : ?>
                            <button 
                                type="button" 
                                class="fp-resv-preset-btn"
                                data-preset="<?php echo esc_attr($presetKey); ?>"
                                data-colors="<?php echo esc_attr(wp_json_encode($preset['colors'])); ?>"
                            >
                                <?php echo esc_html($preset['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Primary Colors -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Colori Principali', 'fp-restaurant-reservations'); ?></h2>
                    
                    <div class="fp-resv-color-field">
                        <label for="fp_color_primary">
                            <?php esc_html_e('Colore Primario', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_primary"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                        <span class="description"><?php esc_html_e('Bottoni principali, link, accenti', 'fp-restaurant-reservations'); ?></span>
                    </div>

                    <div class="fp-resv-color-field">
                        <label for="fp_color_primary_hover">
                            <?php esc_html_e('Colore Hover', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_primary_hover"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                        <span class="description"><?php esc_html_e('Al passaggio del mouse', 'fp-restaurant-reservations'); ?></span>
                    </div>
                </div>

                <!-- Surface Colors -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Colori Sfondo', 'fp-restaurant-reservations'); ?></h2>
                    
                    <div class="fp-resv-color-field">
                        <label for="fp_color_surface">
                            <?php esc_html_e('Sfondo Principale', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_surface"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>

                    <div class="fp-resv-color-field">
                        <label for="fp_color_surface_alt">
                            <?php esc_html_e('Sfondo Alternativo', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_surface_alt"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                        <span class="description"><?php esc_html_e('Card e sezioni', 'fp-restaurant-reservations'); ?></span>
                    </div>
                </div>

                <!-- Text Colors -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Colori Testo', 'fp-restaurant-reservations'); ?></h2>
                    
                    <div class="fp-resv-color-field">
                        <label for="fp_color_text">
                            <?php esc_html_e('Testo Principale', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>

                    <div class="fp-resv-color-field">
                        <label for="fp_color_text_muted">
                            <?php esc_html_e('Testo Secondario', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_text_muted"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                </div>

                <!-- Border Colors -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Colori Bordi', 'fp-restaurant-reservations'); ?></h2>
                    
                    <div class="fp-resv-color-field">
                        <label for="fp_color_border">
                            <?php esc_html_e('Bordo Principale', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_border"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                </div>

                <!-- Button Colors -->
                <div class="fp-resv-color-section">
                    <h2><?php esc_html_e('Colori Bottoni', 'fp-restaurant-reservations'); ?></h2>
                    
                    <div class="fp-resv-color-field">
                        <label for="fp_color_button_bg">
                            <?php esc_html_e('Sfondo Bottone', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_button_bg"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>

                    <div class="fp-resv-color-field">
                        <label for="fp_color_button_text">
                            <?php esc_html_e('Testo Bottone', 'fp-restaurant-reservations'); ?>
                        </label>
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
                            class="fp-color-text"
                            data-for="fp_color_button_text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                </div>

                <!-- Actions -->
                <div class="fp-resv-form-colors-actions">
                    <?php submit_button(__('Salva Colori', 'fp-restaurant-reservations'), 'primary', 'submit', false); ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fp_resv_reset_form_colors'), 'fp_resv_reset_form_colors')); ?>" 
                       class="button button-secondary"
                       onclick="return confirm('<?php esc_attr_e('Ripristinare i colori predefiniti?', 'fp-restaurant-reservations'); ?>');">
                        <?php esc_html_e('Ripristina Default', 'fp-restaurant-reservations'); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Live Preview -->
        <div class="fp-resv-form-colors-preview">
            <div class="fp-resv-preview-header">
                <h3><?php esc_html_e('Anteprima Live', 'fp-restaurant-reservations'); ?></h3>
                <p class="description"><?php esc_html_e('I cambiamenti vengono mostrati in tempo reale', 'fp-restaurant-reservations'); ?></p>
            </div>
            <div class="fp-resv-preview-wrapper">
                <iframe 
                    id="fp-resv-preview-iframe" 
                    frameborder="0"
                    style="width: 100%; min-height: 600px; border: 1px solid #ddd; border-radius: 8px;"
                ></iframe>
            </div>
        </div>
    </div>
</div>

<style id="fp-resv-dynamic-colors">
<?php echo $formColors->generateCSS(); ?>
</style>

