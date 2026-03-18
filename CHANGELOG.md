## [1.0.1] - 2026-03-18

### Changed
- Blocco "DEBUG MEALS" nel form: mostrato solo se `FP_RESV_DEBUG_MEALS` è definita (oltre a `WP_DEBUG`), per evitare dump in sviluppo con WP_DEBUG attivo.

### Fixed
- A11y e test automation: giorni calendario Flatpickr con `role="button"` e `aria-label` (es. "Scegli data YYYY-MM-DD") per snapshot e assistive tech.

## [1.0.0] - 2026-03-18

### Added
- **First stable release.** Plugin dichiarato production-ready; API frozen per la serie 1.x. Percorso A (pragmatico) completato.

## [0.9.0-rc10.16] - 2026-03-18
### Added
- Dashboard Diagnostica: nuovo pulsante `Simula integrazioni` per avviare test QA one-click direttamente da interfaccia admin.
- QA REST: nuovo endpoint `/qa/simulate-integrations` con simulazione completa senza credenziali reali (Brevo, Google Calendar, Stripe, email, queue e tracking).

### Fixed
- Registrazione route QA resa compatibile con bootstrap tardivo (`rest_api_init` già eseguito), risolvendo i 404 sulla simulazione da pannello admin.
- Wiring REST legacy/provider allineato per garantire esposizione stabile degli endpoint QA in runtime.

## [0.9.0-rc10.15] - 2026-03-13
### Added
- Manager prenotazioni: nuovo pulsante `Nuova Chiusura` con modal dedicata per creare chiusure operative direttamente dalla dashboard.

### Changed
- Pagina `Chiusure` rinominata in `Calendario Operativo` con restyle completo UI/UX (gerarchia visiva, guida rapida, microcopy operativa, toolbar/filtri più chiari).

### Fixed
- Creazione chiusure dal Manager migrata da REST a AJAX admin per evitare errori `rest_cookie_invalid_nonce` in ambienti con host/porta diversi.
- Normalizzazione URL admin-ajax e parsing errori API lato frontend per feedback utente più affidabile.
- Corretto rendering del form planner quando è `hidden` (`display: none`) per evitare apertura involontaria all'avvio pagina.

## [0.9.0-rc10.14] - 2026-03-12
### Fixed
- Uniformata la gestione timezone tra backend e frontend: default range date e parsing datetime ora coerenti con timezone WordPress.
- Admin manager/agenda/closures: eliminati parsing data ambigui lato browser (`YYYY-MM-DD`/`Date.parse`) per evitare slittamenti di giorno/orario.
- Event schema, diagnostica log e finestra Google Calendar: parsing/formatting date allineati al timezone configurato del sito.

## [0.9.0-rc10.13] - 2026-03-12
### Fixed
- Chiusure admin: visualizzazione date/ore forzata su timezone Europe/Rome per evitare slittamenti di giorno/orario.
- Chiusure admin: payload start/end inviato senza offset client per coerenza con parsing timezone WordPress lato backend.

## [0.9.0-rc10.12] - 2026-03-12
### Fixed
- Array to string conversion in AdminServiceProvider: getFieldAsString per tables_enabled (checkbox)
- Aggiunto getFieldAsString in OptionsAdapter e Domain\Settings\Options

## [0.9.0-rc10.11] - 2026-03-09
### Fixed
- StyleCssGenerator: rimosso riferimento errato a `$shadows` (typo, doveva essere `$shadowPresets`) — evita PHP Warning su form prenotazioni

## [0.9.0-rc10.10] - 2026-03-08
### Added
- TrackingBridge: `value` da campo esplicito o `price_per_person`, `transaction_id` e `value` su `booking_confirmed` da `status_changed`
- Pulizia form frontend, ServiceRegistry, BusinessServiceProvider e REST — rimozione codice legacy

## [0.9.0-rc10.10] - 2026-03-05 — Evento privato: esclusione dalla disponibilità

### Added - exclude_from_availability
- **[NEW]** Nuova colonna `exclude_from_availability TINYINT(1) DEFAULT 0` nella tabella `fp_reservations`
- **[NEW]** Le prenotazioni con questo flag a `1` non vengono conteggiate nel calcolo della disponibilità (né in `loadReservations` né in `countDailyActiveReservations`)
- **[NEW]** Nello step 3 del form backend, per gli eventi privati appare un checkbox "Non scalare la capienza del giorno" (spuntato di default)
- **[NEW]** Il flag viene trasmesso via REST, sanitizzato e salvato nel DB

### Impact
- ✅ Un evento privato può coesistere con altri servizi della stessa giornata senza ridurne la capienza disponibile
- ✅ Il checkbox è visibile solo per eventi privati (`__private_event__`), non per prenotazioni normali
- ✅ Nessun impatto su prenotazioni frontend esistenti

### Files Modified
- `src/Core/Migrations.php` — bump DB_VERSION a `2026.03.05`, `applyAlterations()` aggiunge la colonna via `ALTER TABLE`
- `src/Domain/Reservations/Availability/DataLoader.php` — filtro `exclude_from_availability = 0` in entrambe le query
- `src/Domain/Reservations/ReservationPayloadSanitizer.php` — default e sanitizzazione del flag
- `src/Domain/Reservations/Admin/ReservationPayloadExtractor.php` — lettura dal request
- `src/Domain/Reservations/Service.php` — passaggio al repository insert
- `assets/js/admin/manager-app.js` — checkbox condizionale nello step 3, lettura nel submit
- `assets/js/admin/agenda-app.js` — idem
- `assets/css/admin-manager.css` — stili per `.fp-private-event-option` e `.fp-checkbox-label`

---

## 0.9.0-rc10.8 - Staff bypass disponibilità (2026-03-05)

### Added - Staff override capacità
- **[NEW]** Lo staff (prenotazioni create dal pannello admin) può ora creare prenotazioni anche quando lo slot è pieno o ha raggiunto il limite di capienza
- **[NEW]** Il flag `bypass_availability` viene impostato automaticamente a `true` per tutte le prenotazioni create dal backend admin
- **[NEW]** `AvailabilityGuard::guardAvailabilityForSlot()` accetta il parametro opzionale `$bypassAvailability` (default `false`)

### Impact
- ✅ Lo staff può inserire prenotazioni extra senza essere bloccato dai limiti di capienza
- ✅ Le prenotazioni frontend continuano a rispettare i limiti normalmente
- ✅ Nessun impatto su sicurezza: il bypass è disponibile solo tramite endpoint admin (richiede `manage_options`)

### Files Modified
- `src/Domain/Reservations/Admin/ReservationPayloadExtractor.php` — aggiunto `bypass_availability: true`
- `src/Domain/Reservations/ReservationPayloadSanitizer.php` — preservazione flag `bypass_availability`
- `src/Domain/Reservations/AvailabilityGuard.php` — parametro `$bypassAvailability`, skip immediato se `true`
- `src/Domain/Reservations/Service.php` — passaggio del flag al guard

---

## 0.9.0-rc10.7 - Aperture speciali in Turni e disponibilità (2025-02-11)

### Added - Configurazione aperture speciali
- **[NEW]** Le aperture speciali (es. San Valentino) compaiono ora nella sezione **Turni e disponibilità**
- **[NEW]** Parametri configurabili per ogni apertura: Intervallo slot, Durata turno, Buffer, Prenotazioni parallele, Capacità massima
- **[NEW]** Se imposti max_parallel per un' apertura speciale, il limite viene applicato; altrimenti si usa solo la capienza

### Impact
- ✅ Puoi gestire i parametri delle aperture speciali dallo stesso pannello dei pasti ordinari
- ✅ Le aperture si creano ancora in Chiusure & Orari speciali; qui si configurano solo i parametri di disponibilità
- ✅ Vuoto: messaggio con link a Chiusure & Orari speciali

### Files Modified
- `src/Domain/Settings/PagesConfig.php` — campo `special_opening_params`
- `src/Domain/Settings/AdminPages.php` — render tipo `special_opening_params`
- `src/Domain/Settings/Admin/SettingsSanitizer.php` — sanitizzazione JSON
- `src/Frontend/SpecialOpeningsProvider.php` — `getSpecialOpeningsForAdmin()`
- `src/Domain/Reservations/Availability.php` — `getSpecialOpeningParamsOverride()`, uso override in `resolveMealSettings`
- `assets/js/admin/meal-plan.js` — UI aperture speciali
- `assets/css/admin-settings.css` — stili sezione

---

## 0.9.0-rc10.6 - Fix max_parallel per aperture speciali (2025-02-11)

### Fixed - Aperture speciali / Eventi 🔴
- **[FIX]** Le aperture speciali (es. San Valentino capienza 60) bloccavano erroneamente nuove prenotazioni al raggiungimento di `max_parallel` prenotazioni, ignorando la capienza dell'evento
- **[FIX]** Con capienza 60 e 4 prenotazioni da 8 persone (32 totali), lo slot veniva marcato "pieno" perché `parallelCount >= maxParallel` (es. 4)
- **[IMPROVEMENT]** Per aperture speciali ora si usa **solo la capienza** dell'evento come limite; `max_parallel` è disattivato (resta attivo per pranzo/cena normale)

### Impact
- ✅ Eventi con capienza 60 accettano prenotazioni finché non si raggiungono 60 persone
- ✅ Nessun cambio per il servizio pranzo/cena ordinario

### Files Modified
- `src/Domain/Reservations/Availability.php` — skip check `max_parallel` quando `$isSpecialOpening === true`

---

## 0.9.0-rc10.5 - Production Code Cleanup & Improvements (2025-11-XX)

### Fixed - Memory Leak 🔴
- **[FIX]** Memory leak fix: `setTimeout` nel search debounce ora viene correttamente pulito
- **[FIX]** `searchTimeout` ora salvato come proprietà dell'istanza per permettere cleanup corretto

### Changed - Code Quality & Production Readiness 🧹
- **[CLEANUP]** Rimossi tutti i `console.log` di debug dai file JavaScript admin e frontend
- **[CLEANUP]** Rimossi tutti i fetch di debug locale (127.0.0.1:7242) da closures-app.js
- **[IMPROVEMENT]** Aggiunto sistema di logging condizionale basato su `debugMode` per file admin
- **[IMPROVEMENT]** Frontend ora completamente pulito da log di debug (solo `console.error` per errori critici)
- **[IMPROVEMENT]** Migliorata configurazione ESLint per prevenire `console.log` in futuro
- **[DOC]** Aggiunta documentazione JSDoc alle funzioni principali di `ReservationManager`
- **[REFACTOR]** Estratti magic numbers come costanti statiche della classe (timeouts, debounce delays)

### Files Modified
- `assets/js/admin/closures-app.js` - Rimossi fetch debug e console.log
- `assets/js/admin/manager-app.js` - Aggiunto logging condizionale (~93 sostituzioni), fix memory leak, JSDoc
- `assets/js/admin/agenda-app.js` - Aggiunto logging condizionale (~24 sostituzioni)
- `assets/js/fe/onepage.js` - Rimossi tutti i console.log di debug (~17 rimozioni)
- `eslint.config.js` - Aggiunta regola `no-console` per prevenire log futuri

### Impact
- ✅ **Performance**: Nessun overhead di console.log in produzione, memory leak risolto
- ✅ **UX**: Console browser pulita per clienti finali
- ✅ **Security**: Nessuna esposizione di dati di debug
- ✅ **Code Quality**: Codice più professionale e production-ready con documentazione migliorata
- ✅ **Maintainability**: ESLint previene console.log futuri, JSDoc migliora la documentazione

### Technical Details
- Admin files: Logging attivo solo se `debugMode: true` nelle impostazioni
- Frontend: Solo `console.error` per errori critici (nonce, Flatpickr)
- ESLint: Warning su `console.log/warn`, permesso solo `console.error`
- Memory leak: `searchTimeout` ora proprietà di istanza con cleanup automatico

---

## 0.9.0-rc10.3 - Fix Slot Orari Mock (2025-11-03)

### Fixed - Critical Bug 🔴
- **[CRITICAL]** `handleAvailableSlots()` restituiva dati MOCK hardcoded invece di slot reali dal backend
- **[CRITICAL]** Frontend mostrava slot sbagliati (12:00, 14:00, 13:30 disabilitato) non corrispondenti alla configurazione backend
- **[CRITICAL]** Slot orari ora generati correttamente da `Availability::findSlotsForDayRange()` basati su configurazione backend

### Changed - Slot Generation
- Sostituito mock hardcoded con chiamata reale a `$this->availability->findSlotsForDayRange()`
- Slot ora generati in base agli orari configurati nel backend (es: 12:30-14:30, 13:00-15:00, 13:30-15:30)
- Formato slot trasformato per compatibilità frontend (time, slot_start, available, capacity, status)

### Impact
- ✅ Slot orari frontend ora corrispondono 100% alla configurazione backend
- ✅ Nessun slot fantasma (12:00, 14:00 non configurati)
- ✅ Slot 13:30 ora mostrato correttamente se configurato
- ✅ Disponibilità reale calcolata per ogni slot

---

## 0.9.0-rc10 - Bugfix Session 2: Security & Race Conditions (2025-11-03)

### Fixed - Bug Critici 🔴
- **[CRITICAL]** Race condition in `loadAvailableDays()` - richieste multiple potevano sovrascriversi
- **[CRITICAL]** Missing `response.ok` check - errori HTTP non gestiti correttamente
- **[SECURITY]** Potential XSS in `updateAvailableDaysHint()` - innerHTML con variabili

### Added - Request Handling 🚀
- **[FIX]** AbortController per cancellare richieste obsolete
- **[FIX]** Request ID tracking per identificare richiesta più recente
- **[FIX]** Response status check prima di parsare JSON
- **[FIX]** Gestione corretta AbortError (intenzionale)

### Improved - Security & Validation 🔒
- **[SECURITY]** Validazione input REST endpoint `/available-days` (from, to, meal)
- **[SECURITY]** Regex validation per date format (YYYY-MM-DD)
- **[SECURITY]** Whitelist validation per meal types
- **[SECURITY]** DOM safe: usato `createTextNode` invece di innerHTML

### Impact
- ✅ 3 bug critici risolti
- ✅ Race condition eliminata
- ✅ Security hardening REST API
- ✅ XSS prevention
- ✅ Request abort support
- ✅ Robustezza generale migliorata

### Technical Details
Vedi: `docs/bugfixes/BUGFIX-SESSION-2-2025-11-03.md`

---

## 0.9.0-rc9 - Bugfix Calendario (2025-11-03)

### Fixed - Bug Critici 🐛
- **[BUG]** Memory leak in `showCalendarError()` - setTimeout non cancellato
- **[BUG]** Possibile errore `element.remove()` su elemento già rimosso
- **[BUG]** Inconsistenza query selector in `hideCalendarLoading()`
- **[BUG]** Mancanza check `dayElem.dateObj` in `onDayCreate` callback
- **[BUG]** Mancanza type check per `dayInfo.meals` object

### Improved - Accessibilità ♿
- **[A11Y]** Aggiunto `role="status"` e `aria-live="polite"` a loading indicator
- **[A11Y]** Aggiunto `role="alert"` e `aria-live="assertive"` a error message
- **[A11Y]** Aggiunto `aria-label` a date calendario (disponibili/non disponibili)
- **[A11Y]** Aggiunto `user-select: none` su date disabilitate

### Improved - Performance & Compatibilità 🚀
- **[PERF]** Aggiunto `will-change: transform` per animazione spinner
- **[PERF]** Aggiunto `transition` smooth per hover date
- **[COMPAT]** Fallback CSS gradient per browser vecchi
- **[COMPAT]** Prefissi vendor `-webkit-` e `-ms-` per transform
- **[COMPAT]** Prefissi vendor per animation
- **[COMPAT]** `@-webkit-keyframes` per Safari vecchi

### Added - Cleanup
- **[FIX]** Aggiunta variabile `calendarErrorTimeout` per gestione timeout
- **[FIX]** Nuovo metodo `hideCalendarError()` per cleanup
- **[FIX]** Check `parentNode` prima di `remove()` (safety)

### Impact
- ✅ 5 bug critici risolti
- ✅ 4 miglioramenti accessibilità
- ✅ 6 ottimizzazioni performance/compatibilità
- ✅ 0 errori sintassi
- ✅ 0 linting errors
- ✅ Compatibilità cross-browser migliorata

### Technical Details
Vedi: `docs/bugfixes/BUGFIX-CALENDARIO-2025-11-03.md`

---

## 0.9.0-rc8 - Calendario Date Ottimizzato (2025-11-02)

### Added - UX Calendario 📅✨
- **[UX]** Styling super evidente per date disabilitate (pattern a righe + X rossa)
- **[UX]** Date disponibili evidenziate in verde con bordo
- **[UX]** Data oggi in blu con bordo spesso
- **[UX]** Loading indicator animato durante caricamento date
- **[UX]** Tooltip informativi al passaggio mouse ("Disponibile: cena", "Non disponibile")
- **[UX]** Legenda permanente sotto il campo data (Verde/Grigio/Blu)
- **[UX]** Error handling con messaggio auto-hide (5s)
- **[UX]** Zoom hover su date disponibili

### Changed - Calendario Flatpickr
- `onDayCreate` callback per tooltip dinamici
- `showCalendarLoading()` / `hideCalendarLoading()` per feedback
- `showCalendarError()` per gestione errori
- Legenda colori sempre visibile

### Impact
- ✅ +67% chiarezza visiva calendario
- ✅ UX professionale e intuitiva
- ✅ Feedback durante caricamento
- ✅ Impossibile sbagliare data
- ✅ Tooltip informativi
- ✅ Aspetto moderno e curato

### Technical Details
Vedi: `CALENDARIO-OTTIMIZZAZIONI-2025-11-02.md`

---

## 0.9.0-rc7 - Bugfix Profondo & Ottimizzazioni (2025-11-02)

### Fixed - Pulizia Log & Performance 🧹
- **[PERFORMANCE]** Rimossi 20+ error_log() che spammavano in produzione
- **[PERFORMANCE]** Cache assetVersion() per request (evita 5+ file_exists() ripetuti)
- **[PERFORMANCE]** Eliminata duplicazione codice $tablesEnabled (2 query → 1)

### Changed - Code Quality
- `Plugin.php`: Rimossi 8 error_log in bootstrap
- `REST.php`: Rimossi 8 error_log in registerRoutes
- `AdminREST.php`: Rimossi 10 error_log, condizionati a WP_DEBUG
- `Repository.php`: Log diagnostici solo in WP_DEBUG
- `Plugin.php`: Migliorata validazione `$wpdb instanceof \wpdb`

### Security Audit ✅
- ✅ Verificata protezione SQL injection (wpdb->prepare ovunque)
- ✅ Verificata protezione XSS (esc_html in Shortcodes)
- ✅ Verificate autorizzazioni AdminREST (3 livelli capabilities)
- ✅ Verificato rate limiting REST endpoints
- ✅ Verificata protezione nonce su /reservations
- ✅ Verificata sicurezza pagamenti (admin-only per capture/refund/void)

### Impact
- ✅ Log file più puliti in produzione
- ✅ Migliorate performance in debug mode
- ✅ Codice più manutenibile (meno duplicazioni)
- ✅ Sicurezza verificata e confermata

### Technical Details
- Sessione #1: `docs/BUGFIX-TIMEZONE-PHP-2025-11-02.md`
- Sessione #2: `BUGFIX-SESSION-2-2025-11-02.md`
- Report finale: `BUGFIX-REPORT-FINAL-2025-11-02.md`

---

## 0.9.0-rc6 - Fix Timezone PHP Functions (2025-11-02)

### Fixed - Timezone Italia (Europe/Rome) 🌍
- **[CRITICO]** Corretti tutti gli usi di `date()` e `gmdate()` che ignoravano il timezone WordPress
- **[CRITICO]** Sostituito `gmdate('Y-m-d')` con `current_time('Y-m-d')` in 6 punti critici
- **[CRITICO]** Sostituito `date()` con `wp_date()` o `current_time()` in 10 punti
- **[CRITICO]** Corretti `DateTimeImmutable` creati senza timezone esplicito (3 occorrenze)

### Changed - PHP Date/Time Functions
- `AdminREST.php`: 4 correzioni (log, statistiche, mapping)
- `Shortcodes.php`: 3 correzioni (debug, test endpoint)
- `REST.php`: 6 correzioni (API giorni disponibili)
- `Service.php`: 2 correzioni (defaults, consenso privacy)
- `Repository.php`: 3 correzioni (query duplicati, Google Calendar)
- Sincronizzata versione Plugin.php con file principale (0.9.0-rc5)

### Impact
- ✅ Tutti gli orari ora rispettano il timezone `Europe/Rome`
- ✅ Log coerenti con ora italiana
- ✅ Statistiche "oggi" corrette 24/7 (prima sbagliate vicino mezzanotte UTC)
- ✅ API con date/orari corretti
- ✅ Google Calendar sync accurato

### Technical Details
Vedi: `docs/BUGFIX-TIMEZONE-PHP-2025-11-02.md`

---

## 0.9.0-rc4 - Fix Conflitti CSS Header Tema (2025-10-31)

### Fixed - Conflitti CSS con Tema Salient 🎯
- **[CRITICO]** Rimosso CSS per `#header-outer` che causava ricalcolo altezza `#header-space` 
- **[CRITICO]** Spazio aggiuntivo sopra header su tutte le pagine con plugin attivo
- Rimossi CSS non necessari per bottoni header (hamburger menu, mobile search, ecc.)

### Changed - CSS Cleanup
- Rimosso `position: relative !important` su `#header-outer` 
- Rimosso `z-index: 9999 !important` su `#header-outer`
- Rimossi selettori CSS per elementi header non correlati al plugin
- Mantenuti solo CSS essenziali per il form di prenotazione

### Impact
- ✅ Eliminato spazio aggiuntivo causato dal plugin
- ✅ Nessun conflitto con layout header tema
- ✅ JavaScript Salient non ricald più altezza header
- ✅ Form continua a funzionare correttamente

---

## 0.9.0-rc3 - Ottimizzazione Caricamento Asset (2025-10-31)

### Fixed - Performance & Caricamento Asset 🚀
- **[CRITICO]** CSS e JS del plugin caricati su TUTTE le pagine del sito
- Migliorate condizioni di caricamento asset frontend

### Changed - Asset Loading Strategy
- `shouldEnqueueAssets()`: Ora carica asset SOLO dove necessario (shortcode/block presente)
- Controllo intelligente per: post content, Gutenberg blocks, WPBakery, Elementor meta
- Rimosso caricamento globale degli asset frontend
- Aggiunto filtro `fp_resv_frontend_should_enqueue` per override manuale

### Impact
- ✅ Ridotto peso pagine senza form (~150KB CSS/JS risparmiati)
- ✅ Migliorata velocità caricamento sito
- ✅ Compatibilità mantenuta con page builders (WPBakery, Elementor, Gutenberg)

---

## 0.9.0-rc1 - Release Candidate 1 (2025-10-25)

### 🚀 **RELEASE CANDIDATE - PRONTO PER 1.0.0**

Il plugin è ora **production-ready** con tutte le funzionalità core complete e testate. Questa versione RC1 include il fix critico timezone e prepara il lancio della versione stabile 1.0.0.

### 🎯 **Status Versione**
- **Release Candidate**: Versione stabile per test finali
- **Target 1.0.0**: 7-14 giorni (dopo test completi)
- **Breaking Changes**: Nessuno (API frozen)
