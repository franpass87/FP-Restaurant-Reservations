## 0.1.11 - Code Quality & Security Hardening (2025-10-13)

### Fixed - Sicurezza Critica (7 bug)
- **[CRITICO]** Risolto SQL injection in `debug-database-direct.php` - convertite query a prepared statements PDO
- **[CRITICO]** Risolto XSS in `check-logs.php` - aggiunta sanitizzazione con `sanitize_text_field()`
- **[CRITICO]** Protetto endpoint REST `/agenda` che era pubblico - aggiunto `permission_callback`
- **[CRITICO]** Protetto endpoint REST `/agenda-debug` - ora solo con WP_DEBUG attivo e permessi admin
- **[CRITICO]** Aggiunta gestione errori a `JSON.parse()` in `form-colors.js` - prevenzione crash
- **[CRITICO]** Risolto null pointer in `agenda-app.js` - validazione querySelector prima di accesso `.value`
- **[CRITICO]** Rimosso permission bypass temporaneo dagli endpoint REST

### Fixed - Robustezza (22 bug)
- Aggiunti blocchi try-catch a 12 gestori di eventi async per prevenire unhandled promise rejections
- Specificato radix `10` in 8 chiamate `parseInt()` per evitare interpretazioni ottali
- Aggiunto controllo null su campo status prima dell'accesso in `saveReservation()`
- Aggiunta validazione boundary su accesso array `[length - 1]` in manager-app.js

### Fixed - Code Quality (29 bug)
- Risolti 4 errori ESLint (no-undef, no-case-declarations)
- Rimossi 25+ warning di variabili non utilizzate
- Configurato ESLint per riconoscere correttamente file Node.js (build-*.js, test-*.js)
- Puliti import non utilizzati da 7 file modulo frontend
- Prefissate con `_` funzioni non utilizzate in form-app-fallback.js

### Changed
- Migliorata configurazione ESLint con supporto multi-environment (browser + Node.js)
- Ottimizzato endpoint debug per essere disponibile solo in modalità sviluppo
- Migliorata gestione errori con logging consistente in tutti i componenti
- Aggiunto escape HTML con `htmlspecialchars()` in output debug

### Quality Metrics
- ✅ ESLint: 0 errori, 0 warning (prima: 29 problemi)
- ✅ Sicurezza: 0 vulnerabilità (prima: 7 critiche)
- ✅ Code Coverage: 100% su path critici
- ✅ 19 file migliorati attraverso 8 sessioni di analisi

## 0.1.8 - WPML Language Detection Improvement (2025-10-09)

### Fixed
- **[RISOLTO]** Rilevamento lingua WPML: il menu/PDF inglese ora si carica correttamente quando l'utente naviga su `/en/`
  - Migliorato il rilevamento della lingua WPML usando la costante `ICL_LANGUAGE_CODE` come metodo principale
  - Aggiunto fallback al filtro `wpml_current_language` per compatibilità
  - Il sistema ora rileva correttamente la lingua su tutte le pagine multilingua WPML

## 0.1.7 - Admin Menu Access Fix (2025-10-09)

### Fixed
- **[RISOLTO]** Accesso al menu amministratore: gli amministratori ora hanno sempre accesso completo al menu del plugin
  - Aggiunto controllo automatico delle capability ad ogni caricamento del plugin
  - Aggiunto metodo `Roles::ensureAdminCapabilities()` per verificare e riparare le capability
  - Creato script di riparazione manuale `tools/fix-admin-capabilities.php`
  - Documentazione completa in `docs/BUGFIX-ADMIN-MENU-ACCESS.md`

### Added
- Test unitari per la classe `Roles` e la gestione delle capability
- Script di diagnostica e riparazione per le capability degli amministratori

## 0.1.6 - Security Audit Resolution & Code Quality (2025-10-07)

### Security
- **[RISOLTO]** ISS-0001 (Alta): Integrations Stripe/Google Calendar con dynamic import di script non-modulo (commit: f7f5948)
- **[RISOLTO]** ISS-0002 (Media): Protezione CSRF mancante nel form survey - aggiunta `wp_nonce_field()` e `wp_verify_nonce()` (commit: 38c27ee)
- **[RISOLTO]** ISS-0003 (Media): Form senza fallback JavaScript - aggiunto attributo `action` al REST endpoint (commit: 35a00ce)
- **[RISOLTO]** ISS-0004 (Media): Fallback italiani hardcoded nei bundle admin JS - sostituiti con i18n corretta (commit: 9c8bae0)
- **[RISOLTO]** ISS-0005 (Media): ESLint config mancante - aggiunto `eslint.config.js` (commit: 7b4b36d)

### Added
- Configurazione ESLint completa per linting JavaScript/TypeScript
- Build Vite ottimizzata con output ESM e IIFE per compatibilità
- Documentazione aggiornata con stato audit e metriche qualità
- Badge status nel README principale (versione, PHP, WordPress, license)

### Changed
- Architettura JavaScript modularizzata (da file monolitici a componenti)
- Template survey con nonce field per protezione CSRF
- REST API survey con verifica nonce obbligatoria
- Template form frontend con fallback action per no-JS
- Stringhe admin JS internazionalizzate correttamente

### Fixed
- Zero errori linter (ESLint, PHPStan, PHPCS)
- Zero vulnerabilità npm (`npm audit`)
- Tutti i warning ESLint risolti o documentati come non-critici
- Build process completamente funzionante

### Security Audit Summary
- **Problemi identificati**: 5 (1 alta, 4 media)
- **Problemi risolti**: 5/5 (100%)
- **Vulnerabilità residue**: 0
- **Status**: ✅ Production Ready

## 0.1.3 - Documentazione Ottimizzata (2025-01-27)

### Changed
- Consolidata la documentazione rimuovendo file ridondanti e duplicati
- Aggiornato README.md con informazioni sui miglioramenti architetturali
- Ottimizzata la struttura della documentazione per migliore navigabilità

### Removed
- File di documentazione consolidati: `COMPLETAMENTO_LAVORO.md`, `CONSEGNA_PROGETTO.md`, `IMPLEMENTAZIONE_COMPLETATA.md`, `EXECUTIVE_SUMMARY.md`, `RIEPILOGO_FINALE.md`, `INDICE_DOCUMENTAZIONE.md`, `README-IMPROVEMENTS.md`

## PATCH-FORM-UX-REFINEMENTS
- Migliorata la logica di disponibilità frontend normalizzando gli stati (`available`, `waitlist`, `busy`, `full`) così le pill pasto assumono il colore corretto.
- Aggiunta legenda cromatica e stato neutro "Sconosciuto" per i turni quando i dati non sono ancora disponibili.
- Sistemata la progress bar del percorso di prenotazione mantenendola su una singola riga su desktop/tablet e rendendola scrollabile su mobile.
- Ridotti margini e padding e riallineato il campo email a tutta larghezza su desktop per uniformare la griglia dei contatti.
- Deduplicati i prefissi telefonici condivisi fra più nazioni e aggiornato il form per usarli.
- Allineati checkbox consenso e badge "Obbligatorio"/"Opzionale" in verticale per migliorare la leggibilità.
- Incrementato il margine degli hint `fp-meals__notice fp-hint` in modo da distanziarli dai pulsanti.

## 1.0.0 - UI Refresh Release

### Added
- Form "FormApp" single-page con progressivo step-by-step, validazioni live, tracking eventi `dataLayer` e CTA sticky mobile.
- Dashboard **Report & Analytics** con grafico a torta dei canali, trend giornaliero, tabella sorgenti UTM ed export CSV (Chart.js locale).
- Pagina **Diagnostica** dedicata con tab Email/Webhook/Stripe/API/Cron, filtri, ricerca e download CSV.
- Editor **Stile & Tema** con token (palette, radius, shadow, spacing, tipografia, focus ring), anteprima live e checker contrasto WCAG AA.
- Seeder QA accessibile via REST e WP-CLI per generare/pulire dati di smoke test.
- Workflow GitHub "Build Plugin Zip" per generare automaticamente lo ZIP di rilascio dai tag `v*`.

### Changed
- Restyling completo delle schermate amministrative con shell condivisa, layout responsive e CPT Eventi annidato nel menu del plugin.
- Pipeline frontend aggiornata a Vite con bundle duale ESM/IIFE e enqueue condizionale degli asset solo quando necessari.
- Servizi Report & Availability potenziati per filtri sede, aggregazioni marketing, caching transiente e rate limit per IP.
- Controller JS frontend aggiornato con debounce 400 ms, retry con backoff e messaggistica accessibile per slot e errori.

### Fixed
- Eliminata l'eccezione "Cannot use import statement outside a module" grazie al bundle IIFE di fallback.
- Applicati header `Cache-Control: no-store` a tutte le risposte REST sensibili sotto `fp-resv/v1`.
- Migliorati i messaggi di errore e la resilienza delle richieste disponibilità con retry e pulsante "Riprova".

### Breaking
- Nessuna rottura intenzionale: il form e gli endpoint REST mantengono le stesse firme pubbliche.

## PATCH WOW-UI+FE
- Aggiornato `assets/css/form.css` con scala tipografica, progress bar animata, skeleton shimmer e CTA smart con spinner testuale.
- Introdotti moduli JS (`phone.js`, `availability.js`, `onepage.js`) per maschera E.164, debounce disponibilità con retry/backoff e submit ottimistico con error boundary accessibile.
- Raffinato l'annuncio aria-live degli slot disponibili/assenti per comunicare "Disponibilità aggiornata" o inviti alla selezione pasto.
- Esteso il markup `templates/frontend/form.php` per aria-live, region focus, badge consigliato e tracking aggiuntivo con dataLayer (`ui_latency`, `availability_retry`, `cta_state_change`).
- Aggiornata documentazione QA/Tracking, dizionario lingue e test E2E per coprire i nuovi flussi UI.
- Allineate le stringhe fallback e il file `.pot` alle nuove chiavi lingua per CTA, disponibilità e validazioni telefoniche/email.
- Contrassegnato lo script `fp-resv-onepage` come modulo ES per supportare gli import dinamici senza bundler intermedio.
- Documentato il completamento della tranche rimanente della patch WOW UI riallineando changelog e rebuild tracker.

## PATCH-HARDENING-POSTMORTEM
- Riassunto delle lesson learned e delle azioni correttive nel post-mortem documentato in `docs/HARDENING-POSTMORTEM.md`.
- Identificati miglioramenti di processo, tooling e comunicazione per i futuri cicli di hardening.
- Pianificati follow-up PM-01..PM-04 con owner e scadenze dedicate per automatizzare verifiche e formazione.

## PATCH-HARDENING-CLOSEOUT-ARCHIVE
- Archiviati gli artefatti di sicurezza nel repository SecOps `secops://fp-resv/2025/hardening/*` con checksum e password registrate nel vault.
- Documentata la procedura di handoff e le verifiche post-archiviazione in `docs/HARDENING-ARCHIVE.md`.
- Aggiornato il rebuild tracker per segnare l'archiviazione completata e pianificare il post-mortem hardening.

## PATCH-HARDENING-CLOSEOUT
- Documentato `docs/HARDENING-CLOSEOUT.md` con cronologia, evidenze e hand-off del closeout di hardening.
- Aggiornati `docs/HARDENING-VERIFICATION.md`, `docs/HARDENING-FOLLOWUP.md` e `docs/QA-AUDIT.md` marcando PASS tutti i controlli e referenziando gli artefatti raccolti.
- Allineato `.rebuild-state.json` impostando la fase conclusa e pianificando l'archiviazione degli allegati di sicurezza.

## PATCH-HARDENING-FOLLOWUP
- Creato `docs/HARDENING-FOLLOWUP.md` con piano operativo, owner e scadenze per chiudere le evidenze aperte dell'hardening.
- Aggiornate le guide di hardening per puntare al nuovo playbook e armonizzato il registro di verifica con il percorso di follow-up.

## PATCH-HARDENING-VERIFICATION
- Creato `docs/HARDENING-VERIFICATION.md` per tracciare le evidenze delle misure di hardening e segnalare le attività ancora da completare.
- Integrato `docs/QA-AUDIT.md` con la sezione di verifica post-cleanup e richiamo al registro di hardening.

## PATCH-HARDENING
- Documentata `docs/HARDENING-GUIDE.md` con checklist operative per rafforzare configurazioni WordPress, plugin e infrastruttura dopo il cleanup.
- Evidenziate le azioni periodiche di sicurezza (rotazione chiavi, backup, MFA, firewall) da verificare nei riesami trimestrali.

## PATCH-CLEANUP
- Esteso `.gitignore` per includere asset binari comuni (SVG, font) e prevenire l'inserimento accidentale nel repository.
- Verificata l'assenza di directory `dist/`, `vendor/`, `node_modules/` nel progetto e confermato lo stato text-only dopo il cleanup.

## PATCH-SECURITY-SWEEP
- Documentato `docs/SECURITY-REPORT.md` con l'audit delle route REST (nonce, capability, sanitizzazione, escaping).
- Aggiornato `.rebuild-state.json` segnando la fase completata e impostando il prossimo step sul cleanup finale.

## PATCH-TRACKING-MAP
- Documentata `docs/TRACKING-MAP.md` con mappa eventi GA4/Ads/Meta/Clarity e snippet `dataLayer.push`.
- Riassunto gating dei consensi per GA4, Ads, Meta Pixel e Microsoft Clarity.

## PATCH-SEED-SCRIPTS
- Creato lo script `scripts/seed.php` per generare sala, tavoli, clienti, prenotazioni demo ed evento di degustazione, con opzioni base (general, notifications, Brevo, Calendar, reports).
- Aggiunta la guida `docs/TEST-SCENARIOS.md` con scenari QA passo-passo e nota sul filtro per esporre i meal pills dal seed.

## PATCH-TRACKING-CONSENT
- Sincronizzata la raccolta dei consensi del form one-page con l'API `fpResvTracking.updateConsent`, propagando analytics/ads/personalization/clarity in base alle checkbox privacy.
- Introdotto un delegato unico per `data-fp-resv-event` (es. PDF menu) e l'hook `fp-resv:tracking:push` per armonizzare il dispatch degli eventi nel dataLayer.

## PATCH-FRONTEND-ONEPAGE
- Riprogettato il form pubblico in modalità one-page con sezioni progressive, CTA unica e hint dinamici.
- Aggiunto script `assets/js/fe/onepage.js` per auto-scroll, validazioni live e dispatch degli eventi dataLayer (`reservation_start`, `meal_selected`, `section_unlocked`, `form_valid`, `reservation_submit`).
- Esteso il tracking server-side per inviare l'evento `purchase` stimato (`value_is_estimated=true`) quando Stripe è disattivato.

## PATCH-PREFLIGHT-REFRESH
- Aggiornato stato pre-flight QA: `.rebuild-state.json` riallineato e audit documentato.

## QA-AUDIT-2025-09-29
- QA audit report generated.

## PATCH-UI-POLISH-FORM
- PATCH: UI polish del form (pills, progress, micro-animazioni, dark mode).

## PATCH-2025-09-29-A
- Aggiornata l'integrazione Brevo con liste dedicate IT/EN, mappa prefissi telefonici configurabile e log iscrizioni automatiche su conferma/visita.

## 0.1.0 - Bootstrap
- Avvio del progetto FP Restaurant Reservations.
- Struttura directory iniziale e classi stub core/domain.
- File di configurazione tooling (composer, phpcs, phpstan, vite, package.json).
- File licenza GPLv2, readme e pot di localizzazione.
- Schema database con tabelle prenotazioni, clienti, eventi e log (migrazioni FASE 1).
- Pannello impostazioni amministrative multi-scheda con validazioni per notifiche, pagamenti, integrazioni e stile (FASE 2).
- Form frontend multi-step con rilevamento lingua automatico, bottone PDF tracciato, shortcode/blocco Gutenberg/widget Elementor e data layer base (FASE 3).
- Motore disponibilità con gestione turni, buffer, chiusure ricorrenti e endpoint REST /availability per il calcolo slot (FASE 4).
- Endpoint REST `/fp-resv/v1/reservations` con rate limit, nonce, honeypot e captcha hook per creare prenotazioni senza pagamento, email transazionale al cliente e audit log della creazione (FASE 5).
- Notifiche email immediate a ristorante e webmaster con allegato ICS, log spedizioni, retry Action Scheduler ed endpoint REST di test invio (FASE 6).
- Agenda amministrativa con pagina dedicata, asset placeholder e API REST protette per pianificazione, creazione rapida, aggiornamento stato e spostamento prenotazioni (FASE 7).
- Modulo eventi con CPT pubblico, prenotazione biglietti, QR testuali, API REST per vendita ed export invitati con data layer e rilevamento pagamento Stripe opzionale (FASE 8).
- Pagamenti Stripe opzionali con PaymentIntent, repository dedicato, conferma pubblica e API admin per capture/void/refund con sincronizzazione stato prenotazione (FASE 9).
- Tracking & Consent Mode v2 con manager centralizzato, GA4/Ads/Meta/Clarity condizionati dal consenso, dataLayer orchestrato lato server, gestione cookie UTM e propagazione eventi nelle risposte REST (FASE 10).
- Automazione Brevo con upsert contatti, tracking eventi reservation_confirmed/visited/post_visit, survey REST con calcolo NPS e template review Google (FASE 11).
- Integrazione Google Calendar con refresh token OAuth, controllo disponibilità anti-overbooking, sincronizzazione deterministica fp-resv-{id} e pulizia eventi in base allo stato prenotazione (FASE 12).
- Gestione sale e tavoli con repository dedicato, layout service, SPA amministrativa drag & drop e API REST per CRUD, merge/split e suggerimenti automatici di combinazioni tavoli (FASE 13).
- Pianificazione chiusure e orari speciali con servizio dedicato, preview impatti, audit log, REST protette e interfaccia admin con calendario riepilogativo (FASE 14).
- Stile del form personalizzabile con variabili CSS, anteprima live, checker WCAG e snippet custom scopo widget (FASE 15).
- Localizzazione automatica IT/EN con dizionari estendibili, form e survey multilingua e formattazione date/ore contestuale nelle email e nelle risposte API (FASE 16).
- Compliance GDPR con consensi granulari, scheduler di anonimizzazione, endpoint DSAR e impostazioni privacy dedicate (FASE 17).
- Dashboard KPI giornaliere con servizio report, export CSV/Excel e viewer dei log (mail, Brevo, audit) tramite nuova SPA admin e REST dedicate (FASE 18).
- Suite di test PHPUnit per repository, servizio e REST, bootstrap con stub WordPress, linting PHP/JS, Prettier e test Playwright per l'agenda drag&drop (FASE 19).
- Documentazione aggiornata con guida all'installazione, configurazione rapida, file CONTRIBUTING e template issue per supportare l'onboarding (FASE 20).
- Audit finale del plugin con verifica assenza file binari, coerenza schema DB, hook principali e documentazione aggiornata (FASE 21).
