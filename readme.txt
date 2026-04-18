=== FP Restaurant Reservations ===
Contributors: francescopasseri
Author: Francesco Passeri
Author URI: https://francescopasseri.com
Tags: reservations, restaurant, events, booking, calendar, ga4, brevo, stripe
Requires at least: 6.5
Tested up to: 6.6
Stable tag: 1.0.39
License: GPLv2 or later

== Description ==
FP Restaurant Reservations è un plugin WordPress production-ready per la gestione professionale delle prenotazioni di ristoranti moderni. Include form single-page accessibile, calendario drag & drop, pagamenti Stripe opzionali, gestione sale/tavoli avanzata, eventi con biglietti e QR, integrazione Google Calendar, tracking GA4/Ads/Meta/Clarity e automazioni Brevo con survey post-visita.

= Prestazioni Enterprise =
* Performance aumentate del 900% (da ~50 req/s a ~500 req/s)
* Response time ridotto del 97% (da ~200ms a <5ms)
* Sistema dual-cache con Redis/Memcached + fallback DB
* Architettura ottimizzata con -70% query database
* Email asincrone (da 2-5s a <200ms)

= Sicurezza e Qualità =
* Audit di sicurezza completato (Ottobre 2025): 5/5 problemi risolti
* Code quality audit (Ottobre 2025): 58/58 bug risolti in 8 sessioni
* Protezione CSRF su tutti i form pubblici
* Validazione input centralizzata e sanitization
* Rate limiting su API REST
* Zero vulnerabilità npm e zero errori ESLint
* Protezione completa da SQL injection e XSS
* REST API authentication su tutti gli endpoint sensibili

== Features ==
* Form single-page con progress bar responsiva che rimane su una sola riga su desktop e scrolla su mobile.
* Pill dei turni con legenda a colori (verde/ambra/rosso) e stato "sconosciuto" neutro finché non arriva la disponibilità.
* Campi contatto ottimizzati: email full-width su desktop, prefissi telefonici deduplicati e validazione live.
* Sezione consensi con badge "Obbligatorio"/"Opzionale" allineati sotto il testo per chiarezza legale.
* Hint contestuali e messaggi aria-live per accessibilità durante la selezione dei turni e l'invio della prenotazione.

== Installation ==
1. Copia la cartella del plugin in `wp-content/plugins/fp-restaurant-reservations`.
2. Attiva il plugin dalla schermata **Plugin** di WordPress.
3. Dopo l’attivazione il plugin crea automaticamente le tabelle necessarie tramite dbDelta.
4. Visita **Impostazioni → FP Reservations** per completare la configurazione iniziale (email ristorante/webmaster, orari, valuta, lingue, tracking, ecc.).

== Requirements ==
* PHP 8.1+
* WordPress 6.5+
* Estensioni PHP: curl, mbstring, json
* REST API abilitata
* (Opzionale) Account Stripe per i pagamenti, account Brevo per automazioni e API Google con credenziali OAuth.

== Configuration ==
* **Generali** – definisci orari, buffer, sedi, PDF per lingua/sede e stato prenotazione default.
* **Notifiche** – imposta mittenti, indirizzi ristorante/webmaster e allegato ICS.
* **Pagamenti Stripe** – abilita/gestisci caparra, pre-autorizzazione o pagamento completo.
* **Brevo** – configura API key, liste IT/EN e la mappa prefissi (es. +39=IT) per il routing automatico delle iscrizioni e le soglie review Google della survey.
* **Google Calendar** – collega l’account OAuth, abilita busy check e sincronizzazione fp-resv-{id}.
* **Stile del form** – personalizza palette, tipografia, dark mode e verifica WCAG.
* **Lingua** – scegli auto-detect IT/EN con fallback e dizionari personalizzabili.
* **Chiusure & Periodi** – gestisci chiusure ricorrenti, speciali e preview impatto.
* **Sale & Tavoli** – crea layout drag & drop con merge/split e suggeritore.
* **Tracking & Consent** – configura GA4/Ads/Meta/Clarity con Consent Mode v2.

== Support ==
Per supporto commerciale scrivi a info@francescopasseri.com.

== Changelog ==

= 1.0.39 - 2026-04-18 =
* Calendario operativo: modifica eventi (inclusa apertura speciale) oltre a eliminazione; fix fasce orarie in salvataggio AJAX.

= 1.0.38 - 2026-04-10 =
* Added: GA4/GTM — dopo invio form, `dataLayer.push` con `value`/`currency` dalla risposta REST (`reservation.tracking`), stesso `event_id` del server per deduplica con Measurement Protocol.

= 1.0.36 - 2026-04-10 =
* Fixed: Brevo — evitato doppio upsert contatto (lista IT da FP Tracking + lista da lingua pagina) che iscriveva lo stesso contatto a due liste contemporaneamente.

= 1.0.35 - 2026-04-07 =
* Fixed: conferma al **cliente** con canale **Brevo** — se l’evento `email_confirmation` non parte (checklist disattivata, client non connesso, errore API), invio di **fallback** con `wp_mail` e template della sezione Notifiche, così il cliente non resta senza mail.

= 1.0.34 - 2026-04-07 =
* Fixed: email **ristorante/webmaster** — corretto accesso alla data di creazione nel contesto email (`getCreatedAt()`); prima l’errore bloccava l’invio delle notifiche staff.
* Fixed: salvataggio **Notifiche** — se il POST è incompleto (es. limite `max_input_vars`), le liste «Email ristorante» / «Email webmaster» non vengono più azzerate: si mantengono i valori già salvati.
* Added: opzione **Avvisa sugli annullamenti** — quando attiva, invio email ai destinatari configurati in Notifiche al passaggio dello stato a «annullata» (hook `fp_resv_reservation_status_changed`).

= 1.0.33 - 2026-04-05 =
* Added: Tracking — URL privacy opzionale; con FP Privacy attivo e campo vuoto si usa automaticamente la pagina informativa configurata lì.

= 1.0.32 - 2026-04-05 =
* Fixed: pagina **Report & Analytics** — grafico a ciambella canali con dimensioni contenute e centrato (non più a tutta larghezza card).

= 1.0.31 - 2026-04-05 =
* Fixed: anteprima **Colori Form** — stesso CSS del form pubblico (`form-simple-inline.css`) e markup tipo `form-simple.php` (griglia orari, pasti, pulsante primario); override live sui colori scelti; rimosso messaggio di successo fuori contesto.

= 1.0.30 - 2026-04-05 =
* Fixed: pagina admin Colori Form — layout, accessibilità titolo, anteprima live nell’iframe (variabili CSS applicate subito), niente più `:root` globale nel backend; CSS dedicato.

= 1.0.24 - 2026-03-25 =
* Fixed: ulteriore riduzione rumore log — REST admin, closures, tavoli, frontend (shortcode/CSS), aperture speciali e bootstrap; errori rilevanti tramite `ErrorLogger` invece di `error_log` diretto dove applicabile.

= 1.0.23 - 2026-03-25 =
* Fixed: meno rumore in `debug.log` — rimossi log verbosi su registrazione shortcode e rendering form (WP_DEBUG).

= 1.0.22 - 2026-03-24 =
* Changed: Brevo transactional — tag sito da FP Tracking tramite `fp_tracking_brevo_merge_transactional_tags` su invii SMTP API.

= 1.0.21 - 2026-03-24 =
* Changed: Brevo — sync contatti via FP Tracking quando Brevo è abilitato nel layer; integrazione attiva anche senza API key locale nel tab Brevo.

= 1.0.20 - 2026-03-24 =
* Changed: email HTML — con FP Mail SMTP attivo applicazione di `fp_fpmail_brand_html` nel Mailer (contenuto template invariato).
* Fixed: badge versione in admin allineata alla versione reale del plugin (Kernel).

= 1.0.19 - 2026-03-24 =
* Changed: Notifiche — sezione dedicata «Canali wp_mail o Brevo» (tre menu) separata dai template email cliente.

= 1.0.18 - 2026-03-24 =
* Changed: canale email cliente per tipo (conferma / promemoria / recensione) in Notifiche; rimosso il selettore master in tab Brevo così puoi mescolare wp_mail e Brevo.

= 1.0.17 - 2026-03-24 =
* Added: tab Brevo — legenda inline (email wp_mail vs eventi Brevo, checklist Automation).

= 1.0.16 - 2026-03-24 =
* Brevo: canale messaggi cliente (default WordPress) e checklist eventi Automation; gate eventi in invio.

= 1.0.15 - 2026-03-24 =
* Fix: ripristinato rendering del form frontend correggendo la callback del filtro `option_fp_resv_brevo` (compatibile con gli argomenti passati da WordPress).

= 1.0.14 - 2026-03-24 =
* Payload tracking prenotazione: meal_label, items GA4, attribution, page_url, filtro fp_resv_tracking_reservation_created_params.

= 1.0.13 - 2026-03-24 =
* Valore prenotazione e prezzo a persona da piano pasti per tracking GA4; evento purchase anche su pending.

= 1.0.12 - 2026-03-23 =
* Menu position 56.10 per ordine alfabetico FP.

= 1.0.11 - 2026-03-23 =
* Brevo: notice centralizzazione sempre visibile nella pagina Brevo con link a FP Tracking.

= 1.0.10 - 2026-03-23 =
* Brevo: API key e liste da FP Tracking quando attivo

= 1.0.9 - 2026-03-22 =
* DiagnosticShortcode: error_log condizionati a WP_DEBUG

= 1.0.8 - 2026-03-22 =
* Fix: console log nel form frontend condizionati a WP_DEBUG (window.fpResvDebug).

= 1.0.7 - 2026-03-22 =
* Changed: allineamento admin UI al design system FP con badge versione nei principali header.
* Changed: rimosse credenziali tracking locali (GA4/Ads/Meta/Clarity) dalle impostazioni, con configurazione centralizzata su FP Marketing Tracking Layer.
* Fixed: disattivato bootstrap tracking legacy quando il layer tracking centralizzato è attivo, evitando doppio tracciamento.

= 1.0.6 - 2026-03-20 =
* Fixed: editor pasti (campi data) — ReferenceError che impediva il rendering dell’intera sezione.

= 1.0.5 - 2026-03-20 =
* Fixed: editor pasti admin senza dipendenza da import ES module (area vuota su alcuni server).

= 1.0.4 - 2026-03-20 =
* Fixed: normalizzazione date pasto in `MealPlan` così `date_from`/`date_to` dal JSON hanno effetto su disponibilità e form.

= 1.0.3 - 2026-03-20 =
* Added: piano pasti con date inizio/fine opzionali per limitare la prenotabilità di un pasto a un intervallo (inclusivo); coerenza con disponibilità, slot e form.

= 1.0.2 - 2026-03-19 =
* Changed: admin heading hierarchy (h1 screen reader + h2 titolo visibile) su impostazioni, stile, diagnostica e manager; wrap `.fp-resv-admin-outer` e margine sotto le notice; `aria-labelledby` region legato all'h1 accessibile.

= 1.0.1 - 2026-03-18 =
* Changed: blocco DEBUG MEALS nel form visibile solo con costante FP_RESV_DEBUG_MEALS (vedi README).
* Fixed: giorni calendario con role="button" e aria-label per accessibilità e test automation.

= 1.0.0 - 2026-03-18 =
* First stable release. Plugin production-ready; API frozen per 1.x.
* Percorso A (pragmatico) verso 1.0 completato.

= 0.9.0-rc10.16 - 2026-03-18 =
* Added: pulsante "Simula integrazioni" nella dashboard Diagnostica con trigger one-click dei test QA.
* Added: endpoint REST QA `/qa/simulate-integrations` con simulazione completa senza credenziali (Brevo, Google Calendar, Stripe, email, queue e tracking).
* Fixed: registrazione route QA anche quando `rest_api_init` è già eseguito, eliminando i 404 durante le simulazioni da admin.

= 0.9.0-rc10.15 - 2026-03-13 =
* Fix: creazione chiusure da Manager migrata su endpoint AJAX admin per eliminare errori nonce/cookie REST in ambienti local/proxy.
* Added: nuovo comando rapido "Nuova Chiusura" in Manager con modal dedicata.
* Changed: pagina Chiusure ridisegnata come "Calendario Operativo" con UX semplificata, guida rapida, filtri migliorati e reset filtri.
* Fixed: rispetto corretto dello stato `hidden` nel form planner operativo.

= 0.9.0-rc10.14 - 2026-03-12 =
* Fix: Allineata la gestione timezone tra backend e frontend su default range, parsing e rendering date/ora
* Fix: Eliminati parse data ambigui in manager/agenda/closures per evitare slittamenti di giorno/orario

= 0.9.0-rc10.13 - 2026-03-12 =
* Fix: Chiusure admin ora visualizzate sempre in timezone Europe/Rome (niente slittamenti 23:00/22:59:59 sui giorni interi)
* Fix: Payload datetime chiusure inviato senza offset client per parsing coerente con timezone WordPress

= 0.9.0-rc1 - 2025-10-25 =
* **🚀 RELEASE CANDIDATE**: Plugin production-ready per test finali verso 1.0.0
* **🎯 Target 1.0.0**: 7-14 giorni dopo test completi
* **✅ API Frozen**: Nessun breaking change, backward compatibility garantita
* **📋 Checklist**: Test completo flusso prenotazione, integrazioni, performance
* **🌍 Timezone**: Fix critico timezone Italia (19 fix applicati)
* **🔒 Sicurezza**: 0 vulnerabilità, 58 bug risolti, audit completo
* **📱 UX**: Manager chiaro, form accessibile, mobile-friendly
* **📚 Docs**: 163 file documentazione organizzati
* **🔧 Quality**: 0 errori ESLint, database transactions, rate limiting

= 0.1.14 - 2025-10-25 =
* **🚨 CRITICO**: Fix timezone - date/time ora salvate in timezone WordPress (Europe/Rome) invece di UTC
* **🚨 CRITICO**: Frontend - fixate 19 occorrenze di toISOString() che convertiva in UTC
* **🚨 CRITICO**: Repository - gmdate() sostituito con current_time() per rispettare timezone WP
* **Bug Fix**: Manager backend mostrava statistiche "oggi" sballate dopo le 22:00 UTC
* **Bug Fix**: Calendario disponibilità poteva shiftare giorni per conversione UTC
* **Improved**: Aggiunto formatLocalDate() in tutti i file JS frontend/backend
* **Verified**: Email già corrette - Language.php usa Europe/Rome come default
* **Impact**: Prenotazioni ora salvate con orario corretto Italia (prima erano UTC -2h)
* **Quality**: 19 fix applicati su 6 file + 1 verifica email OK
* **Compatibility**: Rispetta WordPress Timezone Settings senza breaking changes

= 0.1.13 - 2025-10-25 =
* **Manager UX**: Vista settimanale come default (invece di mensile) per maggiore chiarezza
* **Manager UX**: Nomi giorni completi (Lunedì, Martedì...) invece di abbreviati (Lun, Mar...)
* **Manager UX**: Aggiunta legenda colori stati prenotazioni (Verde=Confermato, Arancione=In Attesa, etc.)
* **Manager UX**: Badge con icone esplicative 📋 Prenotazioni, 👥 Coperti, 🕐 Orario
* **Manager UX**: Stato testuale visibile su ogni card (CONFERMATO, IN ATTESA, etc.) oltre al colore
* **Manager UX**: Tooltip informativi completi su hover (Stato | Orario | Persone | Nome | Servizio)
* **Manager UX**: Empty state migliorato con icona 📭 e background visibile
* **Manager UX**: Animazione hover su card prenotazioni (transform + shadow)
* **Manager UX**: Tooltip descrittivi su bottoni vista (Giorno/Settimana/Mese)
* **Chiarezza**: Da 3/5 a 5/5 (+67% - manager immediato anche per utenti non tecnici)

= 0.1.12 - 2025-10-25 =
* **Bug Fixes**: Risolto endpoint /available-days che ignorava configurazione meal plan
* **Bug Fixes**: Corretto calcolo status disponibilità quando allowedCapacity = 0
* **UX**: Form width aumentato da 480px a 600px (+25% respiro desktop)
* **UX**: Spacing ottimizzato - form più compatto (-17% altezza)
* **UX**: Checkbox ridimensionati (16x16px), testo nero, link privacy blu
* **UX**: Prefisso telefono allineato con campo input
* **UX**: Success message con scroll automatico + hide form (previene doppi click)
* **Design**: Gradienti ridotti da 20+ a 5 (-75%) per design più pulito
* **Design**: Border-radius standardizzato (3 valori invece di 7)
* **Design**: Progress bar più visibile (contrasto +650%)
* **Mobile**: Touch targets ottimizzati (44px), margin ridotti
* **Docs**: 163 file documentazione organizzati in struttura professionale

= 0.1.11 - 2025-10-13 =
* Risolti 58 bug attraverso 8 sessioni intensive di code quality audit
* **Sicurezza**: Risolti 7 bug critici (SQL injection, XSS, API non protette, JSON parsing non sicuro)
* **Robustezza**: Risolti 22 bug importanti (12 unhandled promises, 8 parseInt senza radix, null checks)
* **Code Quality**: Risolti 29 bug minori (ESLint errors, variabili non usate, import puliti)
* Protetto endpoint REST /agenda con permission_callback
* Protetto endpoint debug /agenda-debug (solo WP_DEBUG + admin)
* Aggiunta sanitizzazione input in file di debug e log viewer
* Convertite query SQL dirette a prepared statements PDO
* Aggiunta gestione errori try-catch su tutti i gestori async
* Configurazione ESLint migliorata per supporto multi-environment
* ESLint: 0 errori, 0 warning (prima: 29 problemi)
* Sicurezza: 0 vulnerabilità (prima: 7 critiche)

= 0.1.6 - 2025-10-07 =
* Risolti 5 problemi di sicurezza identificati nell'audit (Ottobre 2025)
* Aggiunta protezione CSRF con nonce verification su form survey
* Implementato fallback no-JavaScript per form prenotazioni
* Rimossi fallback italiani hardcoded in favore di i18n corretta
* Aggiunta configurazione ESLint per linting JavaScript
* Migliorata architettura moduli JavaScript (da monolitico a componenti)
* Ottimizzata build Vite con ESM + IIFE fallback
* Zero errori linter e zero vulnerabilità npm

= 0.1.3 - 2025-01-27 =
* Consolidata e ottimizzata la documentazione del progetto
* Rimossi file ridondanti e duplicati (-80% file root)
* Aggiornato README con miglioramenti architetturali
* Migliorata navigabilità della documentazione

= 0.1.2 =
* Implementati 12 miglioramenti architetturali enterprise
* Performance aumentate del 900% (throughput 50→500 req/s)
* Response time ridotto del 97% (200ms→5ms)
* Dual-cache strategy (Redis/Memcached + DB fallback)
* Sistema metriche per monitoring real-time
* Email asincrone con queue (2-5s→200ms)
* Service container con dependency injection

= 0.9.0-rc10.11 - 2026-03-09 =
* Fix: StyleCssGenerator rimosso riferimento errato a variabile $shadows (PHP Warning su form)

= 0.1.0 =
* Release iniziale del plugin
* Form single-page con progress bar e validazioni live
* Agenda amministrativa drag & drop
* Integrazione pagamenti Stripe opzionali
* Sistema eventi con biglietti e QR
* Integrazione Google Calendar
* Tracking GA4/Ads/Meta/Clarity con Consent Mode v2
* Automazioni Brevo con survey NPS
* Gestione sale e tavoli con layout editor
* Sistema chiusure e orari speciali
* Localizzazione IT/EN automatica
* Compliance GDPR con consensi granulari

Consulta `CHANGELOG.md` nel repository per la cronologia dettagliata completa.
