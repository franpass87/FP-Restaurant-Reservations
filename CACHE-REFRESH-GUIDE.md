# Guida al Refresh della Cache del Plugin

## Problema Risolto

Il plugin utilizza un sistema di versioning degli asset (CSS/JS) basato su timestamp per forzare il refresh della cache del browser. Questo documento spiega come funziona e come usarlo.

## Come Funziona

### In Sviluppo (WP_DEBUG = true)

Quando `WP_DEBUG` è attivo nel `wp-config.php`, il plugin usa automaticamente il timestamp corrente per ogni richiesta di asset. Questo significa che **non è necessario fare nulla** - gli asset vengono sempre ricaricati ad ogni refresh della pagina.

```php
// wp-config.php
define('WP_DEBUG', true);
```

### In Produzione (WP_DEBUG = false)

In produzione, il plugin usa un timestamp salvato nel database che viene aggiornato solo quando:
1. Il plugin viene attivato per la prima volta
2. Il plugin viene aggiornato tramite l'upgrader di WordPress
3. Si forza manualmente il refresh della cache

## Metodi per Forzare il Refresh della Cache in Produzione

### 1. Via REST API (Consigliato per CI/CD)

```bash
curl -X POST "https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

### 2. Via WP-CLI (Consigliato per Deploy via SSH)

```bash
wp eval-file tools/refresh-cache.php
```

### 3. Via URL Admin (Manuale)

Aggiungi `?fp_resv_refresh_cache=1` a qualsiasi pagina admin del plugin:

```
https://tuosito.com/wp-admin/admin.php?page=fp-resv-settings&fp_resv_refresh_cache=1
```

### 4. Via Codice PHP

Se hai accesso al codice WordPress (es. in un must-use plugin):

```php
if (class_exists('FP\Resv\Core\Plugin')) {
    \FP\Resv\Core\Plugin::forceRefreshAssets();
}
```

## Integrazione nel Processo di Deploy

### Deploy Automatico (CI/CD)

Aggiungi al tuo workflow di deploy:

```yaml
# GitHub Actions example
- name: Refresh plugin cache
  run: |
    wp eval-file tools/refresh-cache.php
```

### Deploy Manuale via FTP/SFTP

Dopo aver caricato i file aggiornati:

1. Accedi all'admin di WordPress
2. Vai su `Impostazioni → FP Restaurant Reservations`
3. Aggiungi `&fp_resv_refresh_cache=1` all'URL e premi Invio
4. Vedrai un messaggio di conferma

### Deploy via ZIP

Quando carichi il plugin come ZIP tramite WordPress:
- ✅ La cache viene aggiornata automaticamente
- ✅ Non serve fare nulla

## Verifica che il Cache Refresh Funzioni

1. Ispeziona un file CSS/JS nel browser
2. Controlla il parametro `ver` nell'URL:
   ```
   /wp-content/plugins/fp-restaurant-reservations/assets/css/admin-agenda.css?ver=0.1.6.1728234567
   ```
3. Il numero dopo l'ultimo punto è il timestamp
4. Dopo il refresh della cache, questo numero dovrebbe cambiare

## Risoluzione Problemi

### Gli asset non si aggiornano anche dopo il refresh

1. **Controlla se WP_DEBUG è attivo**: Se sì, dovrebbe funzionare sempre
2. **Svuota la cache del server**: Se usi un plugin di caching (WP Super Cache, W3 Total Cache, etc.), svuota anche quella
3. **Svuota la cache CDN**: Se usi Cloudflare o altro CDN, purga la cache
4. **Hard refresh nel browser**: Premi `Ctrl+F5` (Windows) o `Cmd+Shift+R` (Mac)

### Come verificare che WP_DEBUG sia attivo

```bash
wp config get WP_DEBUG
```

Oppure controlla in `wp-config.php`:
```php
define('WP_DEBUG', true);  // ✅ Cache auto-refresh attivo
define('WP_DEBUG', false); // ❌ Serve refresh manuale
```

## Best Practices

1. **Sviluppo locale**: Usa sempre `WP_DEBUG = true`
2. **Staging**: Usa `WP_DEBUG = true` per test
3. **Produzione**: Usa `WP_DEBUG = false` e fai refresh manuale dopo ogni deploy
4. **Automatizza**: Integra il refresh nel processo di deploy

## File Coinvolti

- `src/Core/Plugin.php`: Contiene `assetVersion()` e `forceRefreshAssets()`
- `src/Domain/Diagnostics/REST.php`: Endpoint REST per refresh
- `tools/refresh-cache.php`: Script helper per refresh via WP-CLI
- Tutti i file che fanno `wp_enqueue_script()` o `wp_enqueue_style()` usano `Plugin::assetVersion()`
