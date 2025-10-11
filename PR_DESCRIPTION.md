# feat: Aggiornamento completo sistema prenotazioni e tracking

## 🎯 Riepilogo

Questo PR porta su main **150+ commit** con funzionalità completamente nuove, refactoring architetturale importante, e numerosi bug fix critici.

## ✨ Funzionalità Completamente Nuove

### 🔐 Sistema Ruoli e Permessi Custom
**NUOVO** - Non esisteva su main
- `src/Core/Roles.php` (114 righe) - Sistema completo di gestione ruoli WordPress
- Permessi granulari per gestione prenotazioni
- Tool di riparazione capabilities (`tools/fix-admin-capabilities.php`)
- Test unitari inclusi

### 📱 Gestione Telefoni Internazionali
**NUOVO** - Non esisteva su main
- `src/Frontend/PhonePrefixes.php` (1.575 righe) - Database completo prefissi mondiali
- `src/Core/PhoneHelper.php` (194 righe) - Helper per validazione e formattazione
- Auto-detect lingua da prefisso telefonico
- Mappatura paese → lingua per WPML

### 🤖 Automazione e Deployment
**NUOVO** - Non esisteva su main
- `src/Core/AutoCacheBuster.php` (60 righe) - Cache busting automatico per asset
- `.github/workflows/deploy-on-merge.yml` - Deploy automatico su merge a main
- `scripts/verify-zip-contents.sh` - Verifica integrità build ZIP
- Tool refresh cache (`tools/force-cache-refresh.sh`)

### 🗂️ Stati Prenotazioni
**NUOVO** - Non esisteva su main
- `src/Domain/Reservations/ReservationStatuses.php` (107 righe)
- Centralizzazione logica stati (pending, confirmed, cancelled, etc.)
- Metodi helper per transizioni di stato

### 🧹 Gestione Disinstallazione
**NUOVO** - Non esisteva su main
- `uninstall.php` (98 righe)
- Pulizia completa database e opzioni
- Opzione per mantenere dati utente

## 🔄 Miglioramenti Sostanziali (già esistenti)

### 📊 Tracking Potenziato
**Già presente su main, ma esteso significativamente:**
- `src/Domain/Tracking/GA4.php`: +106 righe di funzionalità
- `src/Domain/Tracking/Meta.php`: +140 righe di funzionalità  
- `src/Domain/Tracking/Manager.php`: +181 righe (+1 riga rimossa)
- Tracking server-side migliorato
- Eventi personalizzati per conversioni

### 🎫 Agenda Backend - Ristrutturazione Completa
**Refactoring massiccio:**
- `src/Domain/Reservations/AdminREST.php`: +630/-197 righe
- `assets/js/admin/agenda-app.js`: +949/-292 righe
- `assets/css/admin-agenda.css`: +453/-8 righe
- Stile "TheFork" per gestione drag-and-drop
- API più robuste e performanti
- UI completamente ridisegnata

### 🎨 Sistema Prenotazioni Frontend
**Miglioramenti importanti:**
- `src/Domain/Reservations/Service.php`: +312/-140 righe
- `assets/css/form.css`: +793/-197 righe
- `assets/js/fe/onepage.js`: +288/-231 righe
- Nuovi moduli ES6: `MealManager.js` (235 righe)
- Componenti modulari (form-navigation, form-validation, form-state)
- Utility moderne (dom, net, validation, a11y, tracking)

## 🏗️ Refactoring Architetturale

### Modularizzazione Settings
**Miglioramento manutenibilità:**
- `src/Domain/Settings/AdminPages.php`: **-976 righe** (ridotto)
- `src/Domain/Settings/PagesConfig.php`: **+1.085 righe** (nuovo, estratto)
- `src/Domain/Settings/Style.php`: **-1.062 righe** (ridotto)
- `src/Domain/Settings/StyleCss.php`: **+828 righe** (nuovo, estratto)

Codice separato logicamente per migliore manutenibilità.

### Semplificazione Form Context
- `src/Frontend/FormContext.php`: **-1.768 righe** rimosso
- Logica distribuita in componenti specializzati
- Codice più testabile e manutenibile

### Nuovi Moduli Admin
**NUOVI file organizzativi:**
- `assets/js/admin/utils.js` (224 righe)
- `assets/js/admin/meal-plan-config.js` (98 righe)
- `assets/js/admin/meal-plan-utils.js` (164 righe)

## 🐛 Bug Fix Critici

### Prenotazioni e Disponibilità
- ✅ Fix prenotazioni simultanee con sistema di idempotenza
- ✅ Fix "completamente prenotato" con falsi positivi
- ✅ Fix calcolo disponibilità reale vs mostrata
- ✅ Fix passaggio parametro `meal` alle API di disponibilità
- ✅ Fix capacità zero quando tutti i tavoli sono disabilitati
- ✅ Fix validazione date con solo giorni effettivamente disponibili

### Form Multi-Step e UX
- ✅ Fix validazione nonce/cookie su richieste API
- ✅ Fix navigazione form con scroll automatico intelligente
- ✅ Fix calendario con filtri specifici per pasto (pranzo/cena)
- ✅ Fix prevenzione submit senza data selezionata
- ✅ Fix layout responsive su mobile
- ✅ Fix accessibilità (ARIA attributes, focus management)

### Notifiche e Automazioni
- ✅ Fix notifiche duplicate a staff
- ✅ Fix deduplicazione email staff
- ✅ Fix timezone italiano in notifiche
- ✅ Fix eventi Brevo automation
- ✅ Fix header Brevo API per tracking eventi

### Admin e Gestione
- ✅ Fix accesso menu amministrazione
- ✅ Fix visibilità submenu settings
- ✅ Fix caricamento agenda con indicatore loading
- ✅ Fix aggiornamento plugin da GitHub

## 🧪 Testing

**Nuovi test aggiunti:**
- `tests/Integration/Reservations/ServiceTest.php` (261 righe)
- `tests/Unit/Core/RolesTest.php` (61 righe)
- `tests/bootstrap.php` (61 righe)
- Test E2E agenda drag-and-drop migliorati

## 📦 Dipendenze e Configurazione

- Aggiornato `composer.json` per cross-platform compatibility
- Plugin Update Checker integrato e configurato
- Build workflow ottimizzato
- Git attributes per handling files binari

## 📊 Statistiche Finali

- **135 file modificati**
- **+13.652 righe** di codice aggiunte
- **-12.190 righe** di codice rimosse (refactoring)
- **Netto: +1.462 righe** di codice funzionale pulito
- **150+ commit** unificati e testati

### Breakdown per categoria:
- **Nuove funzionalità**: ~4.000 righe
- **Refactoring/Modularizzazione**: ~3.500 righe spostate
- **Bug fix e miglioramenti**: ~6.000 righe
- **Test**: ~400 righe

## ✅ Test e Compatibilità

- ✅ Testato su WordPress 6.x
- ✅ PHP 7.4+ compatibile
- ✅ Cross-browser tested (Chrome, Firefox, Safari, Edge)
- ✅ Mobile responsive verificato
- ✅ WPML compatible
- ✅ Production ready

## 🧹 Nota sulla Pulizia

Questa PR include anche commit di pulizia che rimuove:
- 57 file .md di documentazione temporanea/debug
- 8 script di debug temporanei
- Documentazione interna non necessaria in produzione

Solo codice production-ready è incluso.
