<?php
/**
 * Verify Shortcode in Page - Verifica se lo shortcode è nel database
 */

require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verifica Shortcode nella Pagina</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; }
        .box { background: #f5f5f5; border-left: 4px solid #2196f3; padding: 20px; margin: 20px 0; }
        .success { border-color: #4caf50; background: #e8f5e9; }
        .error { border-color: #f44336; background: #ffebee; }
        .warning { border-color: #ff9800; background: #fff3e0; }
        code { background: #263238; color: #aed581; padding: 2px 6px; border-radius: 3px; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #2196f3; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #1976d2; }
    </style>
</head>
<body>
    <h1>🔍 Verifica Shortcode nelle Pagine</h1>
    
    <?php
    // Cerca tutte le pagine che contengono lo shortcode
    global $wpdb;
    
    $pages = $wpdb->get_results("
        SELECT ID, post_title, post_name, post_status, post_type, post_content
        FROM {$wpdb->posts}
        WHERE post_status IN ('publish', 'draft', 'private')
        AND post_type IN ('page', 'post')
        AND post_content LIKE '%fp_reservations%'
        ORDER BY post_type, post_title
    ");
    
    echo '<div class="box">';
    echo '<h2>Ricerca nel Database</h2>';
    echo '<p>Cerco pagine/post che contengono <code>[fp_reservations]</code> o <code>fp_reservations</code>...</p>';
    
    if (empty($pages)) {
        echo '<div class="box error">';
        echo '<h3>❌ Nessuna pagina trovata!</h3>';
        echo '<p><strong>Lo shortcode non è presente in nessuna pagina o post nel database.</strong></p>';
        echo '<p>Devi:</p>';
        echo '<ol>';
        echo '<li>Creare una nuova pagina</li>';
        echo '<li>Aggiungere lo shortcode <code>[fp_reservations]</code> nel contenuto</li>';
        echo '<li>Pubblicare la pagina</li>';
        echo '</ol>';
        echo '<a href="' . admin_url('post-new.php?post_type=page') . '" class="btn">Crea Nuova Pagina</a>';
        echo '</div>';
    } else {
        echo '<div class="box success">';
        echo '<h3>✓ Trovate ' . count($pages) . ' pagina/e</h3>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>Titolo</th>';
        echo '<th>Tipo</th>';
        echo '<th>Stato</th>';
        echo '<th>Shortcode</th>';
        echo '<th>Azioni</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($pages as $page) {
            $hasShortcode = strpos($page->post_content, '[fp_reservations]') !== false;
            $hasText = strpos($page->post_content, 'fp_reservations') !== false;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($page->post_title) . '</strong><br><small>' . esc_html($page->post_name) . '</small></td>';
            echo '<td>' . esc_html($page->post_type) . '</td>';
            echo '<td>' . esc_html($page->post_status) . '</td>';
            echo '<td>';
            if ($hasShortcode) {
                echo '<span style="color: green;">✓ [fp_reservations]</span>';
            } elseif ($hasText) {
                echo '<span style="color: orange;">⚠ Testo trovato ma non come shortcode</span>';
            } else {
                echo '<span style="color: red;">✗ Non trovato</span>';
            }
            echo '</td>';
            echo '<td>';
            echo '<a href="' . get_permalink($page->ID) . '" class="btn" target="_blank">Visualizza</a>';
            echo '<a href="' . admin_url('post.php?post=' . $page->ID . '&action=edit') . '" class="btn" target="_blank">Modifica</a>';
            echo '</td>';
            echo '</tr>';
            
            // Mostra un'anteprima del contenuto
            echo '<tr>';
            echo '<td colspan="5" style="background: #f9f9f9; font-size: 0.9em;">';
            echo '<details>';
            echo '<summary style="cursor: pointer;">Mostra contenuto (primi 500 caratteri)</summary>';
            echo '<pre style="margin-top: 10px; white-space: pre-wrap;">' . esc_html(substr($page->post_content, 0, 500));
            if (strlen($page->post_content) > 500) {
                echo '...';
            }
            echo '</pre>';
            echo '</details>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    echo '</div>';
    
    // Verifica anche nei widget
    echo '<div class="box">';
    echo '<h2>Verifica nei Widget</h2>';
    
    $widgets = get_option('sidebars_widgets', []);
    $foundInWidgets = false;
    
    foreach ($widgets as $sidebar => $widget_list) {
        if (!is_array($widget_list)) continue;
        
        foreach ($widget_list as $widget_id) {
            if (strpos($widget_id, 'text') === 0) {
                $text_widgets = get_option('widget_text', []);
                foreach ($text_widgets as $instance) {
                    if (is_array($instance) && isset($instance['text'])) {
                        if (strpos($instance['text'], 'fp_reservations') !== false) {
                            echo '<p style="color: green;">✓ Trovato nel widget: ' . esc_html($sidebar) . '</p>';
                            $foundInWidgets = true;
                        }
                    }
                }
            }
        }
    }
    
    if (!$foundInWidgets) {
        echo '<p>Nessun widget contiene lo shortcode.</p>';
    }
    echo '</div>';
    
    // Suggerimenti
    echo '<div class="box warning">';
    echo '<h2>💡 Cosa Fare Ora</h2>';
    
    if (empty($pages)) {
        echo '<p><strong>1. Crea una pagina per le prenotazioni:</strong></p>';
        echo '<ol>';
        echo '<li>Vai su <a href="' . admin_url('post-new.php?post_type=page') . '">Pagine → Aggiungi nuova</a></li>';
        echo '<li>Titolo: "Prenota un tavolo" (o simile)</li>';
        echo '<li>Nel contenuto, aggiungi: <code>[fp_reservations]</code></li>';
        echo '<li>Pubblica la pagina</li>';
        echo '</ol>';
    } else {
        $publishedPages = array_filter($pages, function($p) { return $p->post_status === 'publish'; });
        
        if (empty($publishedPages)) {
            echo '<p style="color: red;"><strong>⚠️ Le pagine trovate NON sono pubblicate!</strong></p>';
            echo '<p>Pubblica la pagina per renderla visibile.</p>';
        } else {
            echo '<p><strong>Pagine con shortcode trovate e pubblicate!</strong></p>';
            echo '<p>Se il form non si vede sulla pagina, il problema potrebbe essere:</p>';
            echo '<ul>';
            echo '<li>Il tema non esegue <code>the_content()</code></li>';
            echo '<li>Un page builder sta nascondendo il contenuto</li>';
            echo '<li>Cache del browser o del server</li>';
            echo '</ul>';
            
            echo '<p><strong>Prova questo fix immediato:</strong></p>';
            echo '<ol>';
            echo '<li>Vai sulla pagina e premi <code>Ctrl+Shift+R</code> (force reload)</li>';
            echo '<li>Apri la console del browser (F12) → Tab "Elementi"</li>';
            echo '<li>Cerca "fp-reservations" o "fp_reservations" nel HTML</li>';
            echo '<li>Se NON lo trovi, il tema non sta processando lo shortcode</li>';
            echo '</ol>';
        }
    }
    
    echo '</div>';
    
    // Test diretto
    if (!empty($pages)) {
        $firstPage = $pages[0];
        echo '<div class="box">';
        echo '<h2>🧪 Test Diretto Shortcode</h2>';
        echo '<p>Testiamo lo shortcode sulla prima pagina trovata: <strong>' . esc_html($firstPage->post_title) . '</strong></p>';
        
        // Setup query per simulare la pagina
        global $post;
        $post = get_post($firstPage->ID);
        setup_postdata($post);
        
        echo '<iframe src="' . get_permalink($firstPage->ID) . '" style="width: 100%; height: 600px; border: 2px solid #ddd; border-radius: 5px;"></iframe>';
        
        wp_reset_postdata();
        echo '</div>';
    }
    ?>
    
    <div class="box">
        <h2>📚 Documentazione</h2>
        <p><strong>Come usare lo shortcode:</strong></p>
        <pre>[fp_reservations]</pre>
        
        <p><strong>Con parametri opzionali:</strong></p>
        <pre>[fp_reservations location="default" lang="it"]</pre>
        
        <p><strong>Nel tema (PHP):</strong></p>
        <pre>&lt;?php echo do_shortcode('[fp_reservations]'); ?&gt;</pre>
    </div>
    
    <p style="text-align: center; margin-top: 40px;">
        <a href="<?php echo admin_url(); ?>" class="btn">Vai alla Dashboard</a>
        <a href="?refresh=<?php echo time(); ?>" class="btn">Aggiorna</a>
    </p>
</body>
</html>

