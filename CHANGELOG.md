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
- Automazione Brevo con upsert contatti, tracking eventi reservation_confirmed/visited/post_visit, follow-up +24h dalla visita, survey REST con calcolo NPS e template review Google (FASE 11).
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
