# Diagnosi: Fallimento Aggiornamento Automatico Plugin

**Data**: 2025-10-11  
**Branch**: `cursor/investigate-plugin-update-failure-19f5`  
**Versione Plugin**: 0.1.10

## 🔍 Problema Riportato

Il plugin **FP Restaurant Reservations** non si aggiornava automaticamente nonostante venissero pubblicate nuove release su GitHub.

## 🕵️ Analisi del Sistema

### Sistema di Aggiornamento Automatico

Il plugin utilizza la libreria **Plugin Update Checker** di Yahnis Elsts per abilitare gli aggiornamenti automatici da GitHub, senza bisogno di passare attraverso il repository ufficiale WordPress.org.

Il sistema funziona così:
1. Il plugin controlla periodicamente se ci sono nuove release su GitHub
2. Se trova una versione più recente, mostra la notifica nell'admin di WordPress
3. L'utente può aggiornare il plugin direttamente dalla dashboard

### Workflow di Deployment

Il repository ha un workflow GitHub Actions (`deploy-on-merge.yml`) che:
1. Viene attivato ad ogni merge su `main`
2. Estrae la versione corrente dal file principale del plugin
3. Crea automaticamente una GitHub Release con il file ZIP del plugin
4. Include tutte le dipendenze necessarie (vendor, assets compilati, ecc.)

## ⚠️ Causa del Problema

**URL Repository Errato nel Plugin Update Checker**

Nel file `fp-restaurant-reservations.php`, il sistema di aggiornamento puntava a un URL GitHub **SBAGLIATO**:

```php
// ❌ URL ERRATO
$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/francescopasseri/fp-restaurant-reservations/',
    __FILE__,
    'fp-restaurant-reservations'
);
```

### Differenze Critiche

| Elemento | URL Errato | URL Corretto |
|----------|------------|--------------|
| **Username GitHub** | `francescopasseri` | `franpass87` |
| **Nome Repository** | `fp-restaurant-reservations` (minuscolo) | `FP-Restaurant-Reservations` (CamelCase) |
| **URL Completo** | `github.com/francescopasseri/fp-restaurant-reservations/` | `github.com/franpass87/FP-Restaurant-Reservations/` |

Il repository reale è:
```
https://github.com/franpass87/FP-Restaurant-Reservations
```

Quindi il Plugin Update Checker cercava le release su un repository **inesistente o sbagliato**, e per questo non trovava mai aggiornamenti disponibili.

## ✅ Soluzione Applicata

Ho corretto l'URL del repository in **3 file**:

### 1. File Principale del Plugin
**File**: `fp-restaurant-reservations.php`  
**Linea**: 64

```php
// ✅ URL CORRETTO
$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/franpass87/FP-Restaurant-Reservations/',
    __FILE__,
    'fp-restaurant-reservations'
);
```

### 2. Documentazione Auto-Deploy
**File**: `docs/GITHUB-AUTO-DEPLOY.md`  
**Linea**: 100

Aggiornato l'esempio di codice con l'URL corretto.

### 3. Package Name Composer
**File**: `composer.json`  
**Linea**: 2

```json
{
    "name": "franpass87/fp-restaurant-reservations",
    ...
}
```

## 📝 Note Importanti

### Dipendenze Mancanti in Sviluppo

Durante l'analisi ho notato che la directory `vendor/` non è presente nel repository (correttamente, perché è in `.gitignore`). Questo significa che:

1. ✅ In **sviluppo locale**: bisogna eseguire `composer install` per installare le dipendenze
2. ✅ In **produzione/release**: il workflow GitHub crea automaticamente il package con tutte le dipendenze incluse
3. ✅ Gli **utenti finali**: ricevono il plugin già con tutto il necessario tramite il file ZIP della release

### Verifica del Fix

Per verificare che il fix funzioni correttamente:

1. **Dopo il merge su main**, il workflow creerà una nuova release
2. **Su WordPress**, vai in **Dashboard** → **Plugin** → controlla gli aggiornamenti
3. Dopo qualche minuto (cache WordPress), dovresti vedere l'aggiornamento disponibile
4. Il plugin mostrerà "È disponibile una nuova versione"

### Test Manuale (Opzionale)

Per testare immediatamente senza attendere la cache di WordPress:

```php
// Aggiungi temporaneamente in wp-config.php
define('WP_DEBUG', true);
delete_transient('update_plugins'); // Pulisce cache aggiornamenti
```

Poi visita: **Dashboard** → **Aggiornamenti**

## 🎯 Impatto del Fix

✅ **Risoluzione Immediata**: Il Plugin Update Checker ora punta al repository corretto  
✅ **Retrocompatibilità**: Nessun impatto sugli utenti esistenti  
✅ **Aggiornamenti Futuri**: Gli utenti riceveranno automaticamente gli aggiornamenti  
✅ **Documentazione Aggiornata**: Tutti i riferimenti sono stati corretti  

## 📚 File Modificati

- ✅ `fp-restaurant-reservations.php` - URL Plugin Update Checker corretto
- ✅ `composer.json` - Package name corretto
- ✅ `docs/GITHUB-AUTO-DEPLOY.md` - Documentazione aggiornata

## 🔄 Prossimi Passi

1. ✅ Merge della branch `cursor/investigate-plugin-update-failure-19f5` su `main`
2. ⏳ Il workflow creerà automaticamente una nuova release
3. ⏳ Gli utenti riceveranno la notifica di aggiornamento su WordPress
4. ⏳ Verifica che il meccanismo funzioni correttamente

## 📖 Riferimenti

- [Plugin Update Checker Documentation](https://github.com/YahnisElsts/plugin-update-checker)
- [Workflow Deploy on Merge](.github/workflows/deploy-on-merge.yml)
- [Documentazione Auto-Deploy](docs/GITHUB-AUTO-DEPLOY.md)
