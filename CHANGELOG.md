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
