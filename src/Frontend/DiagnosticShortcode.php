<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use Throwable;
use WP_REST_Request;
use function add_query_arg;
use function count;
use function current_time;
use function current_user_can;
use function esc_html;
use function error_log;
use function is_array;
use function is_wp_error;
use function ob_get_clean;
use function ob_start;
use function rest_do_request;
use function rest_url;
use function strlen;
use function strtotime;
use function strtoupper;
use function substr;

/**
 * Shortcode diagnostico per debug e troubleshooting.
 * Estratto da Shortcodes per migliorare la manutenibilità.
 */
final class DiagnosticShortcode
{
    /**
     * Render dello shortcode diagnostico [fp_resv_debug].
     */
    public function render(): string
    {
        error_log('[FP-RESV-DEBUG] renderDebug() called');
        
        if (!current_user_can('manage_options')) {
            error_log('[FP-RESV-DEBUG] User does not have manage_options capability');
            return $this->renderPermissionError();
        }

        error_log('[FP-RESV-DEBUG] User has permissions, proceeding with debug');
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'fp_reservations';
            $customersTable = $wpdb->prefix . 'fp_customers';

            ob_start();
            $this->renderStyles();
            $this->renderPanel($wpdb, $table, $customersTable);
            
            $output = ob_get_clean();
            error_log('[FP-RESV-DEBUG] Generated output length: ' . strlen($output));
            return $output;
        } catch (Throwable $e) {
            error_log('[FP-RESV-DEBUG] ERROR: ' . $e->getMessage());
            error_log('[FP-RESV-DEBUG] Stack trace: ' . $e->getTraceAsString());
            
            return $this->renderException($e);
        }
    }

    /**
     * Render errore permessi.
     */
    private function renderPermissionError(): string
    {
        return '<div style="background:#fee;border:2px solid #c00;padding:20px;margin:20px 0;border-radius:8px;">' .
               '<p style="color:#c00;font-weight:bold;">❌ Devi essere amministratore per vedere queste informazioni.</p>' .
               '<p>Utente corrente: ' . wp_get_current_user()->user_login . '</p>' .
               '</div>';
    }

    /**
     * Render eccezione.
     */
    private function renderException(Throwable $e): string
    {
        return '<div style="background:#fee;border:2px solid #c00;padding:20px;margin:20px 0;border-radius:8px;">' .
               '<h3 style="color:#c00;margin-top:0;">❌ Errore nello shortcode di debug</h3>' .
               '<p><strong>Messaggio:</strong> ' . esc_html($e->getMessage()) . '</p>' .
               '<p><strong>File:</strong> ' . esc_html($e->getFile()) . ' <strong>Riga:</strong> ' . $e->getLine() . '</p>' .
               '<p><em>Controlla i log PHP per maggiori dettagli.</em></p>' .
               '</div>';
    }

    /**
     * Render stili CSS.
     */
    private function renderStyles(): void
    {
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
        </style>
        <?php
    }

    /**
     * Render pannello diagnostico principale.
     *
     * @param \wpdb $wpdb
     */
    private function renderPanel($wpdb, string $table, string $customersTable): void
    {
        ?>
        <div class="fp-debug-panel">
            <h2>🔍 Diagnostica FP Restaurant Reservations</h2>
            
            <?php
            $this->renderTableCheck($wpdb, $table);
            $totalCount = $this->renderReservationStats($wpdb, $table);
            
            if ($totalCount > 0) {
                $this->renderRecentReservations($wpdb, $table, $customersTable);
            }
            
            $statusCode = $this->renderRestEndpointTest($totalCount);
            
            if ($totalCount > 0) {
                $this->renderDateRange($wpdb, $table);
            }
            
            $this->renderSummary($totalCount, $statusCode);
            ?>
        </div>
        <?php
    }

    /**
     * Verifica esistenza tabella.
     *
     * @param \wpdb $wpdb
     */
    private function renderTableCheck($wpdb, string $table): void
    {
        ?>
        <h3>1️⃣ Verifica Tabella Database</h3>
        <?php
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        
        if (!$tableExists) {
            echo '<p class="fp-debug-error">❌ ERRORE: La tabella ' . esc_html($table) . ' NON ESISTE!</p>';
            echo '<p>Il plugin non è installato correttamente. Disattiva e riattiva il plugin.</p>';
            return;
        }
        ?>
        <p class="fp-debug-success">✅ Tabella <?php echo esc_html($table); ?> esiste</p>
        <?php
    }

    /**
     * Render statistiche prenotazioni.
     *
     * @param \wpdb $wpdb
     * @return int Numero totale prenotazioni
     */
    private function renderReservationStats($wpdb, string $table): int
    {
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
        return $totalCount;
    }

    /**
     * Render ultime prenotazioni.
     *
     * @param \wpdb $wpdb
     */
    private function renderRecentReservations($wpdb, string $table, string $customersTable): void
    {
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
        <?php
    }

    /**
     * Test endpoint REST.
     *
     * @return int Status code della risposta
     */
    private function renderRestEndpointTest(int $totalCount): int
    {
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
        $request = new WP_REST_Request('GET', '/fp-resv/v1/agenda');
        $request->set_query_params(['date' => $testDate, 'range' => 'month']);
        $response = rest_do_request($request);
        
        if (is_wp_error($response)) {
            echo '<p class="fp-debug-error">❌ Errore: ' . esc_html($response->get_error_message()) . '</p>';
            return 0;
        }
        
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
        
        return $statusCode;
    }

    /**
     * Render range date prenotazioni.
     *
     * @param \wpdb $wpdb
     */
    private function renderDateRange($wpdb, string $table): void
    {
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
                
                <?php 
                $today = current_time('Y-m-d');
                if ($dateRange['ultima_data'] && strtotime($dateRange['ultima_data']) < strtotime($today)): 
                ?>
                    <p class="fp-debug-warning">⚠️ <strong>ATTENZIONE:</strong> Tutte le prenotazioni sono nel passato!</p>
                    <p>Il manager di default mostra il mese corrente, quindi non vedrà prenotazioni vecchie.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render riepilogo finale.
     */
    private function renderSummary(int $totalCount, int $statusCode): void
    {
        ?>
        <hr style="margin: 30px 0;">
        
        <h3>📋 Riepilogo</h3>
        <?php
        $hasReservations = $totalCount > 0;
        $endpointWorks = $statusCode === 200;
        
        if (!$hasReservations) {
            echo '<p class="fp-debug-error"><strong>❌ PROBLEMA: Nessuna prenotazione nel database</strong></p>';
            echo '<p>Il form non salva i dati. Controlla i log PHP per errori durante l\'invio del form.</p>';
        } else if ($endpointWorks) {
            echo '<p class="fp-debug-success"><strong>✅ TUTTO OK dal lato server!</strong></p>';
            echo '<p>Database e endpoint funzionano. Se il manager non mostra nulla:</p>';
            echo '<ul>';
            echo '<li>Apri la <strong>Console JavaScript</strong> del browser (F12)</li>';
            echo '<li>Cerca errori JavaScript</li>';
            echo '<li>Verifica che il nonce sia valido</li>';
            echo '<li>Cancella la cache del browser</li>';
            echo '</ul>';
        } else {
            echo '<p class="fp-debug-error"><strong>❌ PROBLEMA: L\'endpoint non funziona correttamente</strong></p>';
            echo '<p>Controlla i log PHP per errori nell\'endpoint REST.</p>';
        }
        ?>
        <?php
    }
}















