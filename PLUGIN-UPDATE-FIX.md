# Fix: Plugin Non Si Aggiorna - Problema Cache Asset

## Problema Identificato

Il plugin non si aggiornava dopo le modifiche perché la versione degli asset CSS/JS rimaneva la stessa. Il sistema di versioning usava solo il timestamp `fp_resv_last_upgrade` che veniva aggiornato solo durante l'upgrade ufficiale via WordPress, non durante lo sviluppo o deploy manuale.

## Soluzione Implementata

### 1. Auto-refresh in Sviluppo

Modificato `src/Core/Plugin.php::assetVersion()` per usare automaticamente il timestamp corrente quando `WP_DEBUG` è attivo:

```php
public static function assetVersion(): string
{
    // In debug mode, always use current timestamp to bust cache
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return self::VERSION . '.' . time();
    }
    
    // In production, use upgrade timestamp for stable caching
    $upgradeTime = get_option('fp_resv_last_upgrade', 0);
    if ($upgradeTime === 0) {
        $upgradeTime = time();
        update_option('fp_resv_last_upgrade', $upgradeTime);
    }
    
    return self::VERSION . '.' . $upgradeTime;
}
```

**Benefici:**
- ✅ Con `WP_DEBUG = true`, gli asset si ricaricano automaticamente ad ogni richiesta
- ✅ Non serve più cancellare manualmente la cache durante lo sviluppo
- ✅ In produzione, la cache rimane stabile per prestazioni ottimali

### 2. Metodo per Forzare Refresh

Aggiunta la funzione `forceRefreshAssets()` in `src/Core/Plugin.php`:

```php
public static function forceRefreshAssets(): void
{
    update_option('fp_resv_last_upgrade', time());
    
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    CacheManager::invalidateAll();
    
    Logging::log('plugin', 'Asset cache manually refreshed', [
        'version' => self::VERSION,
        'timestamp' => time(),
    ]);
}
```

### 3. Endpoint REST API

Aggiunto endpoint REST in `src/Domain/Diagnostics/REST.php`:

```
POST /wp-json/fp-resv/v1/diagnostics/refresh-cache
```

Questo permette di forzare il refresh della cache via API, utile per:
- Deploy automatici (CI/CD)
- Script di deploy
- Refresh manuale da strumenti esterni

### 4. Script Helper

Creato `tools/refresh-cache.php` che supporta:
- Esecuzione via WP-CLI: `wp eval-file tools/refresh-cache.php`
- Refresh via URL admin: `?fp_resv_refresh_cache=1`
- Istruzioni chiare per tutti i metodi di utilizzo

### 5. Documentazione Completa

Creato `CACHE-REFRESH-GUIDE.md` con:
- Spiegazione completa del sistema
- Tutti i metodi per forzare il refresh
- Best practices per sviluppo e produzione
- Troubleshooting
- Esempi di integrazione in CI/CD

## File Modificati

1. **src/Core/Plugin.php**
   - Modificato `assetVersion()` per supportare auto-refresh con WP_DEBUG
   - Aggiunto `forceRefreshAssets()` per refresh manuale

2. **src/Domain/Diagnostics/REST.php**
   - Aggiunto endpoint `/diagnostics/refresh-cache`
   - Aggiunto metodo `handleRefreshCache()`

3. **tools/refresh-cache.php** (nuovo)
   - Script helper per refresh via WP-CLI e URL

4. **CACHE-REFRESH-GUIDE.md** (nuovo)
   - Documentazione completa del sistema di cache

## Come Usare la Fix

### Durante Sviluppo

Nel `wp-config.php`:
```php
define('WP_DEBUG', true);
```

✅ Gli asset si ricaricano automaticamente - non serve fare nulla!

### In Produzione

Dopo ogni deploy:

**Opzione 1 - WP-CLI (consigliata):**
```bash
wp eval-file tools/refresh-cache.php
```

**Opzione 2 - URL Admin:**
```
https://tuosito.com/wp-admin/admin.php?page=fp-resv-settings&fp_resv_refresh_cache=1
```

**Opzione 3 - REST API:**
```bash
curl -X POST "https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## Verifica che Funzioni

1. Apri DevTools del browser
2. Vai alla tab Network
3. Carica una pagina del plugin
4. Controlla l'URL di un file CSS/JS:
   ```
   admin-agenda.css?ver=0.1.6.1728234567
   ```
5. Il numero dopo l'ultimo punto è il timestamp
6. Dopo modifiche + refresh cache, dovrebbe cambiare

## Compatibilità

- ✅ Retrocompatibile: non rompe nulla
- ✅ Funziona con WordPress 6.5+
- ✅ Funziona con PHP 8.1+
- ✅ Compatibile con plugin di caching (WP Super Cache, W3TC, etc.)
- ✅ Compatibile con CDN (Cloudflare, etc.)

## Test Consigliati

1. **Test sviluppo:**
   - Attiva `WP_DEBUG`
   - Modifica un file CSS
   - Ricarica la pagina
   - Verifica che il CSS cambi

2. **Test produzione:**
   - Disattiva `WP_DEBUG`
   - Modifica un file CSS
   - Ricarica → CSS vecchio (corretto)
   - Esegui refresh cache
   - Ricarica → CSS nuovo ✅

3. **Test REST API:**
   - Chiama l'endpoint
   - Verifica la risposta JSON
   - Controlla che il timestamp sia cambiato

## Migrazione da Installazioni Esistenti

Per installazioni esistenti del plugin:

1. Aggiorna i file del plugin
2. Se in sviluppo: attiva `WP_DEBUG` nel `wp-config.php`
3. Se in produzione: esegui refresh cache dopo il primo aggiornamento

Non serve migrare database o modificare configurazioni.
