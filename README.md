# FP Restaurant Reservations

FP Restaurant Reservations è un plugin WordPress completo per la gestione delle prenotazioni di un ristorante moderno: form single-page accessibile, agenda drag & drop, pagamenti facoltativi, analisi marketing e strumenti di diagnostica per l'assistenza.

## Requisiti

- WordPress 6.5 o superiore (single site o multisite)
- PHP 8.1 o superiore con estensioni `curl`, `json`, `mbstring`
- Accesso REST API attivo su WordPress
- (Opzionale) account Stripe e Brevo per le rispettive integrazioni

## Installazione

1. Copia la cartella del repository in `wp-content/plugins/fp-restaurant-reservations`.
2. Installa le dipendenze opzionali: `composer install --no-dev` e `npm install` (necessario solo negli ambienti di build).
3. Esegui `npm run build` per generare i bundle JavaScript/CSS quando lavori da sorgente.
4. Attiva il plugin dalla Bacheca WordPress.
5. Dopo l'attivazione visita **Impostazioni → FP Reservations** per completare la configurazione guidata.

## Come integrare il form

Il form pubblico è una single-page application con progress bar, CTA sticky e validazioni live. È disponibile in tre modalità:

- Shortcode `[fp_reservations]`
- Blocco Gutenberg **FP Reservations → Form**
- Widget Elementor **FP Reservations Form**

Gli asset frontend vengono caricati solo quando shortcode/blocco/widget sono presenti sulla pagina. Il form espone gli eventi `dataLayer`/`fpResvTracking.dispatch`:

| Evento | Descrizione |
| --- | --- |
| `reservation_start` | L'utente apre il form e seleziona una data valida |
| `section_unlocked` | Passaggio a uno step successivo (party, slot, dettagli, conferma) |
| `meal_selected` | Scelta del turno/pasto |
| `form_valid` | Tutti i campi obbligatori risultano validi |
| `reservation_submit` | Invio della prenotazione |
| `reservation_confirmed` | Prenotazione confermata lato server |
| `purchase` | Prenotazione con pagamento stimato o confermato |

Il controller JavaScript implementa debounce da 400 ms, retry con backoff sugli slot (`/availability`) e rispetta gli header `Retry-After` per il rate limiting.

## Pagine amministrative

Tutte le schermate dell'area di amministrazione condividono un layout coerente (breadcrumb, barre laterali, card responsive, supporto tema scuro).

- **Agenda** – Calendario drag & drop con azioni rapide e dettagli prenotazione.
- **Tavoli & Sale** – Editor visuale con merge/split e suggerimenti automatici.
- **Chiusure** – Gestione periodi speciali e riduzioni di capienza con anteprima.
- **Report & Analytics** – Grafico a torta per canali di acquisizione, linea temporale giornaliera, tabella sorgenti UTM ed export CSV.
- **Diagnostica** – Log separati (Email, Webhook, Stripe, API, Cron/Queue) con filtri, ricerca, paginazione ed export CSV.
- **Stile & Tema** – Editor token (palette, tipografia, radius, shadow, focus ring) con anteprima live del form e checker contrasto WCAG AA.
- **Impostazioni** – Schede per generali, notifiche, pagamenti Stripe, Brevo, Google Calendar, privacy e lingua.
- **Eventi** – Custom post type incluso nel menu del plugin per gestire degustazioni e serate speciali.

## API, sicurezza e performance

- Endpoint REST namespaced `fp-resv/v1` con `no-store`, sanitizzazione parametri, nonce/capability dove richiesto e rate limit sugli slot.
- Cache transient 30–60 s sulle risposte di disponibilità più comuni.
- Export CSV per report e log con paginazione lato server.
- Seeder QA disponibile via REST `/fp-resv/v1/qa/seed` (autenticazione amministratore) e WP-CLI `wp fp-resv qa seed` per generare dati demo o pulire le fixture.

## Tooling & sviluppo

- `npm run build` compila i bundle frontend (ESM + IIFE) e gli asset admin tramite Vite.
- `npm run lint:php` esegue PHPCS; `npm run lint:phpstan` avvia PHPStan.
- `composer dump-autoload` aggiorna il classmap PSR-4.
- I file distribuiti sono generati nella cartella `assets/dist/`; gli asset sorgente restano in `assets/js` e `assets/css`.
- Il workflow GitHub `Build Plugin Zip` crea automaticamente lo ZIP pronto per l'upload ad ogni tag `v*`.

## Release process

1. Aggiorna il codice e assicurati che i test locali siano verdi.
2. Esegui `bash build.sh --bump=patch` per incrementare la versione e generare lo ZIP (usa `--set-version=X.Y.Z` se necessario).
3. Controlla il contenuto di `build/` e carica lo ZIP in WordPress oppure allegalo alla release.
4. Crea un tag `vX.Y.Z` e `git push --tags` per attivare il workflow GitHub che pubblica lo ZIP come artefatto `plugin-zip`.

## Supporto

Per assistenza o richieste commerciali scrivere a **info@francescopasseri.com**.

## Licenza

GPLv2 o successiva.
