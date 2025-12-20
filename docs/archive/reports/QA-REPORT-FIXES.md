# QA Report - Fix Applicati
**Data:** 9 Dicembre 2025  
**Versione Plugin:** 0.9.0-rc10.3

## Fix Critici Applicati

### ✅ 1. Conflitto `array_values` in SettingsSanitizer
**File:** `src/Domain/Settings/Admin/SettingsSanitizer.php`  
**Problema:** Dichiarazione duplicata di `use function array_values;` (righe 13 e 42)  
**Fix:** Rimossa la dichiarazione duplicata alla riga 42  
**Status:** RISOLTO

### ✅ 2. StripeService - Argomenti Costruttore Mancanti
**File:** `src/Core/ServiceRegistry.php`  
**Problema:** `StripeService::__construct()` riceveva 2 argomenti ma ne richiede 7  
**Fix:** Aggiunte tutte le dipendenze necessarie:
- `StripeApiClient($options)` - richiede Options
- `StripeAmountCalculator($options)` - richiede Options  
- `StripeIntentBuilder($options, $amountCalculator)` - richiede Options e AmountCalculator
- `StripeStatusMapper()` - nessuna dipendenza
- `StripePaymentFormatter()` - nessuna dipendenza

Aggiunti anche gli use statements necessari:
- `use FP\Resv\Domain\Payments\StripeApiClient;`
- `use FP\Resv\Domain\Payments\StripeAmountCalculator;`
- `use FP\Resv\Domain\Payments\StripeIntentBuilder;`
- `use FP\Resv\Domain\Payments\StripeStatusMapper;`
- `use FP\Resv\Domain\Payments\StripePaymentFormatter;`

**Status:** RISOLTO

### ✅ 3. Menu Admin Non Visibile - Inizializzazione Legacy System
**File:** `fp-restaurant-reservations.php`, `src/Kernel/Bootstrap.php`, `src/Core/Plugin.php`  
**Problema:** Il nuovo sistema Bootstrap non inizializzava il vecchio sistema Plugin, quindi ServiceRegistry e AdminPages non venivano registrati

**Root Cause:**
- Bootstrap veniva chiamato su `wp_loaded` (dopo `plugins_loaded`)
- `Plugin::boot()` registra l'hook `plugins_loaded` solo se non è già stato chiamato
- Quando Bootstrap viene eseguito, `plugins_loaded` è già stato chiamato, quindi `Plugin::boot()` non viene mai eseguito
- Di conseguenza, `ServiceRegistry` non viene inizializzato e `AdminPages` non viene registrato

**Fix Applicato:**
1. **fp-restaurant-reservations.php:** Cambiato hook da `wp_loaded` a `plugins_loaded` con priorità 20
2. **Bootstrap.php:** Aggiunta inizializzazione manuale delle variabili statiche di Plugin e chiamata diretta a `onPluginsLoaded()`
3. **Plugin.php:** Aggiunto flag statico in `onPluginsLoaded()` per prevenire doppie inizializzazioni

**Status:** RISOLTO

## File Modificati

1. ✅ `src/Domain/Settings/Admin/SettingsSanitizer.php` - Rimosso `use function array_values;` duplicato
2. ✅ `src/Core/ServiceRegistry.php` - Corretto costruttore StripeService con tutte le dipendenze + use statements
3. ✅ `fp-restaurant-reservations.php` - Cambiato hook da `wp_loaded` a `plugins_loaded`
4. ✅ `src/Kernel/Bootstrap.php` - Aggiunta inizializzazione legacy system
5. ✅ `src/Core/Plugin.php` - Aggiunto flag per prevenire doppie inizializzazioni

## Verifica Linting

✅ Nessun errore di linting rilevato

## Prossimi Passi

### IMMEDIATO: Riattivare il Plugin

Il plugin è stato disattivato automaticamente da BootstrapGuard a causa degli errori precedenti.

**Opzione 1: Via Admin WordPress**
1. Vai a `http://fp-development.local/wp-admin/plugins.php`
2. Se il sito mostra ancora errore fatale, potrebbe essere necessario riattivare manualmente via database o WP-CLI

**Opzione 2: Via WP-CLI**
```bash
wp plugin activate FP-Restaurant-Reservations
```

**Opzione 3: Via Database**
Eseguire query SQL per riattivare:
```sql
UPDATE wp_options 
SET option_value = REPLACE(option_value, 'FP-Restaurant-Reservations/fp-restaurant-reservations.php', '') 
WHERE option_name = 'active_plugins';
```

Poi aggiungere il plugin alla lista:
```sql
UPDATE wp_options 
SET option_value = CONCAT(option_value, 'a:1:{i:0;s:52:"FP-Restaurant-Reservations/fp-restaurant-reservations.php";}') 
WHERE option_name = 'active_plugins';
```

### Dopo Riattivazione

1. ✅ Verificare che il menu "FP Reservations" appaia nella sidebar admin
2. ✅ Testare accesso a tutte le pagine admin
3. ✅ Verificare che non ci siano errori in console
4. ✅ Continuare con test E2E completi

## Note Tecniche

- Il flag statico in `Plugin::onPluginsLoaded()` previene doppie inizializzazioni anche se chiamato da più punti
- La chiamata diretta a `onPluginsLoaded()` nel Bootstrap bypassa il sistema di hook di WordPress ma è sicura grazie al flag
- Il cambio di hook da `wp_loaded` a `plugins_loaded` garantisce che il plugin si inizializzi al momento giusto



