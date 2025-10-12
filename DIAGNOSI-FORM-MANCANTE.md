# Diagnosi: Form Mancante sulla Pagina

## 🔍 Problema Rilevato

Il form di prenotazione non compare più sulla pagina: https://www.villadianella.it/ristorante-vinci-toscana

## 📋 Cause Possibili

### 1. **Shortcode Mancante**
Il problema più comune è che lo shortcode `[fp_reservations]` non sia presente nel contenuto della pagina.

**Soluzione:**
1. Accedi al pannello WordPress
2. Vai su Pagine > Tutte le pagine
3. Cerca e modifica la pagina "ristorante-vinci-toscana"
4. Verifica che nel contenuto sia presente lo shortcode: `[fp_reservations]`
5. Se mancante, aggiungilo e salva

### 2. **Cache Attiva**
La cache del sito potrebbe mostrare una versione vecchia della pagina.

**Soluzione:**
1. Svuota la cache di WordPress (se usi un plugin di cache)
2. Svuota la cache del CDN (se presente)
3. Svuota la cache del browser (Ctrl+Shift+R o Cmd+Shift+R)

### 3. **JavaScript Non Caricato**
Il JavaScript che inizializza il form potrebbe non essere caricato correttamente.

**Soluzione:**
1. Apri la console del browser (F12)
2. Ricarica la pagina
3. Cerca errori JavaScript nella console
4. Se ci sono errori, controlla che i file JavaScript esistano:
   - `assets/dist/fe/onepage.esm.js`
   - `assets/dist/fe/onepage.iife.js`

### 4. **Build Assets Mancanti**
I file JavaScript compilati potrebbero non essere presenti.

**Soluzione:**
```bash
# Nella directory del plugin
npm install
npm run build
```

### 5. **Filtro WordPress che Blocca il Caricamento**
Un tema o plugin potrebbe impedire il caricamento degli asset.

**Soluzione:**
Verifica se c'è un filtro attivo che blocca il caricamento:
```php
// Nel file functions.php del tema o in un plugin
add_filter('fp_resv_frontend_should_enqueue', '__return_true');
```

## 🛠️ Strumenti Diagnostici

### Script di Diagnosi Automatica

È stato creato uno script diagnostico che verifica automaticamente tutti i potenziali problemi:

**Via Browser:**
1. Accedi come amministratore
2. Vai a: `https://yoursite.com/wp-content/plugins/fp-restaurant-reservations/tools/diagnose-form-visibility.php`

**Via WP-CLI:**
```bash
wp eval-file tools/diagnose-form-visibility.php
```

### Debug Logging

Il plugin ora include logging dettagliato quando `WP_DEBUG` è attivo:

1. Abilita il debug in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Controlla il file di log:
```bash
tail -f wp-content/debug.log
```

3. Cerca messaggi come:
   - `[FP-RESV] Form rendered successfully`
   - `[FP-RESV] WARNING: Form output is too short`
   - `[FP-RESV] CRITICAL: Context is not set`

## ✅ Checklist di Verifica

- [ ] Lo shortcode `[fp_reservations]` è presente nella pagina?
- [ ] La pagina è pubblicata (non in bozza)?
- [ ] La cache è stata svuotata?
- [ ] Il plugin è attivo?
- [ ] I file JavaScript esistono nella cartella `assets/dist/fe/`?
- [ ] Non ci sono errori nella console JavaScript del browser?
- [ ] Il debug log non mostra errori critici?

## 🔧 Soluzioni Rapide

### Soluzione 1: Verifica Shortcode
```bash
# Via WP-CLI - cerca pagine con lo shortcode
wp post list --post_type=page --s="[fp_reservations" --format=table
```

### Soluzione 2: Rebuild Assets
```bash
cd /path/to/plugin
npm ci
npm run build
```

### Soluzione 3: Forza Caricamento Assets
Aggiungi al file `functions.php` del tema:
```php
add_filter('fp_resv_frontend_should_enqueue', '__return_true');
```

### Soluzione 4: Test Shortcode Manuale
Crea una pagina di test con solo:
```
[fp_reservations]
```

## 📝 Logging Aggiunto

Sono stati aggiunti i seguenti controlli di logging:

1. **In `src/Frontend/Shortcodes.php`:**
   - Log della lunghezza dell'output del form
   - Log dell'ID e location del form
   - Warning se l'output è troppo corto

2. **In `templates/frontend/form.php`:**
   - Log se il context non è impostato
   - Log se l'array degli steps è vuoto
   - Log delle chiavi del context per debug

## 🎯 Prossimi Passi

1. Esegui lo script diagnostico
2. Controlla il debug log
3. Verifica la presenza dello shortcode nella pagina
4. Svuota tutte le cache
5. Controlla la console JavaScript del browser

## 📞 Se il Problema Persiste

Se dopo aver seguito tutti i passaggi il form non compare ancora:

1. Esporta il debug log completo
2. Fai uno screenshot della console JavaScript
3. Verifica l'HTML della pagina (View Source) per vedere se il form è presente ma nascosto
4. Controlla gli stili CSS che potrebbero nascondere il form:
   ```css
   .fp-resv-widget { display: block !important; visibility: visible !important; }
   ```

## 🔄 Modifiche Apportate

### File Modificati:
1. `src/Frontend/Shortcodes.php` - Aggiunto logging dettagliato
2. `templates/frontend/form.php` - Aggiunto logging per context e steps
3. `tools/diagnose-form-visibility.php` - Nuovo script diagnostico completo

### Test Consigliati:
```bash
# Test 1: Verifica presenza shortcode
wp post get $(wp post list --post_type=page --name=ristorante-vinci-toscana --field=ID) --field=post_content

# Test 2: Test rendering shortcode
wp eval 'echo do_shortcode("[fp_reservations]");' | head -n 20

# Test 3: Verifica assets
ls -lh assets/dist/fe/onepage.*
```

## 🌐 Link Utili

- [Documentazione Plugin](../README.md)
- [Guida Rapida](../QUICK-START.md)
- [Changelog](../CHANGELOG.md)
