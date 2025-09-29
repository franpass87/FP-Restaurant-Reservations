=== FP Restaurant Reservations ===
Contributors: francescopasseri
Author: Francesco Passeri
Author URI: https://francescopasseri.com
Tags: reservations, restaurant, events, booking, calendar, ga4, brevo, stripe
Requires at least: 6.5
Tested up to: 6.6
Stable tag: 0.1.0
License: GPLv2 or later

== Description ==
FP Restaurant Reservations fornisce un flusso completo di prenotazioni ristorante con pagamenti Stripe opzionali, calendario drag & drop, gestione sale/tavoli, eventi con biglietti e QR testuale, integrazione Google Calendar, tracking GA4/Ads/Meta/Clarity e automazioni Brevo con survey post-visita.

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
Consulta `CHANGELOG.md` nel repository per la cronologia dettagliata.
