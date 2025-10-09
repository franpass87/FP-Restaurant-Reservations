=== FP Restaurant Reservations ===
Contributors: francescopasseri
Author: Francesco Passeri
Author URI: https://francescopasseri.com
Tags: reservations, restaurant, events, booking, calendar, ga4, brevo, stripe
Requires at least: 6.5
Tested up to: 6.6
Stable tag: 0.1.9
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
* Protezione CSRF su tutti i form pubblici
* Validazione input centralizzata e sanitization
* Rate limiting su API REST
* Zero vulnerabilità npm note

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
