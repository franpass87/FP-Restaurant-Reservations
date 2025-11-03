<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Core\Plugin;
use FP\Resv\Core\ServiceContainer;
use FP\Resv\Domain\Settings\Language;
use FP\Resv\Domain\Settings\Options;
use function add_shortcode;
use function apply_filters;
use function file_exists;
use function ob_get_clean;
use function ob_start;
use function shortcode_atts;

final class Shortcodes
{
    public static function register(): void
    {
        error_log('[FP-RESV-SHORTCODE] register() method called');
        add_shortcode('fp_reservations', [self::class, 'render']);
        add_shortcode('fp_resv_debug', [self::class, 'renderDebug']);
        add_shortcode('fp_resv_test', [self::class, 'renderTest']); // Test shortcode semplice
        error_log('[FP-RESV-SHORTCODE] add_shortcode("fp_reservations") executed');
        error_log('[FP-RESV-SHORTCODE] add_shortcode("fp_resv_debug") executed');
        error_log('[FP-RESV-SHORTCODE] add_shortcode("fp_resv_test") executed');
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = []): string
    {
        error_log('[FP-RESV] ========================================');
        error_log('[FP-RESV] Shortcode render() chiamato');
        error_log('[FP-RESV] Attributes: ' . print_r($atts, true));
        error_log('[FP-RESV] Current URL: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log('[FP-RESV] Is main query: ' . (function_exists('is_main_query') ? (is_main_query() ? 'YES' : 'NO') : 'N/A'));
        
        // Temporarily disable wpautop and other filters that might break HTML
        $removedFilters = [];
        $filtersToRemove = ['wpautop', 'wptexturize', 'convert_chars'];
        foreach ($filtersToRemove as $filter) {
            if (has_filter('the_content', $filter)) {
                remove_filter('the_content', $filter);
                $removedFilters[] = $filter;
            }
        }
        
        try {
            $atts = shortcode_atts(
                [
                    'location' => 'default',
                    'lang'     => '',
                    'form_id'  => '',
                ],
                $atts,
                'fp_reservations'
            );

            $container = ServiceContainer::getInstance();
            if (!$container) {
                error_log('[FP-RESV] CRITICAL: ServiceContainer not available');
                $errorMsg = 'ServiceContainer non disponibile. Il plugin potrebbe non essere completamente inizializzato.';
                return self::renderError($errorMsg);
            }
            error_log('[FP-RESV] ServiceContainer OK');
            
            $options = $container->get(Options::class);
            if (!$options instanceof Options) {
                error_log('[FP-RESV] WARNING: Options instance missing from container, creating fallback instance');
                $options = new Options();
                $container->register(Options::class, $options);
                $container->register('settings.options', $options);
            }
            error_log('[FP-RESV] Options caricati correttamente');

            $language = $container->get(Language::class);
            if (!$language instanceof Language) {
                error_log('[FP-RESV] Language not in container, creating new instance');
                $language = new Language($options);
                $container->register(Language::class, $language);
                $container->register('settings.language.runtime', $language);
            }

            error_log('[FP-RESV] Creating FormContext...');
            $contextBuilder = new FormContext($options, $language, [
                'location' => (string) $atts['location'],
                'lang'     => (string) $atts['lang'],
                'form_id'  => (string) $atts['form_id'],
            ]);

            error_log('[FP-RESV] Converting context to array...');
            $context = $contextBuilder->toArray();
            error_log('[FP-RESV] Context creato, keys: ' . implode(', ', array_keys($context)));
            
            // Verify context has required data
            if (empty($context['config']) || empty($context['strings'])) {
                error_log('[FP-RESV] CRITICAL: Context missing required data');
                error_log('[FP-RESV] Context dump: ' . print_r($context, true));
                return self::renderError('Context incompleto');
            }

            /**
             * Allow third parties to adjust the frontend context before rendering.
             *
             * @param array<string, mixed> $context
             * @param array<string, mixed> $atts
             */
            $context = apply_filters('fp_resv_frontend_form_context', $context, $atts);

            $template = Plugin::$dir . 'templates/frontend/form.php';
            if (!file_exists($template)) {
                error_log('[FP-RESV] CRITICAL: Template not found at: ' . $template);
                return self::renderError('Template non trovato: ' . $template);
            }
            error_log('[FP-RESV] Template trovato: ' . $template);

            error_log('[FP-RESV] Starting template rendering...');
            ob_start();
            /** @var array<string, mixed> $context */
            $context = $context;
            include $template;

            $output = (string) ob_get_clean();
            error_log('[FP-RESV] Template rendered, output length: ' . strlen($output));
            
            // Ensure output is not empty
            if (empty(trim($output))) {
                error_log('[FP-RESV] CRITICAL: Form rendering produced empty output');
                error_log('[FP-RESV] Context had ' . count($context) . ' keys');
                return self::renderError('Il form non ha prodotto output');
            }

            error_log('[FP-RESV] Form renderizzato correttamente, lunghezza output: ' . strlen($output));
            error_log('[FP-RESV] Output contiene fp-resv-widget: ' . (strpos($output, 'fp-resv-widget') !== false ? 'SI' : 'NO'));
            error_log('[FP-RESV] Output contiene data-fp-resv-app: ' . (strpos($output, 'data-fp-resv-app') !== false ? 'SI' : 'NO'));
            error_log('[FP-RESV] ========================================');
            
            // RIMUOVI TUTTI i <p> e <br> aggiunti da wpautop che rompono il layout
            $output = preg_replace('/<p>\s*<!--/', '<!--', $output);  // <p> prima dei commenti
            $output = preg_replace('/-->\s*<\/p>/', '-->', $output);  // </p> dopo i commenti
            $output = preg_replace('/-->\s*<br\s*\/?>/', '-->', $output);  // <br> dopo commenti
            $output = preg_replace('/<p>\s*<\/p>/', '', $output);  // <p> vuoti
            $output = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/', '', $output);  // <p> con solo <br>
            
            // RIMUOVI TUTTI i <br /> ovunque
            $output = str_replace('<br />', '', $output);
            $output = str_replace('<br/>', '', $output);
            $output = str_replace('<br>', '', $output);
            $output = str_replace('<p></p>', '', $output);  // <p></p> vuoti
            
            // Rimuovi <p> che wrappano i div del form
            $output = preg_replace('/<p>(\s*<div[^>]*class="[^"]*fp-resv[^"]*"[^>]*>)/', '$1', $output);
            $output = preg_replace('/(<\/div>\s*)<\/p>/', '$1', $output);
            
            // Rimuovi spazi multipli tra tag
            $output = preg_replace('/>\s+</', '><', $output);
            
            // Re-add filters that were removed
            foreach ($removedFilters as $filter) {
                if ($filter === 'wpautop') {
                    add_filter('the_content', 'wpautop');
                } elseif ($filter === 'wptexturize') {
                    add_filter('the_content', 'wptexturize');
                } elseif ($filter === 'convert_chars') {
                    add_filter('the_content', 'convert_chars');
                }
            }
            
            return $output;
        } catch (\Throwable $e) {
            // Log error in development/debug mode
            error_log('[FP-RESV] ========================================');
            error_log('[FP-RESV] EXCEPTION CAUGHT!');
            error_log('[FP-RESV] Error rendering form: ' . $e->getMessage());
            error_log('[FP-RESV] Stack trace: ' . $e->getTraceAsString());
            error_log('[FP-RESV] File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            error_log('[FP-RESV] ========================================');
            
            // Re-add filters even on error
            foreach ($removedFilters as $filter) {
                if ($filter === 'wpautop') {
                    add_filter('the_content', 'wpautop');
                } elseif ($filter === 'wptexturize') {
                    add_filter('the_content', 'wptexturize');
                } elseif ($filter === 'convert_chars') {
                    add_filter('the_content', 'convert_chars');
                }
            }
            
            // Return visible error ALWAYS when there's an exception
            $errorMsg = 'Errore nel rendering del form: ' . $e->getMessage();
            $errorMsg .= '<br>File: ' . basename($e->getFile()) . ' Linea: ' . $e->getLine();
            return self::renderError($errorMsg);
        }
    }
    
    /**
     * Render error message with debug information
     */
    private static function renderError(string $message): string
    {
        error_log('[FP-RESV] Rendering error: ' . $message);
        
        // Show visible error only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $html = '<div class="fp-resv-error" style="background: #fee; border: 2px solid #c33; padding: 20px; margin: 20px 0; border-radius: 8px; font-family: sans-serif; max-width: 800px;">';
            $html .= '<h3 style="margin: 0 0 10px; color: #c33;">⚠️ FP Restaurant Reservations - Errore</h3>';
            $html .= '<p style="margin: 0;"><strong>Messaggio:</strong> ' . esc_html($message) . '</p>';
            $html .= '<p style="margin: 10px 0 0; font-size: 0.9em; color: #666;">Controlla i log PHP per maggiori dettagli. Per nascondere questo messaggio, disattiva WP_DEBUG.</p>';
            $html .= '</div>';
            return $html;
        }
        
        // In production, return HTML comment only
        return '<!-- FP-RESV Error: ' . esc_html($message) . ' -->';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function renderBlock(array $attributes = [], string $content = ''): string
    {
        unset($content);

        $atts = [
            'location' => isset($attributes['location']) ? (string) $attributes['location'] : 'default',
            'lang'     => isset($attributes['language']) ? (string) $attributes['language'] : '',
            'form_id'  => isset($attributes['formId']) ? (string) $attributes['formId'] : '',
        ];

        return self::render($atts);
    }

    /**
     * Shortcode di test super semplice: [fp_resv_test]
     * Serve solo a verificare che gli shortcode funzionino
     */
    public static function renderTest(): string
    {
        error_log('[FP-RESV-TEST] Test shortcode called!');
        
        $timestamp = wp_date('Y-m-d H:i:s');
        $user = wp_get_current_user();
        $isAdmin = current_user_can('manage_options') ? 'SÌ' : 'NO';
        
        return '<div style="background:#e7f5ff;border:2px solid #339af0;padding:20px;margin:20px 0;border-radius:8px;font-family:sans-serif;">' .
               '<h3 style="color:#1971c2;margin-top:0;">✅ Test Shortcode FP Restaurant Reservations</h3>' .
               '<p><strong>Timestamp:</strong> ' . esc_html($timestamp) . '</p>' .
               '<p><strong>Utente:</strong> ' . esc_html($user->user_login ?: 'Non loggato') . '</p>' .
               '<p><strong>Sei amministratore:</strong> ' . $isAdmin . '</p>' .
               '<p style="margin-bottom:0;"><strong>Stato:</strong> <span style="color:#2f9e44;font-weight:bold;">Lo shortcode funziona! ✅</span></p>' .
               '</div>';
    }

    /**
     * Shortcode diagnostico: [fp_resv_debug]
     * Mostra informazioni sul database e gli endpoint REST
     */
    public static function renderDebug(): string
    {
        // Test semplice per vedere se lo shortcode viene eseguito
        error_log('[FP-RESV-DEBUG] renderDebug() called');
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            error_log('[FP-RESV-DEBUG] User does not have manage_options capability');
            return '<div style="background:#fee;border:2px solid #c00;padding:20px;margin:20px 0;border-radius:8px;"><p style="color:#c00;font-weight:bold;">❌ Devi essere amministratore per vedere queste informazioni.</p><p>Utente corrente: ' . wp_get_current_user()->user_login . '</p></div>';
        }

        error_log('[FP-RESV-DEBUG] User has permissions, proceeding with debug');
        
        // Wrap in try-catch per catturare qualsiasi errore
        try {
            global $wpdb;
        $table = $wpdb->prefix . 'fp_reservations';
        $customersTable = $wpdb->prefix . 'fp_customers';

        ob_start();
        ?>
        <style>
            .fp-debug-panel {
                background: white;
                border: 2px solid #0073aa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .fp-debug-panel h2 {
                margin-top: 0;
                color: #0073aa;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .fp-debug-panel h3 {
                color: #23282d;
                margin-top: 20px;
            }
            .fp-debug-stat {
                background: #f8f9fa;
                padding: 15px;
                margin: 10px 0;
                border-left: 4px solid #0073aa;
            }
            .fp-debug-success { color: #46b450; font-weight: bold; }
            .fp-debug-error { color: #dc3232; font-weight: bold; }
            .fp-debug-warning { color: #ffb900; font-weight: bold; }
            .fp-debug-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            .fp-debug-table th,
            .fp-debug-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            .fp-debug-table th {
                background: #f1f1f1;
                font-weight: 600;
            }
            .fp-debug-table tr:hover {
                background: #f9f9f9;
            }
            .fp-debug-code {
                background: #23282d;
                color: #46b450;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
                font-family: 'Courier New', monospace;
                font-size: 13px;
            }
        </style>

        <div class="fp-debug-panel">
            <h2>🔍 Diagnostica FP Restaurant Reservations</h2>
            
            <?php
            // ============================================================================
            // VERIFICA TABELLA
            // ============================================================================
            ?>
            <h3>1️⃣ Verifica Tabella Database</h3>
            <?php
            $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            
            if (!$tableExists) {
                echo '<p class="fp-debug-error">❌ ERRORE: La tabella ' . esc_html($table) . ' NON ESISTE!</p>';
                echo '<p>Il plugin non è installato correttamente. Disattiva e riattiva il plugin.</p>';
                echo '</div>';
                return ob_get_clean();
            }
            ?>
            <p class="fp-debug-success">✅ Tabella <?php echo esc_html($table); ?> esiste</p>

            <?php
            // ============================================================================
            // STATISTICHE PRENOTAZIONI
            // ============================================================================
            ?>
            <h3>2️⃣ Statistiche Prenotazioni</h3>
            <?php
            $totalCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $today = current_time('Y-m-d');
            $todayCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE date = %s", $today));
            $futureCount = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE date >= %s", $today));
            ?>
            
            <div class="fp-debug-stat">
                <strong>Totale prenotazioni:</strong> 
                <span class="<?php echo $totalCount > 0 ? 'fp-debug-success' : 'fp-debug-error'; ?>">
                    <?php echo $totalCount; ?>
                </span>
            </div>
            
            <?php if ($totalCount === 0): ?>
                <div class="fp-debug-stat">
                    <p class="fp-debug-error"><strong>❌ PROBLEMA TROVATO!</strong></p>
                    <p>Non ci sono prenotazioni nel database. Questo significa che:</p>
                    <ul>
                        <li>Il form NON sta salvando i dati nel database</li>
                        <li>Le email partono ma il record non viene scritto</li>
                        <li>C'è un errore durante il salvataggio</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="fp-debug-stat">
                    <strong>Prenotazioni oggi:</strong> <?php echo $todayCount; ?><br>
                    <strong>Prenotazioni future:</strong> <?php echo $futureCount; ?>
                </div>
                
                <?php
                // Statistiche per stato
                $statusStats = $wpdb->get_results("
                    SELECT status, COUNT(*) as count
                    FROM $table
                    GROUP BY status
                    ORDER BY count DESC
                ", ARRAY_A);
                
                if ($statusStats):
                ?>
                    <table class="fp-debug-table">
                        <thead>
                            <tr>
                                <th>Stato</th>
                                <th>Numero</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusStats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(strtoupper($stat['status'])); ?></strong></td>
                                    <td><?php echo (int) $stat['count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // ============================================================================
            // ULTIME PRENOTAZIONI
            // ============================================================================
            if ($totalCount > 0):
            ?>
                <h3>3️⃣ Ultime 5 Prenotazioni</h3>
                <?php
                $recentReservations = $wpdb->get_results("
                    SELECT 
                        r.id,
                        r.date,
                        r.time,
                        r.party,
                        r.status,
                        r.created_at,
                        c.first_name,
                        c.last_name,
                        c.email
                    FROM $table r
                    LEFT JOIN $customersTable c ON r.customer_id = c.id
                    ORDER BY r.created_at DESC
                    LIMIT 5
                ", ARRAY_A);
                
                if ($recentReservations):
                ?>
                    <table class="fp-debug-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data/Ora</th>
                                <th>Persone</th>
                                <th>Stato</th>
                                <th>Cliente</th>
                                <th>Creato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReservations as $r): ?>
                                <tr>
                                    <td>#<?php echo (int) $r['id']; ?></td>
                                    <td><?php echo esc_html($r['date'] . ' ' . substr($r['time'], 0, 5)); ?></td>
                                    <td><?php echo (int) $r['party']; ?></td>
                                    <td><strong><?php echo esc_html(strtoupper($r['status'])); ?></strong></td>
                                    <td>
                                        <?php 
                                        if ($r['first_name'] || $r['last_name']) {
                                            echo esc_html($r['first_name'] . ' ' . $r['last_name']);
                                            if ($r['email']) {
                                                echo '<br><small>' . esc_html($r['email']) . '</small>';
                                            }
                                        } else {
                                            echo '<em>N/A</em>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($r['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // ============================================================================
            // TEST ENDPOINT REST
            // ============================================================================
            ?>
            <h3>4️⃣ Test Endpoint REST /agenda</h3>
            <?php
            $testDate = current_time('Y-m-d');
            $restUrl = rest_url('fp-resv/v1/agenda');
            $fullUrl = add_query_arg(['date' => $testDate, 'range' => 'month'], $restUrl);
            ?>
            <p><strong>Endpoint:</strong><br>
            <code><?php echo esc_html($fullUrl); ?></code></p>
            
            <?php
            // Simula chiamata REST
            $request = new \WP_REST_Request('GET', '/fp-resv/v1/agenda');
            $request->set_query_params(['date' => $testDate, 'range' => 'month']);
            $response = rest_do_request($request);
            
            if (is_wp_error($response)) {
                echo '<p class="fp-debug-error">❌ Errore: ' . esc_html($response->get_error_message()) . '</p>';
            } else {
                $data = $response->get_data();
                $statusCode = $response->get_status();
                
                echo '<p><strong>Status Code:</strong> <span class="' . ($statusCode === 200 ? 'fp-debug-success' : 'fp-debug-error') . '">' . $statusCode . '</span></p>';
                
                if ($statusCode === 200) {
                    if (isset($data['reservations']) && is_array($data['reservations'])) {
                        $reservationsInResponse = count($data['reservations']);
                        echo '<p><strong>Prenotazioni nella risposta:</strong> ' . $reservationsInResponse . '</p>';
                        
                        if ($reservationsInResponse === 0 && $totalCount > 0) {
                            echo '<div class="fp-debug-stat">';
                            echo '<p class="fp-debug-error"><strong>❌ PROBLEMA TROVATO!</strong></p>';
                            echo '<p>Ci sono ' . $totalCount . ' prenotazioni nel DB ma l\'endpoint ne restituisce 0.</p>';
                            echo '<p><strong>Possibili cause:</strong></p>';
                            echo '<ul>';
                            echo '<li>Le prenotazioni sono in date diverse dal mese corrente</li>';
                            echo '<li>C\'è un filtro che esclude le prenotazioni</li>';
                            echo '<li>Problema nella query SQL dell\'endpoint</li>';
                            echo '</ul>';
                            echo '</div>';
                        } else if ($reservationsInResponse > 0) {
                            echo '<p class="fp-debug-success">✅ L\'endpoint restituisce correttamente ' . $reservationsInResponse . ' prenotazioni!</p>';
                        }
                    } else {
                        echo '<p class="fp-debug-warning">⚠️ La risposta non contiene l\'array "reservations"</p>';
                    }
                }
            }
            ?>

            <?php
            // ============================================================================
            // RANGE DATE
            // ============================================================================
            if ($totalCount > 0):
            ?>
                <h3>5️⃣ Range Date Prenotazioni</h3>
                <?php
                $dateRange = $wpdb->get_row("
                    SELECT 
                        MIN(date) as prima_data,
                        MAX(date) as ultima_data
                    FROM $table
                ", ARRAY_A);
                
                if ($dateRange):
                ?>
                    <div class="fp-debug-stat">
                        <strong>Prima prenotazione:</strong> <?php echo esc_html($dateRange['prima_data']); ?><br>
                        <strong>Ultima prenotazione:</strong> <?php echo esc_html($dateRange['ultima_data']); ?>
                        
                        <?php if ($dateRange['ultima_data'] && strtotime($dateRange['ultima_data']) < strtotime($today)): ?>
                            <p class="fp-debug-warning">⚠️ <strong>ATTENZIONE:</strong> Tutte le prenotazioni sono nel passato!</p>
                            <p>Il manager di default mostra il mese corrente, quindi non vedrà prenotazioni vecchie.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <hr style="margin: 30px 0;">
            
            <h3>📋 Riepilogo</h3>
            <?php
            $hasReservations = $totalCount > 0;
            $endpointWorks = $statusCode === 200;
            $reservationsInResponse = isset($reservationsInResponse) ? $reservationsInResponse : 0;
            
            if (!$hasReservations) {
                echo '<p class="fp-debug-error"><strong>❌ PROBLEMA: Nessuna prenotazione nel database</strong></p>';
                echo '<p>Il form non salva i dati. Controlla i log PHP per errori durante l\'invio del form.</p>';
            } else if ($reservationsInResponse === 0 && $hasReservations) {
                echo '<p class="fp-debug-error"><strong>❌ PROBLEMA: L\'endpoint non restituisce prenotazioni</strong></p>';
                echo '<p>Ci sono dati nel DB ma l\'endpoint /agenda non li restituisce. Problema nella query o nei filtri.</p>';
            } else if ($reservationsInResponse > 0) {
                echo '<p class="fp-debug-success"><strong>✅ TUTTO OK dal lato server!</strong></p>';
                echo '<p>Database e endpoint funzionano. Se il manager non mostra nulla:</p>';
                echo '<ul>';
                echo '<li>Apri la <strong>Console JavaScript</strong> del browser (F12)</li>';
                echo '<li>Cerca errori JavaScript</li>';
                echo '<li>Verifica che il nonce sia valido</li>';
                echo '<li>Cancella la cache del browser</li>';
                echo '</ul>';
            }
            ?>
        </div>
        <?php
        
        $output = ob_get_clean();
        error_log('[FP-RESV-DEBUG] Generated output length: ' . strlen($output));
        return $output;
        
        } catch (\Throwable $e) {
            error_log('[FP-RESV-DEBUG] ERROR: ' . $e->getMessage());
            error_log('[FP-RESV-DEBUG] Stack trace: ' . $e->getTraceAsString());
            
            return '<div style="background:#fee;border:2px solid #c00;padding:20px;margin:20px 0;border-radius:8px;">' .
                   '<h3 style="color:#c00;margin-top:0;">❌ Errore nello shortcode di debug</h3>' .
                   '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>' .
                   '<p><strong>File:</strong> ' . esc_html($e->getFile()) . ' <strong>Riga:</strong> ' . $e->getLine() . '</p>' .
                   '<p><em>Controlla i log PHP per maggiori dettagli.</em></p>' .
                   '</div>';
        }
    }
}
