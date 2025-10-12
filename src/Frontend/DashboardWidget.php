<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function add_action;
use function admin_url;
use function current_user_can;
use function do_shortcode;
use function esc_html;
use function esc_url;
use function get_permalink;
use function wp_add_dashboard_widget;

final class DashboardWidget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
    }

    public function addWidget(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'fp_resv_form_status',
            '🍽️ FP Reservations - Status Form',
            [$this, 'renderWidget']
        );
    }

    public function renderWidget(): void
    {
        global $wpdb, $shortcode_tags;

        echo '<style>
            .fp-status-box { padding: 15px; margin: 10px 0; border-left: 4px solid #ddd; background: #f9f9f9; }
            .fp-status-ok { border-color: #00a32a; background: #e8f5e9; }
            .fp-status-error { border-color: #d63638; background: #ffebee; }
            .fp-status-warning { border-color: #dba617; background: #fff8e1; }
            .fp-status-icon { font-size: 20px; margin-right: 5px; }
            .fp-status-btn { display: inline-block; padding: 8px 15px; background: #2271b1; color: white !important; text-decoration: none; border-radius: 4px; margin: 5px 5px 0 0; }
            .fp-status-btn:hover { background: #135e96; }
        </style>';

        // Check 1: Plugin Active
        $pluginActive = class_exists('FP\\Resv\\Core\\Plugin');
        
        // Check 2: Shortcode Registered
        $shortcodeRegistered = isset($shortcode_tags['fp_reservations']);
        
        // Check 3: Find pages with shortcode
        $pages = $wpdb->get_results("
            SELECT ID, post_title, post_status 
            FROM {$wpdb->posts}
            WHERE post_status IN ('publish', 'draft')
            AND post_type = 'page'
            AND post_content LIKE '%[fp_reservations%'
            ORDER BY post_status DESC, post_title
            LIMIT 5
        ");

        $publishedPages = array_filter($pages ?: [], fn($p) => $p->post_status === 'publish');

        // Check 4: Test shortcode execution
        $hasOutput = false;
        if ($shortcodeRegistered) {
            ob_start();
            $output = do_shortcode('[fp_reservations]');
            ob_end_clean();
            $hasOutput = !empty(trim($output)) && strpos($output, 'fp-resv-widget') !== false;
        }

        // Overall status
        $allOk = $pluginActive && $shortcodeRegistered && !empty($publishedPages) && $hasOutput;

        if ($allOk) {
            echo '<div class="fp-status-box fp-status-ok">';
            echo '<span class="fp-status-icon">✅</span>';
            echo '<strong>Tutto OK!</strong> Il form di prenotazione funziona correttamente.';
            echo '</div>';
            
            echo '<p><strong>Pagine con form:</strong></p>';
            echo '<ul style="margin: 0; padding-left: 20px;">';
            foreach ($publishedPages as $page) {
                echo '<li><a href="' . esc_url(get_permalink($page->ID)) . '" target="_blank">' . esc_html($page->post_title) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="fp-status-box fp-status-error">';
            echo '<span class="fp-status-icon">⚠️</span>';
            echo '<strong>Problema Rilevato</strong>';
            echo '</div>';

            if (!$pluginActive) {
                echo '<p style="color: #d63638;">✗ Plugin non attivo correttamente</p>';
            } elseif (!$shortcodeRegistered) {
                echo '<p style="color: #d63638;">✗ Shortcode non registrato</p>';
            } elseif (empty($pages)) {
                echo '<div class="fp-status-box fp-status-warning">';
                echo '<p><strong>✗ Nessuna pagina con shortcode trovata</strong></p>';
                echo '<p>Devi creare una pagina e aggiungere lo shortcode <code>[fp_reservations]</code></p>';
                echo '<a href="' . admin_url('post-new.php?post_type=page') . '" class="fp-status-btn">Crea Nuova Pagina</a>';
                echo '</div>';
            } elseif (empty($publishedPages)) {
                echo '<div class="fp-status-box fp-status-warning">';
                echo '<p><strong>⚠ Pagina trovata ma non pubblicata</strong></p>';
                foreach ($pages as $page) {
                    echo '<p>Pagina: <strong>' . esc_html($page->post_title) . '</strong> (Stato: ' . esc_html($page->post_status) . ')</p>';
                    echo '<a href="' . admin_url('post.php?post=' . $page->ID . '&action=edit') . '" class="fp-status-btn">Modifica e Pubblica</a>';
                }
                echo '</div>';
            } elseif (!$hasOutput) {
                echo '<div class="fp-status-box fp-status-error">';
                echo '<p><strong>✗ Lo shortcode non produce output</strong></p>';
                echo '<p>Possibili cause:</p>';
                echo '<ul style="margin: 5px 0; padding-left: 20px;">';
                echo '<li>Impostazioni del plugin non configurate</li>';
                echo '<li>Servizi (pranzo/cena) non definiti</li>';
                echo '<li>Errore nella configurazione</li>';
                echo '</ul>';
                echo '<a href="' . admin_url('admin.php?page=fp-resv-settings') . '" class="fp-status-btn">Vai alle Impostazioni</a>';
                echo '</div>';
            }
        }

        echo '<hr style="margin: 15px 0;">';
        echo '<p style="margin: 0;"><small>';
        echo '<a href="' . admin_url('admin.php?page=fp-resv-settings') . '">Impostazioni</a> • ';
        echo '<a href="' . admin_url('admin.php?page=fp-resv-reservations') . '">Agenda</a>';
        if (!empty($publishedPages)) {
            echo ' • <a href="' . esc_url(get_permalink($publishedPages[0]->ID)) . '" target="_blank">Visualizza Form</a>';
        }
        echo '</small></p>';
    }
}

