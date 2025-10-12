# 📋 Checklist Deploy Produzione

## 🚨 PROBLEMA IDENTIFICATO

Quando fai deploy manuale del codice (push git), WordPress continua a servire i file JS/CSS vecchi dalla cache del browser perché il parametro `ver=` (versione) non cambia automaticamente.

## ✅ SOLUZIONE - Dopo ogni deploy:

### Opzione 1: Script Force Refresh (CONSIGLIATO)
```bash
# 1. Dopo il push/deploy, naviga su:
https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1

# 2. Oppure via WP-CLI (se disponibile):
wp eval-file force-refresh-assets.php

# 3. Oppure manualmente via WordPress admin:
# - Vai su: Dashboard > Prenotazioni > Agenda
# - Aggiungi `&force_refresh_assets=1` all'URL
```

### Opzione 2: Via Database (se necessario)
```sql
-- Aggiorna il timestamp manualmente
UPDATE wp_options 
SET option_value = UNIX_TIMESTAMP() 
WHERE option_name = 'fp_resv_last_upgrade';

-- Pulisci transient
DELETE FROM wp_options 
WHERE option_name LIKE '%_transient_%fp_resv%';
```

### Opzione 3: Via PHP in wp-config.php (temporaneo)
```php
// Aggiungi temporaneamente in wp-config.php per forzare debug mode
define('WP_DEBUG', true);
// Poi ricordati di toglierlo!
```

## 🔍 Come Verificare che Funziona

1. **Console Browser** (F12):
   ```javascript
   // Cerca questa riga:
   agenda-app.js?ver=0.1.10.1234567890
   // Il numero dopo 0.1.10. deve essere recente (timestamp unix)
   ```

2. **Network Tab**:
   - Filtra per `agenda-app.js`
   - Verifica che venga scaricato dal server (Status 200) e non dalla cache (from disk cache)

3. **Controllo Versione File**:
   ```bash
   # Verifica data modifica file in produzione
   ls -lah assets/js/admin/agenda-app.js
   # Deve mostrare data/ora recente
   ```

## 🎯 Procedura Deploy Completa

```bash
# 1. Commit e push modifiche
git add assets/js/admin/agenda-app.js
git commit -m "Fix: Migliorato handling errori API"
git push origin main

# 2. Deploy in produzione (dipende dal tuo setup)
# - SSH: git pull origin main
# - FTP: Upload file manualmente
# - CI/CD: Trigger pipeline

# 3. IMPORTANTE: Force refresh assets
# Naviga su URL con parametro o esegui script

# 4. Hard refresh browser
# Ctrl+Shift+R (Windows/Linux)
# Cmd+Shift+R (Mac)

# 5. Verifica in console che la versione sia aggiornata
```

## ⚠️ Note Importanti

- Il parametro `ver=` è controllato da `Plugin::assetVersion()` in `src/Core/Plugin.php`
- In modalità debug (`WP_DEBUG = true`), il parametro usa sempre `time()` (aggiornato ad ogni caricamento)
- In produzione usa `get_option('fp_resv_last_upgrade')` che è stabile fino a quando non lo aggiorni manualmente
- **Questa è una feature, non un bug**: serve per non invalidare la cache del browser ad ogni richiesta

## 🔧 Se il Problema Persiste

1. Verifica che il file sia stato effettivamente caricato:
   ```bash
   ssh user@server
   cd /path/to/wordpress/wp-content/plugins/fp-restaurant-reservations
   ls -lah assets/js/admin/agenda-app.js
   cat assets/js/admin/agenda-app.js | grep "loadReservations" | head -20
   ```

2. Controlla permessi file:
   ```bash
   chmod 644 assets/js/admin/agenda-app.js
   ```

3. Pulisci cache server (se presente):
   - Redis: `redis-cli FLUSHALL`
   - Memcached: `telnet localhost 11211` poi `flush_all`
   - Varnish: `varnishadm "ban req.url ~ /"`
   - Nginx FastCGI: `rm -rf /var/cache/nginx/*`

4. Pulisci cache CDN (se presente):
   - Cloudflare: Dashboard > Cache > Purge Everything
   - Altri CDN: Purge/Invalidate cache

## 📝 Aggiungi al Hook di Deploy

Per automatizzare, aggiungi al tuo script di deploy:

```bash
#!/bin/bash
# deploy.sh

echo "Deploy in corso..."
git pull origin main

echo "Force refresh assets..."
wp eval-file force-refresh-assets.php

echo "Deploy completato! ✅"
```
