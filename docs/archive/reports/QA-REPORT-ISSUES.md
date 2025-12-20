# QA Report - Problemi Identificati
**Data:** 9 Dicembre 2025  
**Versione Plugin:** 0.9.0-rc10.3

## Problema Critico: Menu Admin Non Visibile

### Descrizione
Il menu "FP Reservations" non appare nella sidebar admin di WordPress, anche se il plugin risulta attivo.

### Analisi Root Cause
Il plugin utilizza due sistemi di inizializzazione:
1. **Nuovo sistema Bootstrap** (`src/Kernel/Bootstrap.php`) - chiamato su `wp_loaded`
2. **Vecchio sistema Plugin** (`src/Core/Plugin.php`) - chiamato su `plugins_loaded`

Il problema è che:
- Il nuovo sistema Bootstrap viene chiamato su `wp_loaded` (dopo `plugins_loaded`)
- Il vecchio sistema Plugin::boot() registra l'hook `plugins_loaded` se non è già stato chiamato
- Ma quando Bootstrap viene eseguito, `plugins_loaded` è già stato chiamato, quindi Plugin::boot() non viene mai eseguito
- Di conseguenza, ServiceRegistry non viene inizializzato e AdminPages non viene registrato

### File Coinvolti
- `src/Kernel/Bootstrap.php` - Nuovo sistema di bootstrap
- `src/Core/Plugin.php` - Vecchio sistema (non viene chiamato)
- `src/Core/ServiceRegistry.php` - Registra AdminPages (non viene inizializzato)
- `src/Domain/Settings/AdminPages.php` - Classe che registra il menu admin

### Soluzione Proposta
1. **Opzione 1 (Raccomandata):** Far sì che Bootstrap chiami anche Plugin::onPluginsLoaded() per mantenere la compatibilità
2. **Opzione 2:** Migrare completamente AdminPages al nuovo sistema tramite AdminServiceProvider
3. **Opzione 3:** Far sì che Bootstrap chiami Plugin::boot() prima di `plugins_loaded`

### Note
- Tentativo di fix ha causato errore fatale (ripristinato)
- Necessario test approfondito prima di applicare fix definitivo
- Il problema potrebbe essere risolto anche disattivando e riattivando il plugin

## Altri Problemi Identificati

### 1. Errori Console Browser
- Errori 500 per admin-ajax.php (potrebbero non essere correlati al plugin)

### 2. Messaggio Dashboard
- "Struttura plugin non valida: cartella 'src' mancante" (potrebbe essere un falso positivo)

## Prossimi Passi

1. Verificare log errori PHP di WordPress
2. Testare con debug attivo
3. Implementare fix per menu admin
4. Continuare con test E2E una volta risolto il problema principale



