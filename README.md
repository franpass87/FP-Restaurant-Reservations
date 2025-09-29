# FP Restaurant Reservations

FP Restaurant Reservations è un plugin WordPress pensato per ristoranti che necessitano di una piattaforma completa per la gestione delle prenotazioni, dei pagamenti opzionali, degli eventi e dei flussi di automazione marketing.

## Requisiti

- WordPress 6.5 o superiore (single site o multisite)
- PHP 8.1 o superiore con estensioni `curl`, `mbstring`, `json`
- Accesso REST API attivo su WordPress
- (Opzionale) Account Stripe e Brevo per le relative integrazioni

## Installazione

1. Copia la cartella del repository nella directory `wp-content/plugins/fp-restaurant-reservations` del tuo sito.
2. Verifica i permessi di scrittura sulle cartelle di WordPress per consentire la generazione di file temporanei (es. ICS).
3. Accedi alla Bacheca WordPress e attiva il plugin “FP Restaurant Reservations”.
4. Alla prima attivazione il plugin esegue automaticamente le migrazioni database necessarie.

> Suggerimento: è possibile tenere il repository come submodule Git per ambienti di staging/produzione.

## Funzionalità principali

- Prenotazione ristorante senza pagamento di default con email di conferma e link di gestione.
- Pagamenti Stripe opzionali (caparra, pre-autorizzazione, pagamento completo).
- Calendario agenda drag & drop in tempo reale con quick edit e creazione manuale.
- Modulo eventi completo con biglietti e QR testuale per il check-in.
- Gestione sale/tavoli con layout visuale, merge/split e suggeritore.
- Gestione chiusure e orari speciali con priorità e ricorrenze.
- Integrazione Google Calendar bidirezionale con controlli “busy”.
- Tracking GA4/Ads/Meta/Clarity con Consent Mode v2 e dataLayer centralizzato.
- Automazioni Brevo: upsert contatti, follow-up +24h dalla visita, survey post-visita e routing recensioni Google.
- Dashboard KPI con export CSV/Excel e viewer log.
- Gestione privacy (consensi granulari, DSAR, retention) e stile form personalizzabile (palette, tipografia, dark mode, WCAG checker).

## Configurazione rapida

Dopo l’attivazione visita **Impostazioni → FP Reservations** per completare i parametri principali:

1. **Generali** – orari di servizio, dimensioni party, valuta default, sedi.
2. **Notifiche** – indirizzi email ristorante e webmaster, template e allegati ICS.
3. **Pagamenti Stripe** – chiavi API, modalità pagamento (caparra/pre-auth/full), stato iniziale (OFF di default).
4. **Brevo** – chiave API, elenchi, mapping attributi e soglie review Google.
5. **Google Calendar** – credenziali OAuth, calendario target, preferenze privacy guest.
6. **Stile del form** – palette, tipografia, variabili CSS e anteprima live.
7. **Lingua** – auto-detect IT/EN, fallback e dizionari personalizzati.
8. **Chiusure & Periodi** – regole ricorrenti, riduzioni capienza e anteprima impatti.
9. **Sale & Tavoli** – configurazione layout, merge/split, suggeritore.
10. **Tracking & Consent** – configurazione manager consenso, integrazioni GA4/Ads/Meta/Clarity.

Per il bottone “Scarica PDF” definisci un URL per lingua/sede all’interno delle impostazioni generali.

## Testing & Tooling

Il progetto fornisce solo sorgenti: nessun artefatto di build viene committato. Sono disponibili gli script:

- `composer dump-autoload` per aggiornare il mapping PSR-4 (se necessario).
- `npm run lint:php` per eseguire PHPCS.
- `npm run lint:phpstan` per l’analisi statica.
- `npm run lint:js` e `npm run format:check` (una volta configurati ESLint/Prettier).
- `npm run test` per la suite PHPUnit definita in `tests/phpunit.xml`.

## Struttura del repository

- `fp-restaurant-reservations.php`: bootstrap del plugin.
- `src/`: codice PHP organizzato in domini (core, reservations, events, ecc.).
- `assets/`: sorgenti JS/CSS per frontend e admin SPA (nessun build output incluso).
- `templates/`: viste PHP per email, form e survey.
- `tests/`: bootstrap PHPUnit con stub WordPress, integrazioni e Playwright.
- `languages/`: file `.pot` stub per la localizzazione (nessun `.mo` binario).

## Supporto

Per assistenza o richieste commerciali contattare **info@francescopasseri.com**.

## Licenza

Distribuito sotto licenza GPLv2 o successiva.
