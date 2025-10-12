<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use function add_action;
use function admin_url;
use function current_user_can;
use function do_shortcode;
use function esc_html;
use function esc_url;
use function get_option;
use function get_permalink;
use function get_post;
use function home_url;
use function wp_die;

final class ShortcodeDebug
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addDebugPage'], 100);
    }

    public function addDebugPage(): void
    {
        add_submenu_page(
            'fp-resv-reservations',
            'Debug Shortcode',
            '🔍 Debug Form',
            'manage_options',
            'fp-resv-debug-shortcode',
            [$this, 'renderDebugPage']
        );
    }

    public function renderDebugPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }

        global $wpdb;

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                .fp-debug-container {
                    max-width: 1200px;
                    margin: 20px 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .fp-debug-box {
                    background: white;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .fp-debug-box h2 {
                    margin-top: 0;
                    border-bottom: 2px solid #2271b1;
                    padding-bottom: 10px;
                }
                .fp-debug-success { border-left: 4px solid #00a32a; }
                .fp-debug-error { border-left: 4px solid #d63638; }
                .fp-debug-warning { border-left: 4px solid #dba617; }
                .fp-debug-code {
                    background: #f6f7f7;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 10px;
                    font-family: 'Courier New', monospace;
                    overflow-x: auto;
                }
                .fp-debug-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .fp-debug-table th,
                .fp-debug-table td {
                    padding: 10px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                .fp-debug-table th {
                    background: #f6f7f7;
                    font-weight: 600;
                }
                .fp-button {
                    display: inline-block;
                    padding: 8px 16px;
                    background: #2271b1;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    margin-right: 10px;
                }
                .fp-button:hover {
                    background: #135e96;
                    color: white;
                }
                .fp-status-yes { color: #00a32a; font-weight: bold; }
                .fp-status-no { color: #d63638; font-weight: bold; }
                .fp-status-warn { color: #dba617; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="wrap fp-debug-container">
                <h1>🔍 Debug Form Prenotazioni</h1>
                
                <?php
                // Check 1: Plugin Status
                $pluginActive = class_exists('FP\\Resv\\Core\\Plugin');
                ?>
                <div class="fp-debug-box <?php echo $pluginActive ? 'fp-debug-success' : 'fp-debug-error'; ?>">
                    <h2>1. Status Plugin</h2>
                    <?php if ($pluginActive): ?>
                        <p class="fp-status-yes">✓ Plugin attivo e caricato</p>
                        <p>Versione: <code><?php echo \FP\Resv\Core\Plugin::VERSION; ?></code></p>
                    <?php else: ?>
                        <p class="fp-status-no">✗ Plugin non caricato correttamente</p>
                    <?php endif; ?>
                </div>

                <?php
                // Check 2: Shortcode Registration
                global $shortcode_tags;
                $shortcodeRegistered = isset($shortcode_tags['fp_reservations']);
                ?>
                <div class="fp-debug-box <?php echo $shortcodeRegistered ? 'fp-debug-success' : 'fp-debug-error'; ?>">
                    <h2>2. Registrazione Shortcode</h2>
                    <?php if ($shortcodeRegistered): ?>
                        <p class="fp-status-yes">✓ Shortcode [fp_reservations] registrato</p>
                    <?php else: ?>
                        <p class="fp-status-no">✗ Shortcode NON registrato</p>
                    <?php endif; ?>
                </div>

                <?php
                // Check 3: Pages with Shortcode
                $pages = $wpdb->get_results("
                    SELECT ID, post_title, post_name, post_status, post_type
                    FROM {$wpdb->posts}
                    WHERE post_status IN ('publish', 'draft', 'private')
                    AND post_type IN ('page', 'post')
                    AND post_content LIKE '%fp_reservations%'
                    ORDER BY post_status DESC, post_type, post_title
                ");
                ?>
                <div class="fp-debug-box <?php echo !empty($pages) ? 'fp-debug-success' : 'fp-debug-error'; ?>">
                    <h2>3. Pagine con Shortcode</h2>
                    <?php if (empty($pages)): ?>
                        <p class="fp-status-no">✗ Nessuna pagina trovata con lo shortcode!</p>
                        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-top: 15px;">
                            <h3 style="margin-top: 0;">Come Risolvere:</h3>
                            <ol>
                                <li>Vai su <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>">Pagine → Aggiungi nuova</a></li>
                                <li>Aggiungi il titolo: "Prenota un tavolo"</li>
                                <li>Nel contenuto, scrivi: <code>[fp_reservations]</code></li>
                                <li>Pubblica la pagina</li>
                            </ol>
                        </div>
                    <?php else: ?>
                        <p class="fp-status-yes">✓ Trovate <?php echo count($pages); ?> pagina/e</p>
                        <table class="fp-debug-table">
                            <thead>
                                <tr>
                                    <th>Titolo</th>
                                    <th>Tipo</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($page->post_title); ?></strong></td>
                                        <td><?php echo esc_html($page->post_type); ?></td>
                                        <td>
                                            <?php if ($page->post_status === 'publish'): ?>
                                                <span class="fp-status-yes">✓ Pubblicata</span>
                                            <?php else: ?>
                                                <span class="fp-status-warn">⚠ <?php echo esc_html($page->post_status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(get_permalink($page->ID)); ?>" class="fp-button" target="_blank">Visualizza</a>
                                            <a href="<?php echo admin_url('post.php?post=' . $page->ID . '&action=edit'); ?>" class="fp-button">Modifica</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($shortcodeRegistered): ?>
                    <div class="fp-debug-box">
                        <h2>4. Test Esecuzione Shortcode</h2>
                        <p>Eseguo il shortcode per verificare l'output...</p>
                        
                        <?php
                        ob_start();
                        $startTime = microtime(true);
                        $output = do_shortcode('[fp_reservations]');
                        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                        $buffered = ob_get_clean();
                        ?>
                        
                        <p>⏱ Tempo di esecuzione: <strong><?php echo $executionTime; ?>ms</strong></p>
                        <p>📏 Lunghezza output: <strong><?php echo strlen($output); ?> bytes</strong></p>
                        
                        <?php if (empty(trim($output))): ?>
                            <div class="fp-debug-error" style="padding: 15px; margin-top: 15px;">
                                <p class="fp-status-no">✗ Lo shortcode NON ha prodotto output!</p>
                                <p><strong>Possibili cause:</strong></p>
                                <ul>
                                    <li>Le impostazioni del plugin non sono configurate</li>
                                    <li>Errore nel FormContext builder</li>
                                    <li>Servizi (pranzo/cena) non configurati</li>
                                </ul>
                                <p><a href="<?php echo admin_url('admin.php?page=fp-resv-settings'); ?>" class="fp-button">Vai alle Impostazioni</a></p>
                            </div>
                        <?php else: ?>
                            <?php
                            $hasWidget = strpos($output, 'fp-resv-widget') !== false;
                            $hasForm = strpos($output, 'fp-resv-widget__form') !== false;
                            $hasDataAttr = strpos($output, 'data-fp-resv-app') !== false;
                            ?>
                            <div class="fp-debug-success" style="padding: 15px; margin-top: 15px;">
                                <p class="fp-status-yes">✓ Lo shortcode ha prodotto output!</p>
                                <ul style="list-style: none; padding-left: 0;">
                                    <li><?php echo $hasWidget ? '<span class="fp-status-yes">✓</span>' : '<span class="fp-status-no">✗</span>'; ?> Contiene classe 'fp-resv-widget'</li>
                                    <li><?php echo $hasForm ? '<span class="fp-status-yes">✓</span>' : '<span class="fp-status-no">✗</span>'; ?> Contiene form</li>
                                    <li><?php echo $hasDataAttr ? '<span class="fp-status-yes">✓</span>' : '<span class="fp-status-no">✗</span>'; ?> Contiene attributo 'data-fp-resv-app'</li>
                                </ul>
                                
                                <?php if ($hasWidget && $hasForm && $hasDataAttr): ?>
                                    <div style="background: #d1f2eb; border: 1px solid #00a32a; padding: 15px; border-radius: 4px; margin-top: 15px;">
                                        <h3 style="margin-top: 0; color: #00a32a;">✅ IL FORM FUNZIONA!</h3>
                                        <p>Il form viene generato correttamente. Se non lo vedi sulla pagina:</p>
                                        <ul>
                                            <li>Svuota la cache del browser (Ctrl+Shift+R)</li>
                                            <li>Svuota la cache del server/CDN se presente</li>
                                            <li>Verifica che non ci siano conflitti CSS che nascondono il form</li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <details style="margin-top: 15px;">
                                <summary style="cursor: pointer; padding: 10px; background: #f6f7f7; border-radius: 4px;">
                                    Mostra HTML generato (primi 1000 caratteri)
                                </summary>
                                <div class="fp-debug-code" style="margin-top: 10px; max-height: 400px; overflow-y: auto;">
                                    <?php echo esc_html(substr($output, 0, 1000)); ?>
                                    <?php if (strlen($output) > 1000): ?>
                                        <div style="padding: 10px; background: #fff; margin-top: 10px;">
                                        ... [troncato, totale <?php echo strlen($output); ?> bytes]
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                // Check 5: Debug Log
                $logFile = WP_CONTENT_DIR . '/debug.log';
                $hasLog = file_exists($logFile);
                ?>
                <div class="fp-debug-box">
                    <h2>5. Log PHP</h2>
                    <?php if ($hasLog): ?>
                        <?php
                        $logContent = file_get_contents($logFile);
                        $lines = explode("\n", $logContent);
                        $fpResvLogs = array_filter($lines, function($line) {
                            return stripos($line, '[FP-RESV]') !== false;
                        });
                        ?>
                        <?php if (count($fpResvLogs) > 0): ?>
                            <p>Trovate <strong><?php echo count($fpResvLogs); ?></strong> righe di log</p>
                            <div class="fp-debug-code" style="max-height: 400px; overflow-y: auto;">
                                <?php
                                $lastLogs = array_slice($fpResvLogs, -20);
                                foreach ($lastLogs as $log):
                                    echo esc_html($log) . "\n";
                                endforeach;
                                ?>
                            </div>
                        <?php else: ?>
                            <p class="fp-status-warn">⚠ Nessun log trovato con [FP-RESV]</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="fp-status-warn">⚠ File debug.log non trovato</p>
                        <p>Per attivare i log, aggiungi in wp-config.php:</p>
                        <div class="fp-debug-code">
define('WP_DEBUG', true);<br>
define('WP_DEBUG_LOG', true);
                        </div>
                    <?php endif; ?>
                </div>

                <div class="fp-debug-box">
                    <h2>📚 Cosa Fare Ora</h2>
                    <?php if (empty($pages)): ?>
                        <p><strong>1. Crea una pagina con lo shortcode:</strong></p>
                        <p><a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="fp-button">Crea Nuova Pagina</a></p>
                    <?php elseif (empty(trim($output ?? ''))): ?>
                        <p><strong>1. Configura le impostazioni del plugin:</strong></p>
                        <p><a href="<?php echo admin_url('admin.php?page=fp-resv-settings'); ?>" class="fp-button">Vai alle Impostazioni</a></p>
                    <?php else: ?>
                        <p><strong>Il form funziona! Visita la pagina:</strong></p>
                        <?php if (isset($pages[0])): ?>
                            <p><a href="<?php echo esc_url(get_permalink($pages[0]->ID)); ?>" class="fp-button" target="_blank">Visualizza Pagina</a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <p><a href="?page=fp-resv-debug-shortcode&refresh=<?php echo time(); ?>" class="fp-button">🔄 Aggiorna Diagnostica</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

