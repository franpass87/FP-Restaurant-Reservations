# ⚡ ISTRUZIONI IMMEDIATE - Risolvi Problema Agenda

## 🎯 Cosa Fare ADESSO in Produzione

### Step 1: Deploy il Codice Aggiornato
```bash
# SSH sul server
ssh user@tuoserver.it

# Naviga nella cartella del plugin
cd /path/to/wordpress/wp-content/plugins/fp-restaurant-reservations

# Pull delle modifiche
git pull origin cursor/debug-agenda-reservation-loading-error-9725
# oppure se hai fatto merge su main:
# git pull origin main
```

### Step 2: Force Refresh della Cache
**OPZIONE A - Via Browser (PIÙ SEMPLICE):**

Vai su questo URL (sostituisci con il tuo dominio):
```
https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1
```

**OPZIONE B - Via WP-CLI (se disponibile):**
```bash
wp option update fp_resv_last_upgrade $(date +%s) --autoload=no
wp cache flush
```

**OPZIONE C - Via Database:**
```sql
UPDATE wp_options 
SET option_value = UNIX_TIMESTAMP() 
WHERE option_name = 'fp_resv_last_upgrade';
```

### Step 3: Hard Refresh nel Browser
1. Apri l'agenda: https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda
2. Premi **Ctrl + Shift + R** (Windows/Linux) o **Cmd + Shift + R** (Mac)
3. Apri la Console (F12)

### Step 4: Verifica che Funzioni
Nella console del browser dovresti vedere:

```
✅ FUNZIONA:
[Agenda] Inizializzazione...
[Agenda] Caricamento prenotazioni...
[API] GET https://...
[Agenda] Tipo risposta: object
[Agenda] È array? false
[Agenda] Ha reservations? true
[Agenda] ✓ Formato: Oggetto strutturato
[Agenda] ✓ Caricate X prenotazioni con successo

❌ NON FUNZIONA (versione vecchia):
[Agenda] Errore nel caricamento: Error: Risposta API non valida
    at AgendaApp.loadReservations (agenda-app.js?ver=0.1.9.XXX:233:23)
```

### Step 5: Verifica Versione File
Nella console vai nel tab **Network**, filtra per `agenda-app.js` e verifica:

```
✅ VERSIONE CORRETTA:
agenda-app.js?ver=0.1.10.1728XXX  (timestamp recente)
Status: 200 (from server, non "from cache")

❌ VERSIONE VECCHIA:
agenda-app.js?ver=0.1.9.XXX  (versione vecchia)
Status: 304 o "from disk cache"
```

## 🔍 Se Non Funziona Ancora

### Problema: Il file JS ha ancora versione vecchia

**Soluzione 1 - Pulisci Cache Browser Manualmente:**
```
Chrome/Edge:
F12 > Network tab > Disable cache (checkbox) > Ricarica pagina

Firefox:
F12 > Network tab > Ingranaggio > Disable HTTP cache > Ricarica

Safari:
Sviluppo > Svuota cache > Ricarica
```

**Soluzione 2 - Verifica che il file sia stato deployato:**
```bash
ssh user@server
cd /path/to/plugin
ls -lah assets/js/admin/agenda-app.js
# Deve mostrare data/ora recente

# Verifica contenuto
grep "Tipo risposta:" assets/js/admin/agenda-app.js
# Se trova la stringa, il file è aggiornato
```

**Soluzione 3 - Pulisci Cache Server:**
```bash
# Se hai Redis
redis-cli FLUSHALL

# Se hai Memcached
echo "flush_all" | nc localhost 11211

# Se hai cache file WordPress
rm -rf /path/to/wordpress/wp-content/cache/*
```

**Soluzione 4 - Pulisci Cache CDN (Cloudflare, ecc.):**
- Cloudflare: Dashboard > Cache > Purge Everything
- Altri CDN: cerca opzione "Invalidate cache" o "Purge cache"

### Problema: L'errore persiste anche con versione corretta

**Verifica risposta API:**
1. Apri Console (F12)
2. Vai nel tab Network
3. Filtra per `agenda?date=`
4. Clicca sulla richiesta
5. Vai nel tab Response
6. Verifica che la risposta sia JSON valido

**Se la risposta è HTML o PHP error:**
- C'è un errore PHP lato server
- Controlla i log PHP: `/var/log/php-error.log` o simile
- Abilita debug WordPress temporaneamente:
  ```php
  // In wp-config.php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```

**Se la risposta è JSON ma con struttura inaspettata:**
- Fai uno screenshot della risposta
- Segnala il formato che stai ricevendo

## 📞 Supporto

Se dopo questi step il problema persiste, fornisci:

1. Screenshot della console con l'errore completo
2. Screenshot del Network tab con la richiesta API
3. Output del comando: `ls -lah assets/js/admin/agenda-app.js`
4. Risultato della query: `SELECT option_value FROM wp_options WHERE option_name = 'fp_resv_last_upgrade'`
5. Versione WordPress: Dashboard > Aggiornamenti

## 🎓 Perché è Successo

Il problema era dovuto alla **cache del browser**:
- WordPress usa un parametro `ver=X.Y.Z.timestamp` per invalidare la cache
- Il timestamp viene preso da un'opzione del database (`fp_resv_last_upgrade`)
- Questa opzione si aggiorna solo durante l'upgrade ufficiale del plugin
- Quando fai deploy manuale (git push), l'opzione non cambia → browser usa cache vecchia
- Soluzione: forzare manualmente l'aggiornamento del timestamp

## ⚙️ Prevenzione Futura

Aggiungi al tuo script di deploy:

```bash
#!/bin/bash
# deploy.sh

echo "📦 Deploy in corso..."
git pull origin main

echo "♻️  Force refresh assets..."
wp option update fp_resv_last_upgrade $(date +%s) --autoload=no
wp cache flush

echo "✅ Deploy completato!"
echo "🔗 Apri: https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda"
echo "💡 Ricordati di fare hard refresh (Ctrl+Shift+R)"
```

Oppure usa sempre l'URL con parametro:
```
https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1
```
