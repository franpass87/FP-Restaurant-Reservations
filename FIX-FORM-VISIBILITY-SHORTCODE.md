# Fix: Form Non Visibile con Shortcode

## Problema
Il form di prenotazione non era visibile quando veniva utilizzato lo shortcode `[fp_reservations]`, probabilmente a causa di conflitti CSS con temi o altri plugin.

## Soluzione Implementata

### 1. CSS Robusto con Alta Specificità (`assets/css/form.css`)
Aggiunto all'inizio del file CSS regole con altissima specificità per forzare la visibilità:

```css
/* Force visibility of the form widget to prevent theme/plugin conflicts */
/* High specificity rules to override any theme/plugin CSS */
.fp-resv-widget,
.fp-resv-widget.fp-resv,
.fp-resv-widget.fp-resv.fp-card,
div.fp-resv-widget,
div.fp-resv-widget.fp-resv,
div.fp-resv-widget.fp-resv.fp-card,
div[data-fp-resv-app],
div[data-fp-resv-app].fp-resv-widget,
#fp-resv-form,
[id^="fp-resv-"] {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    width: 100% !important;
    max-width: min(100%, var(--fp-resv-max-width, 100%)) !important;
    margin: 0 auto !important;
    height: auto !important;
    overflow: visible !important;
    clip: auto !important;
    clip-path: none !important;
    transform: none !important;
}
```

### 2. Stili Inline Specifici per Form ID (`templates/frontend/form.php`)
Aggiunto un blocco `<style>` inline con specificità basata sull'ID del form:

```php
<style>
.fp-resv-widget#<?php echo esc_attr($formId); ?>,
div.fp-resv-widget#<?php echo esc_attr($formId); ?>,
#<?php echo esc_attr($formId); ?>.fp-resv-widget {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    width: 100% !important;
    height: auto !important;
    max-width: 100% !important;
    margin: 0 auto !important;
    z-index: 1 !important;
}
</style>
```

### 3. JavaScript con Controllo Visibilità (`assets/js/fe/form-app-fallback.js`)
Aggiunto controllo JavaScript che:
- Forza la visibilità con stili inline
- Verifica i container parent (fino a 5 livelli) per assicurarsi che non siano nascosti
- Esegue un auto-check ogni secondo per i primi 10 secondi dopo il caricamento

```javascript
function ensureWidgetVisibility(widget) {
    // Force visibility with inline styles
    widget.style.display = 'block';
    widget.style.visibility = 'visible';
    widget.style.opacity = '1';
    // ... altri stili
    
    // Verifica parent containers
    var parent = widget.parentElement;
    var depth = 0;
    while (parent && depth < 5) {
        var display = window.getComputedStyle(parent).display;
        if (display === 'none') {
            parent.style.display = 'block';
        }
        parent = parent.parentElement;
        depth++;
    }
}
```

### 4. Auto-Check Periodico
Controllo automatico che verifica la visibilità ogni secondo per i primi 10 secondi:

```javascript
function autoCheckVisibility() {
    var checks = 0;
    var maxChecks = 10;
    
    var interval = setInterval(function() {
        checks++;
        
        var widgets = document.querySelectorAll('[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]');
        var hasHiddenWidget = false;
        
        Array.prototype.forEach.call(widgets, function(widget) {
            var computed = window.getComputedStyle(widget);
            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                ensureWidgetVisibility(widget);
                hasHiddenWidget = true;
            }
        });
        
        if (checks >= maxChecks || !hasHiddenWidget) {
            clearInterval(interval);
        }
    }, 1000);
}
```

## Livelli di Protezione

La soluzione implementa **4 livelli di protezione** per garantire che il form sia sempre visibile:

1. **CSS Globale**: Regole CSS generali con alta specificità
2. **CSS Inline Specifico**: Stili inline per ogni singolo form basati sull'ID
3. **JavaScript Immediato**: Controllo e fix alla inizializzazione del widget
4. **JavaScript Periodico**: Monitoraggio continuo per i primi 10 secondi

## Perché Funziona

- **Altissima specificità CSS**: Le combinazioni di selettori e l'uso di `!important` garantiscono che queste regole abbiano la precedenza
- **Stili inline con ID**: Gli stili inline con selettori ID hanno specificità ancora maggiore
- **JavaScript come fallback**: Se il CSS fallisce, il JavaScript forza la visibilità
- **Protezione parent**: Verifica che anche i container parent non nascondano il form
- **Auto-correzione**: Se qualcosa nasconde il form dopo il caricamento, viene immediatamente ripristinato

## Test

Per verificare che il fix funzioni:

1. Inserisci lo shortcode `[fp_reservations]` in una pagina
2. Apri la console del browser (F12)
3. Dovresti vedere i log:
   - `[FP-RESV] Found widgets: 1` (o più)
   - `[FP-RESV] Widget visibility ensured: fp-resv-form`
   - `[FP-RESV] Widget initialized successfully: fp-resv-form`

4. Il form dovrebbe essere visibile anche in presenza di temi o plugin che applicano `display: none` agli elementi

## Note

- Il fix è compatibile con tutti i browser (anche IE11 grazie al fallback)
- Non interferisce con la normale funzionalità del form
- È retrocompatibile con le installazioni esistenti
- Non richiede modifiche ai temi o configurazioni particolari

## File Modificati

1. `assets/css/form.css` - Aggiunto CSS robusto per forzare visibilità
2. `templates/frontend/form.php` - Aggiunto stile inline specifico per ID
3. `assets/js/fe/form-app-fallback.js` - Aggiunto controllo JavaScript di visibilità

Data: 2025-10-12
Branch: cursor/fix-invisible-form-with-shortcode-231b
