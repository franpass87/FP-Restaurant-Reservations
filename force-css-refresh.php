<?php
/**
 * Force CSS Cache Refresh
 * Esegui questo script per vedere le ultime modifiche CSS
 */

// Timestamp corrente per cache busting
$timestamp = time();

// URL del sito (modifica se necessario)
$site_url = 'http://localhost'; // Cambia con il tuo URL

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Cache Refresh - Test Form</title>
    
    <!-- CSS con cache buster -->
    <link rel="stylesheet" href="./assets/css/form.css?v=<?php echo $timestamp; ?>">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .info {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #000;
        }
        .success {
            color: #16a34a;
            font-weight: bold;
        }
        .timestamp {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="info">
        <h1>🔄 CSS Cache Refresh</h1>
        <p class="success">✅ CSS caricato con timestamp: <span class="timestamp"><?php echo $timestamp; ?></span></p>
        <p>Questo file carica il CSS con un parametro di cache busting.</p>
        <p><strong>Istruzioni:</strong></p>
        <ol>
            <li>Apri questo file nel browser: <code>force-css-refresh.php</code></li>
            <li>Dovresti vedere le tue modifiche CSS applicate</li>
            <li>Se non vedi le modifiche, premi <kbd>Ctrl+Shift+R</kbd> (Windows) o <kbd>Cmd+Shift+R</kbd> (Mac)</li>
        </ol>
    </div>

    <!-- FORM DI TEST -->
    <div class="fp-resv-widget fp-resv fp-card" id="fp-resv-default">
        <!-- Progress Bar -->
        <div class="fp-resv-progress">
            <ul class="fp-progress" style="--fp-progress-fill: 16.666%;">
                <li class="fp-progress__item" aria-current="step" data-state="active">
                    <span class="fp-progress__index">01</span>
                    <span class="fp-progress__label">Servizio</span>
                </li>
                <li class="fp-progress__item">
                    <span class="fp-progress__index">02</span>
                    <span class="fp-progress__label">Data</span>
                </li>
                <li class="fp-progress__item">
                    <span class="fp-progress__index">03</span>
                    <span class="fp-progress__label">Persone</span>
                </li>
            </ul>
        </div>

        <!-- Section di test -->
        <div class="fp-section">
            <h2>SERVIZIO</h2>
            <p>Scegli il servizio</p>
            <p>Seleziona il tipo di servizio desiderato.</p>

            <div class="fp-meals">
                <h3>Scegli il servizio</h3>
                
                <div class="fp-pills-list">
                    <button class="fp-pill fp-meal-pill" data-active="true" aria-pressed="true">
                        <span class="fp-meal-pill__label">
                            <span class="fp-meal-pill__icon">🍽️</span>
                            Pranzo
                        </span>
                    </button>
                    
                    <button class="fp-pill fp-meal-pill">
                        <span class="fp-meal-pill__label">
                            <span class="fp-meal-pill__icon">🥂</span>
                            Aperitivo
                        </span>
                    </button>
                    
                    <button class="fp-pill fp-meal-pill">
                        <span class="fp-meal-pill__label">
                            <span class="fp-meal-pill__icon">🌙</span>
                            Cena
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottoni -->
        <div class="fp-section" style="display: flex; gap: 1rem;">
            <button class="fp-btn fp-btn--primary">Bottone Primario</button>
            <button class="fp-btn fp-btn--secondary">Bottone Secondario</button>
        </div>

        <!-- Inputs -->
        <div class="fp-section">
            <label class="fp-field__label">Nome</label>
            <input type="text" class="fp-input" placeholder="Il tuo nome">
        </div>
    </div>

    <script>
        console.log('CSS caricato con timestamp:', <?php echo $timestamp; ?>);
        console.log('Verifica che tutti i fix siano applicati:');
        console.log('- Progress bar senza barra di riempimento ✓');
        console.log('- Padding 0 su mobile ✓');
        console.log('- Section con padding 20px ✓');
        console.log('- Progress item min-width 100% su mobile ✓');
        console.log('- Colori nero/bianco con contrasto alto ✓');
        console.log('- Nessun bordino blu su focus ✓');
    </script>
</body>
</html>

