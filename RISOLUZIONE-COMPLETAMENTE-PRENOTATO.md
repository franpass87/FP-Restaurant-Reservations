# Risoluzione: "Continua a dirmi completamente prenotato"

## Data: 2025-10-09
## Branch: cursor/continua-a-dire-completamente-prenotato-0ea4

---

## 🔍 Problema Identificato

Il sistema continua a mostrare "Completamente prenotato" anche dopo aver corretto i bug nel codice JavaScript. Questo accade perché **il browser sta usando una versione cached vecchia del JavaScript**, anche se i fix sono stati applicati correttamente.

### Verifica dei Fix Applicati

✅ **Fix 1 - Reset cache meal availability** (riga 591 di `assets/js/fe/onepage.js`):
```javascript
if ((fieldKey === 'date' || fieldKey === 'party') && valueChanged) {
    this.clearSlotSelection({ schedule: false });
    // Reset meal availability cache quando cambiano parametri critici
    this.state.mealAvailability = {};
}
```

✅ **Fix 2 - Verifica sempre disponibilità** (riga 825 di `assets/js/fe/onepage.js`):
```javascript
// Schedula sempre l'aggiornamento della disponibilità, anche se lo stato cached è 'full'
// perché i parametri (date, party) potrebbero essere cambiati
this.scheduleAvailabilityUpdate({ immediate: true });
```

✅ **File compilati aggiornati** (tutti modificati il 2025-10-09 alle 18:04):
- `assets/dist/fe/onepage.esm.js` ✅
- `assets/dist/fe/onepage.iife.js` ✅

---

## 🎯 Causa del Problema

Il plugin usa un sistema di **cache busting** basato su timestamp:

```php
// src/Core/Plugin.php
public static function assetVersion(): string
{
    // In debug mode, sempre timestamp corrente
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return self::VERSION . '.' . time();
    }
    
    // In produzione, usa timestamp salvato nel DB
    $upgradeTime = get_option('fp_resv_last_upgrade', false);
    return self::VERSION . '.' . (int) $upgradeTime;
}
```

### In Sviluppo (WP_DEBUG = true)
- ✅ Timestamp sempre aggiornato
- ✅ Browser ricarica sempre gli asset

### In Produzione (WP_DEBUG = false)
- ⚠️ Usa timestamp dal database `fp_resv_last_upgrade`
- ⚠️ Se non aggiornato, il browser usa la versione cached vecchia

---

## ✅ Soluzione Immediata

### Opzione 1: Via REST API (Consigliato)

```bash
curl -X POST "https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

### Opzione 2: Via WP-CLI (Se hai accesso SSH)

```bash
wp eval-file tools/refresh-cache.php
```

### Opzione 3: Via URL Admin (Con nonce di sicurezza)

1. Aggiungi questo codice al `functions.php` del tema:

```php
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node([
        'id'    => 'fp_resv_refresh_cache',
        'title' => '🔄 Refresh Cache Plugin',
        'href'  => wp_nonce_url(
            admin_url('admin.php?page=fp-resv-settings&fp_resv_refresh_cache=1'),
            'fp_resv_refresh_cache'
        ),
    ]);
}, 100);
```

2. Vai nell'admin WordPress
3. Clicca su "🔄 Refresh Cache Plugin" nella barra admin

### Opzione 4: Attiva WP_DEBUG Temporaneamente

Nel file `wp-config.php`:

```php
// Modalità debug - forza sempre il refresh degli asset
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false); // Non mostrare errori agli utenti
define('WP_DEBUG_LOG', true);       // Log degli errori
```

⚠️ **Ricorda di disattivarlo dopo aver verificato!**

---

## 🔄 Refresh Cache Browser (Lato Utente)

Dopo aver forzato il refresh lato server, gli utenti devono fare un **hard refresh** del browser:

### Windows/Linux
- **Chrome/Edge:** `Ctrl + Shift + R` o `Ctrl + F5`
- **Firefox:** `Ctrl + F5` o `Ctrl + Shift + R`

### Mac
- **Chrome/Edge:** `Cmd + Shift + R`
- **Firefox:** `Cmd + Shift + R`
- **Safari:** `Cmd + Option + R`

### Verifica nel Browser

1. Apri DevTools (`F12`)
2. Vai alla tab **Network**
3. Ricarica la pagina
4. Cerca il file `onepage.iife.js` o `onepage.esm.js`
5. Verifica che abbia un parametro `?ver=0.1.7.TIMESTAMP` con un timestamp recente

---

## 🧪 Test dopo la Risoluzione

### Test 1: Cambio Data
```
1. Seleziona data: Oggi
2. Seleziona meal: "Cena" → potrebbe essere "full"
3. Cambia data: Domani
4. Riseleziona meal: "Cena"
✅ ATTESO: Sistema verifica disponibilità per la nuova data
```

### Test 2: Cambio Party
```
1. Seleziona: 8 persone
2. Seleziona meal: "Cena" → potrebbe essere "full"
3. Cambia a: 2 persone
4. Riseleziona meal: "Cena"
✅ ATTESO: Mostra slot disponibili se presenti
```

### Test 3: Console Browser
```
1. Apri DevTools (F12)
2. Tab Console
3. Seleziona un meal
4. Cerca messaggi come:
   - "[FP-RESV] Availability updated"
   - "[FP-RESV] Meal availability state: ..."
✅ ATTESO: Nessun errore JavaScript
```

---

## 📊 Log Diagnostici (Per Debug Avanzato)

Il sistema ha logging attivo in `src/Domain/Reservations/Availability.php`.

Per vedere i log:

1. **Attiva WP_DEBUG_LOG** in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. **Controlla il file di log**: `wp-content/debug.log`

3. **Cerca messaggi come**:
```
[FP-RESV] resolveMealSettings - meal key: "cena"
[FP-RESV] Meal trovato: {"hours_definition": "..."}
[FP-RESV] resolveScheduleForDay - date: 2025-10-10, dayKey: thu
[FP-RESV] Schedule per il giorno: [...]
```

Questi log mostrano esattamente:
- Se il meal viene trovato nella configurazione
- Quali orari vengono parsati
- Perché uno schedule potrebbe essere vuoto

---

## 📋 Checklist Completa

### Per lo Sviluppatore

- [x] ✅ Fix applicati al codice sorgente (`onepage.js`)
- [x] ✅ File compilati aggiornati (`onepage.esm.js`, `onepage.iife.js`)
- [ ] ⚠️ Cache server aggiornata (esegui refresh cache)
- [ ] ⚠️ Verificato timestamp versione asset nel browser

### Per l'Amministratore

- [ ] Verifica configurazione orari in "Turni & disponibilità"
- [ ] Controlla che i meal abbiano `hours_definition` configurato
- [ ] Testa con diverse date e numero di persone
- [ ] Forza refresh cache tramite REST API o WP-CLI

### Per l'Utente Finale

- [ ] Hard refresh del browser (`Ctrl+Shift+R` o `Cmd+Shift+R`)
- [ ] Svuota cache del browser se necessario
- [ ] Controlla che gli slot orari vengano visualizzati

---

## 🔧 Automazione per il Futuro

### Deploy Automatico

Aggiungi al workflow di deploy (es. GitHub Actions):

```yaml
- name: Refresh Plugin Cache
  run: |
    wp eval-file tools/refresh-cache.php
```

### Hook di WordPress

Aggiungi in `functions.php` per refresh automatico dopo aggiornamenti:

```php
add_action('upgrader_process_complete', function($upgrader, $options) {
    if ($options['type'] === 'plugin' && 
        isset($options['plugins']) && 
        in_array('fp-restaurant-reservations/fp-restaurant-reservations.php', $options['plugins'])) {
        
        if (class_exists('FP\Resv\Core\Plugin')) {
            \FP\Resv\Core\Plugin::forceRefreshAssets();
        }
    }
}, 10, 2);
```

---

## 📚 Riferimenti

### Documenti Correlati
- `FIX-SLOT-ORARI-COMUNICAZIONE.md` - Fix originali applicati
- `ANALISI-DISPONIBILITA.md` - Analisi completa del problema
- `docs/CACHE-REFRESH-GUIDE.md` - Guida dettagliata cache refresh
- `docs/BUGFIX-ASSET-CACHE.md` - Documentazione sistema cache busting

### File Chiave
- `assets/js/fe/onepage.js` - Codice sorgente con fix
- `src/Core/Plugin.php` - Sistema versioning e cache busting
- `src/Frontend/WidgetController.php` - Caricamento asset
- `tools/refresh-cache.php` - Script per forzare refresh

---

## ✅ Verifica Finale

Dopo aver applicato la soluzione, verifica che:

1. ✅ Il timestamp degli asset sia aggiornato nel browser
2. ✅ I messaggi "Completamente prenotato" appaiano SOLO quando effettivamente non ci sono posti
3. ✅ Cambiando data/persone, il sistema verifichi sempre la disponibilità
4. ✅ Gli slot orari configurati nel backend vengano visualizzati correttamente
5. ✅ Non ci siano errori JavaScript nella console del browser

---

**Autore**: Background Agent  
**Data**: 2025-10-09  
**Status**: ✅ Soluzione Documentata

---

## 🚀 Azione Immediata Raccomandata

**Per risolvere il problema SUBITO:**

1. **Esegui il refresh della cache**:
   ```bash
   wp eval-file tools/refresh-cache.php
   ```
   
   OPPURE
   
   ```bash
   curl -X POST "https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache"
   ```

2. **Hard refresh nel browser**: `Ctrl+Shift+R` (Windows) o `Cmd+Shift+R` (Mac)

3. **Testa**: Cambia data/persone e verifica che la disponibilità venga ricalcolata correttamente

Il problema dovrebbe essere risolto! 🎉
