<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Form Prenotazioni</title>
    <?php
    // Bootstrap WordPress
    require_once __DIR__ . '/../../../wp-load.php';
    
    // Force debug mode
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    
    // Enqueue scripts and styles
    do_action('wp_enqueue_scripts');
    wp_head();
    ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 10px 0;
        }
        .test-success { background: #e8f5e9; border-color: #4caf50; }
        .test-error { background: #ffebee; border-color: #f44336; }
    </style>
</head>
<body>
    <div class="test-header">
        <h1>🧪 Test Form Prenotazioni - Villa Dianella</h1>
        <p>Questa pagina serve per testare il form di prenotazione.</p>
    </div>

    <?php
    // Check if shortcode is registered
    global $shortcode_tags;
    if (isset($shortcode_tags['fp_reservations'])) {
        echo '<div class="test-info test-success">';
        echo '<strong>✓ Shortcode registrato</strong><br>';
        echo 'Lo shortcode [fp_reservations] è disponibile.';
        echo '</div>';
    } else {
        echo '<div class="test-info test-error">';
        echo '<strong>✗ Shortcode NON registrato</strong><br>';
        echo 'Il plugin potrebbe non essere attivo o non caricato correttamente.';
        echo '</div>';
    }

    // Check if plugin is active
    if (class_exists('FP\\Resv\\Core\\Plugin')) {
        echo '<div class="test-info test-success">';
        echo '<strong>✓ Plugin attivo</strong><br>';
        echo 'Versione: ' . FP\Resv\Core\Plugin::VERSION;
        echo '</div>';
    } else {
        echo '<div class="test-info test-error">';
        echo '<strong>✗ Plugin NON attivo</strong>';
        echo '</div>';
    }
    ?>

    <div class="test-header">
        <h2>Form di Prenotazione</h2>
        <p><em>Il form dovrebbe apparire qui sotto. Se non lo vedi, controlla i messaggi di errore sopra o nella console del browser (F12).</em></p>
    </div>

    <?php
    // Render the shortcode
    echo do_shortcode('[fp_reservations]');
    ?>

    <div class="test-header" style="margin-top: 30px;">
        <h2>📝 Istruzioni</h2>
        <ol>
            <li>Se vedi il form sopra, <strong>funziona tutto!</strong></li>
            <li>Se non vedi il form:
                <ul>
                    <li>Apri la console del browser (F12)</li>
                    <li>Cerca messaggi che iniziano con <code>[FP-RESV]</code></li>
                    <li>Controlla i log PHP in <code>wp-content/debug.log</code></li>
                </ul>
            </li>
            <li>Una volta verificato che funziona, <strong>elimina questo file</strong> per sicurezza</li>
        </ol>
    </div>

    <?php wp_footer(); ?>
    
    <script>
        // Log JavaScript info
        console.log('[TEST] Pagina di test caricata');
        console.log('[TEST] Cerca widget nel DOM...');
        
        setTimeout(() => {
            const widgets = document.querySelectorAll('[data-fp-resv-app], .fp-resv-widget, [data-fp-resv]');
            console.log('[TEST] Widget trovati:', widgets.length);
            
            if (widgets.length === 0) {
                console.error('[TEST] ❌ Nessun widget trovato! Il form non è stato renderizzato.');
                alert('⚠️ ATTENZIONE: Il form non è stato trovato nel DOM.\n\nControlla i log della console per i dettagli.');
            } else {
                console.log('[TEST] ✓ Widget trovato:', widgets[0]);
                widgets.forEach((widget, i) => {
                    console.log(`[TEST] Widget ${i+1}:`, {
                        id: widget.id,
                        classes: widget.className,
                        hasData: widget.hasAttribute('data-fp-resv'),
                        visible: window.getComputedStyle(widget).display !== 'none'
                    });
                });
            }
        }, 1000);
    </script>
</body>
</html>

