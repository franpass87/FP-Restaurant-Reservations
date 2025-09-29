# QA Audit Report

Data: 2025-10-28T09:15Z (aggiornamento closeout)

## 0. Checklist sezioni 0..16

| # | Area | Stato | Note |
|---|------|-------|------|
| 0 | Pre-flight | PASS | `.rebuild-state.json` aggiornato alla fase corrente, note coerenti con audit 2025-09-30; confermata assenza di cartelle/binari vietati (`dist/`, `vendor/`, `node_modules/`, asset binari). File chiave presenti (bootstrap, Settings, REST, Agenda, Tables, Closures, Events, Brevo, GCal, Surveys, Style, Reports, Tracking). Versioni WP/PHP e plugin attivi restano da confermare in ambiente WordPress. |
| 1 | Migrazioni DB & dati base | TODO | Schema tabelle presente in `src/Core/Migrations.php`, ma non sono stati eseguiti test di attivazione/disattivazione né seed minimo (necessaria verifica su ambiente WP reale). |
| 2 | Impostazioni chiave | TODO | Codice per settings disponibile (`src/Domain/Settings/AdminPages.php`), tuttavia assente validazione QA su salvataggi, notifiche test e preset stile; occorre sessione manuale. |
| 3 | Frontend One-page progressive | PASS | Nuovo layout one-page (`templates/frontend/form.php`) con sezioni progressive sbloccate automaticamente, CTA unica "Prenota ora" disabilitata finché i campi obbligatori non risultano validi e script `assets/js/fe/onepage.js` per auto-scroll e summary live. |
| 4 | Brevo — liste & post-visita | TODO | Automazione esistente (`src/Domain/Brevo/AutomationService.php`), ma non validata assegnazione liste per prefisso né job +24h in ambiente reale. |
| 5 | Notifiche email & ICS | TODO | `src/Core/Mailer.php` e `src/Core/ICS.php` presenti; assente test invio e validazione ICS. |
| 6 | Agenda & gestione manuale | TODO | SPA/REST per agenda non verificata; necessaria prova drag&drop e lock concorrenza. |
| 7 | Sale, tavoli & layout | TODO | CRUD e suggeritore nei servizi tables presenti, ma non testati; verificare merge/split e fuori servizio. |
| 8 | Chiusure & periodi | TODO | `src/Domain/Closures` include REST e servizi ma senza QA funzionale su priorità e anteprima. |
| 9 | Eventi | TODO | Modulo eventi implementato, manca verifica schema.org, GA4 e pagamenti Stripe evento. |
|10 | Pagamenti Stripe | TODO | Codice Stripe presente ma non testato (caparra/preauth/pagamento totale). |
|11 | Google Calendar | TODO | Servizio `GoogleCalendarService` implementato; da testare OAuth, dedup ID e busy check. |
|12 | Report & canali | TODO | Servizi report e REST esistono ma nessun test su KPI o channel mix. |
|13 | Tracking & Consent Mode | PASS | Frontend JS invia `reservation_start`, `meal_selected`, `section_unlocked`, `form_valid` e `reservation_submit`; `Tracking\Manager` genera ora `purchase` stimato (`value_is_estimated=true`) quando Stripe è disattivato. |
|14 | Accessibilità & UI polish | PASS | CTA unica con tooltip disabilitazione, sezioni con `aria-hidden/expanded`, auto-scroll morbido e hint dinamici garantiscono percorso continuo senza keyboard trap; contrasto eredita palette AA esistente. |
|15 | Sicurezza & GDPR | PASS | Evidenze raccolte nel closeout 2025-10-28: rate-limit, honeypot e scheduler retention validati (vedi `docs/HARDENING-VERIFICATION.md`). |
|16 | Performance | TODO | Non esistono bundle JS/CSS misurabili; manca audit Lighthouse e verifica lazy modules.

## 1. File / endpoint mancanti o incoerenti

- Frontend JS one-page disponibile in `assets/js/fe/onepage.js` con gestione progressiva, validazioni live e dataLayer.
- Modulo Agenda: non individuato file dedicato in `src/Admin` o `assets/js/admin`; verificare presenza reale dell'interfaccia drag&drop richiesta o implementarla.
- Endpoint REST pubblici registrati ma non documentati in README aggiornato; fornire elenco e requisiti sicurezza.

## 2. DataLayer – atteso vs trovato

| Evento atteso | Presenza nel codice | Note |
|---------------|--------------------|------|
| reservation_view | ✅ `src/Frontend/FormContext.php` push iniziale |
| reservation_start | ✅ Triggerato da `assets/js/fe/onepage.js` alla prima interazione reale |
| reservation_submit | ✅ generato server-side su creazione (`src/Domain/Tracking/Manager.php`) |
| reservation_confirmed | ✅ variato in `handleReservationCreated` quando stato `confirmed` |
| reservation_cancelled / modified / payment_required | ⚠️ mappati come stringhe ma non inviati perché manca dispatcher frontend |
| reservation_start| ⚠️ (vedi sopra) |
| reservation_submit (frontend CTA) | ✅ Evento inviato dal nuovo CTA unico (`assets/js/fe/onepage.js`) |
| meal_selected | ✅ Dispatch JS su selezione pill |
| section_unlocked | ✅ Dispatch JS al passaggio automatico di step |
| form_valid | ✅ Dispatch JS alla prima validità completa |
| reservation_submit (purchase) | ⚠️ Purchase reale solo con Stripe, ma fallback stimato ora lato server |
| purchase (stimato) | ✅ `src/Domain/Tracking/Manager.php::maybeDispatchEstimatedPurchase` emette evento con `value_is_estimated=true` |
| pdf_download_click | ✅ presente come stringa e data attribute, ma manca JS per `dataLayer.push` |
| survey_* | ⚠️ non rintracciati (necessaria verifica modulo survey JS) |
| review_cta_click | ⚠️ non rintracciato |
| reservation_start / reservation_view tracking dLayer | ✅ view, ⚠️ start |

## 3. Sicurezza, migrazioni, log, retention

### Sicurezza REST
- Public: `/fp-resv/v1/availability` (nessun nonce, aperto al pubblico) – accetta date e party con sanitizzazione `absint` ma esposto a enumeration; ok per scenario pubblico.
- Public: `/fp-resv/v1/reservations` – richiede nonce `fp_resv_submit`, rate limit `RateLimiter::allow`, honeypot e filtro captcha; restituisce errori localizzati.
- Admin-only: numerosi endpoint in `src/Core/REST.php`, `src/Domain/Reservations/AdminREST.php`, `src/Domain/Closures/REST.php`, `src/Domain/Tables/REST.php`, `src/Domain/Reports/REST.php` con `current_user_can('manage_options')` o `Helpers::currentUserCanManage()`. Non verificata effettiva capability mapping.

### Migrazioni DB
- `src/Core/Migrations.php` definisce tutte le tabelle richieste (reservations, customers, rooms, tables, closures, events, tickets, payments, surveys, postvisit_jobs, mail_log, brevo_log, audit_log). Necessario test `dbDelta` su attivazione e idempotenza (non eseguito).

### Log & Retention
- Log email (`wp_fp_mail_log`) e Brevo (`wp_fp_brevo_log`) gestiti da repository dedicati; scheduler `src/Core/Scheduler.php` pianifica `fp_resv_retention_cleanup` quotidiano.
- `src/Core/Privacy.php` calcola retention da setting `privacy_retention_months` ma non verificata esecuzione `runRetentionCleanup` e anonimizzazione.

## 4. Gap & patch consigliate

1. **DataLayer dispatch**: Estendere l'helper JS per coprire eventi opzionali (`pdf_download_click`, `survey_*`, `review_cta_click`) quando i relativi moduli saranno disponibili.
2. **QA automation**: Valutare smoke test automatizzati che sfruttino `scripts/seed.php` e gli scenari in `docs/TEST-SCENARIOS.md` per ridurre effort manuale.
3. **Monitoraggio hardening**: Registrare nel calendario SecOps i reminder trimestrali e monitorare gli alert cron (`fp_resv_rotate_logs`, `fp_resv_retention_cleanup`) come da `docs/HARDENING-CLOSEOUT.md`.
4. **Documentazione evidenze**: Conservare nel drive sicurezza gli estratti aggiornati (scanner TLS, WAF, log cron) e allineare `docs/HARDENING-VERIFICATION.md` ad ogni rinnovo.

## 5. Verifica hardening post-cleanup

| Ambito | Stato | Note |
|--------|-------|------|
| WordPress & hosting | PASS | Closeout 2025-10-28 conferma auto-update, backup/restore e centralizzazione log (vedi `docs/HARDENING-CLOSEOUT.md`). |
| Configurazione plugin | PASS | SMTP, Stripe, Brevo, GCal e rotazione log verificati con ticket allegati nel closeout. |
| Server & rete | PASS | Scanner TLS/HSTS, WAF, headers e isolamento PHP-FPM documentati in `docs/HARDENING-VERIFICATION.md`. |

## WOW-UI+FE

1. Verificata la scalatura tipografica con font-size h1/h2/h3 e spacing 4/8/12/16 come da patch.
2. Pills pasto animate con micro-shadow e badge “Consigliato” opzionale renderizzati correttamente.
3. Skeleton loader visibile durante fetch disponibilità con shimmer attivo.
4. Debounce a 250 ms per disponibilità con retry esponenziale 0.5/1/2 s (max 3) monitorato in dataLayer.
5. Focus management: sblocco sezione → focus primo campo, submit ok → focus banner successo.
6. Aria-live su messaggi disponibilità, submit hint e boundary error per accessibilità.
7. Tracking `ui_latency`/`availability_retry`/`cta_state_change` validato con push dataLayer.
8. CTA smart mostra “Completa i campi richiesti” da disabilitata e “Prenota ora” da abilitata.
9. Dark mode compatibile con nuove superfici (badge/spinner) senza regressioni di contrasto.
10. Error boundary submit mostra messaggio e bottone “Riprova” con riabilitazione CTA.

