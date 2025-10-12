# 🔍 Checklist Diagnostica Produzione

## STEP 1: Verifica che hai fatto il PULL in produzione

**Sul server di produzione**, esegui:

```bash
ssh user@villadianella.it

# Vai nella cartella del plugin
cd /percorso/completo/wp-content/plugins/fp-restaurant-reservations

# Verifica branch corrente
git branch

# Verifica ultimo commit
git log -1 --oneline

# IMPORTANTE: Fai il pull!
git pull origin main
# oppure se sei su un branch specifico:
# git pull origin cursor/debug-agenda-reservation-loading-error-9725

# Verifica data modifica file
ls -lah assets/js/admin/agenda-app.js
```

**Il file deve mostrare data/ora RECENTE!**

## STEP 2: Verifica contenuto file in produzione

```bash
# Controlla che il file sia la versione aggiornata
grep -n "Tipo risposta:" assets/js/admin/agenda-app.js

# Dovrebbe trovare la riga con "Tipo risposta:"
# Se NON trova niente = file vecchio, devi fare git pull!
```

## STEP 3: Force refresh timestamp manualmente

**Opzione A - Via WP-CLI (CONSIGLIATO):**
```bash
cd /percorso/wordpress
wp option update fp_resv_last_upgrade $(date +%s) --autoload=no
wp cache flush
```

**Opzione B - Via Database diretto:**
```bash
# Accedi al database
mysql -u username -p database_name

# Esegui query
UPDATE wp_options 
SET option_value = UNIX_TIMESTAMP() 
WHERE option_name = 'fp_resv_last_upgrade';

# Verifica
SELECT option_name, option_value 
FROM wp_options 
WHERE option_name = 'fp_resv_last_upgrade';

# Esci
exit;
```

**Opzione C - Via PHP diretto:**
Crea file temporaneo `force-update.php` nella root di WordPress:

```php
<?php
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Permessi insufficienti');
}

$timestamp = time();
update_option('fp_resv_last_upgrade', $timestamp, false);

// Pulisci cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

echo "✅ Timestamp aggiornato: " . $timestamp . "\n";
echo "✅ Nuova versione asset: 0.1.10." . $timestamp . "\n";
echo "\nOra fai hard refresh browser (Ctrl+Shift+R)\n";

// Auto-elimina
unlink(__FILE__);
?>
```

Poi vai su: `https://www.villadianella.it/force-update.php`

## STEP 4: Pulisci TUTTE le cache

### Cache Browser (FONDAMENTALE)
1. **Chrome/Edge:**
   - F12 > Network tab
   - Spunta "Disable cache"
   - Ricarica con Ctrl+Shift+R

2. **Firefox:**
   - F12 > Network
   - Click sull'icona ingranaggio
   - Spunta "Disable HTTP cache"
   - Ricarica con Ctrl+Shift+R

3. **Safari:**
   - Menu Sviluppo > Svuota cache
   - Ricarica con Cmd+Shift+R

### Cache Server
```bash
# Redis (se presente)
redis-cli FLUSHALL

# Memcached (se presente)
echo "flush_all" | nc localhost 11211

# Cache file WordPress
rm -rf /path/to/wordpress/wp-content/cache/*

# OPcache PHP
sudo service php8.1-fpm reload
# oppure
sudo service php-fpm reload
```

### Cache Plugin WordPress
Se hai plugin di cache attivi (W3 Total Cache, WP Super Cache, etc.):

```bash
# Via WP-CLI
wp cache flush
wp transient delete --all
wp rewrite flush

# Oppure manualmente dal WordPress admin:
# Dashboard > Plugin > [Tuo plugin cache] > Purge/Clear Cache
```

### CDN/Cloudflare
Se usi Cloudflare o altro CDN:
1. Login su Cloudflare
2. Vai nella sezione Caching
3. Click su "Purge Everything"
4. Attendi 30 secondi

## STEP 5: Verifica nel browser

1. Apri Console (F12)
2. Vai nel tab **Network**
3. Ricarica la pagina con Ctrl+Shift+R
4. Filtra per `agenda-app.js`
5. Guarda:

```
Nome file: agenda-app.js
Query string: ?ver=0.1.10.XXXXXXXXXX (il numero deve essere RECENTE!)
Status: 200 (NON "from cache" o "from disk cache")
Size: ~51KB
```

6. Click sul file
7. Tab "Response"
8. Cerca la stringa "Tipo risposta:" nel codice
9. **Se la trovi = versione corretta**
10. **Se NON la trovi = versione vecchia ancora in cache**

## STEP 6: Verifica risposta API

Nel tab Network:
1. Filtra per `agenda?date=`
2. Click sulla richiesta
3. Tab "Response"
4. Copia la risposta e controlla:

```json
{
  "reservations": [...],
  "meta": {...},
  "stats": {...},
  "data": {...}
}
```

O qualcos'altro? Se è diverso, dimmi cosa vedi!

## STEP 7: Debug console

Nella Console dovresti vedere:

```
✅ VERSIONE CORRETTA:
[Agenda] Inizializzazione...
[Agenda] Caricamento prenotazioni...
[API] GET https://...
[API] Status: 200
[Agenda] ✓ Risposta ricevuta
[Agenda] Tipo risposta: object        <-- QUESTA RIGA È CHIAVE!
[Agenda] È array? false
[Agenda] Chiavi oggetto: ...
[Agenda] Ha reservations? true
[Agenda] ✓ Formato: Oggetto strutturato
[Agenda] ✓ Caricate X prenotazioni

❌ VERSIONE VECCHIA:
[Agenda] Errore nel caricamento: Error: Risposta API non valida
    at AgendaApp.loadReservations (agenda-app.js?ver=0.1.9.XXX:233:23)
```

## STEP 8: Ultima risorsa - Rinomina file

Se niente funziona, forza il cambio:

```bash
# Sul server
cd /path/to/plugin/assets/js/admin
mv agenda-app.js agenda-app-backup.js
cp agenda-app-backup.js agenda-app.js
touch agenda-app.js
```

Poi modifica il file PHP che lo carica:

```php
// In src/Domain/Reservations/AdminController.php
// Cambia temporaneamente:
$scriptUrl = Plugin::$url . 'assets/js/admin/agenda-app.js?nocache=' . time();
```

---

## 📊 Dimmi i Risultati

Dopo aver fatto questi step, dimmi:

1. ✅ Hai fatto git pull in produzione? Output di `git log -1`
2. ✅ Data file: output di `ls -lah assets/js/admin/agenda-app.js`
3. ✅ Timestamp aggiornato? output di query database
4. ✅ Versione nel browser: `?ver=0.1.10.XXXXXXXXXX`
5. ✅ Cosa vedi nella console? (copia errore completo)
6. ✅ Cosa vedi nel Network tab per la risposta API? (screenshot o JSON)

Con queste info capirò esattamente dove si blocca!
