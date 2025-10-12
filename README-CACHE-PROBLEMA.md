# 🐛 Problema Cache Assets - RISOLTO

## Sintomi
L'agenda in produzione caricava una versione vecchia del file JavaScript (`agenda-app.js`), causando errori nella gestione delle risposte API. L'errore mostrava:

```
[Agenda] Errore nel caricamento: Error: Risposta API non valida
at AgendaApp.loadReservations (agenda-app.js?ver=0.1.9.1760037108:233:23)
```

Ma nel codice sorgente aggiornato, la riga 233 conteneva codice completamente diverso.

## Causa Root
Il sistema di cache busting di WordPress usa un parametro `ver=X.Y.Z.timestamp` per invalidare la cache del browser quando i file cambiano. Il timestamp viene gestito dall'opzione `fp_resv_last_upgrade` che viene aggiornata automaticamente solo durante:
- Attivazione plugin
- Upgrade plugin tramite WordPress admin

Quando fai **deploy manuale** del codice (git push, FTP, SSH), questa opzione non viene aggiornata automaticamente, quindi il browser continua a caricare i file vecchi dalla cache.

## Soluzione Implementata

### 1. Force Refresh via URL
Ora puoi forzare il refresh degli asset aggiungendo `?force_refresh_assets=1` all'URL dell'agenda:

```
https://tuosito.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1
```

Questo:
- Aggiorna il timestamp di `fp_resv_last_upgrade`
- Pulisce tutte le cache WordPress
- Invalida i transient del plugin
- Fa redirect automatico per rimuovere il parametro

### 2. Script Standalone
È disponibile anche lo script `force-refresh-assets.php` che può essere eseguito:
- Navigando direttamente: `https://tuosito.it/wp-content/plugins/fp-restaurant-reservations/force-refresh-assets.php`
- Via WP-CLI: `wp eval-file force-refresh-assets.php`

### 3. Metodo Manuale Plugin
In `src/Core/Plugin.php` è disponibile il metodo statico:

```php
\FP\Resv\Core\Plugin::forceRefreshAssets();
```

Che può essere chiamato da qualsiasi punto del codice WordPress.

## Procedura Deploy Corretta

```bash
# 1. Push modifiche
git push origin main

# 2. Deploy in produzione
ssh user@server "cd /path/to/plugin && git pull"

# 3. IMPORTANTE: Force refresh assets
# Vai su: admin.php?page=fp-resv-agenda&force_refresh_assets=1

# 4. Hard refresh browser
# Ctrl+Shift+R (Windows/Linux) o Cmd+Shift+R (Mac)

# 5. Verifica versione in console browser
# Cerca: agenda-app.js?ver=0.1.10.NUOVO_TIMESTAMP
```

## Verifica

### Nel Browser
1. Apri Console (F12)
2. Vai nel tab Network
3. Filtra per `agenda-app.js`
4. Verifica che il parametro `ver=` abbia un timestamp recente
5. Lo status deve essere `200` (non "from disk cache")

### Nel Database
```sql
SELECT option_value FROM wp_options WHERE option_name = 'fp_resv_last_upgrade';
```
Il valore deve essere un timestamp unix recente.

### Via SSH
```bash
# Verifica data ultima modifica file
ls -lah assets/js/admin/agenda-app.js

# Confronta con timestamp nel database
wp option get fp_resv_last_upgrade
```

## Modifiche ai File

1. **src/Domain/Reservations/AdminController.php**
   - Aggiunto check per parametro `force_refresh_assets=1`
   - Chiama automaticamente `Plugin::forceRefreshAssets()`
   - Fa redirect per pulire URL

2. **src/Core/Plugin.php**
   - Metodo `assetVersion()` già esistente (gestisce versioning)
   - Metodo `forceRefreshAssets()` già esistente (force refresh)
   - Metodo `onUpgrade()` chiamato automaticamente durante upgrade plugin

3. **force-refresh-assets.php** (nuovo)
   - Script standalone per force refresh manuale
   - Può essere eseguito anche fuori da WordPress admin

4. **DEPLOY-CHECKLIST.md** (nuovo)
   - Guida completa per procedura deploy
   - Troubleshooting cache issues

## Prevenzione Futura

### Opzione A: Automatizza nel Deploy Script
```bash
#!/bin/bash
git pull origin main
wp option update fp_resv_last_upgrade $(date +%s) --autoload=no
wp cache flush
```

### Opzione B: Abilita Debug Mode Temporaneamente
```php
// In wp-config.php (solo durante sviluppo)
define('WP_DEBUG', true);
```
Questo fa sì che `assetVersion()` usi sempre `time()` invece del timestamp statico.

### Opzione C: Usa Hook Git
Crea `.git/hooks/post-merge`:
```bash
#!/bin/bash
echo "Aggiornamento timestamp asset..."
wp option update fp_resv_last_upgrade $(date +%s) --autoload=no
```

## Lesson Learned

1. **Il caching è una feature, non un bug**: serve per performance
2. **Deploy manuale richiede step aggiuntivo**: invalidare cache asset
3. **Browser cache è aggressiva**: usa hard refresh (Ctrl+Shift+R)
4. **Timestamp statico = cache stabile**: ottimo per produzione, ma richiede gestione
5. **Debug mode = no cache**: utile per sviluppo, non per produzione

## File Correlati
- `src/Core/Plugin.php` - Sistema versioning e cache busting
- `src/Domain/Reservations/AdminController.php` - Hook per force refresh
- `force-refresh-assets.php` - Script standalone
- `DEPLOY-CHECKLIST.md` - Guida deploy completa
