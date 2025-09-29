# Contributi

Grazie per l'interesse nel contribuire a FP Restaurant Reservations. Questo progetto segue un percorso di sviluppo a fasi documentato in `.rebuild-state.json`.

## Requisiti di sviluppo

- PHP 8.1+
- Node.js 18+ (per tooling JS, nessun build output da committare)
- Composer per la gestione dell'autoload PSR-4

## Flusso di lavoro

1. Crea un branch dedicato descrittivo (es. `feature/agenda-dnd`).
2. Mantieni le modifiche coerenti con la fase corrente descritta nel file `.rebuild-state.json`.
3. Esegui gli strumenti di qualità prima di aprire la PR:
   - `npm run lint:php`
   - `npm run lint:phpstan`
   - `npm run lint:js`
   - `npm run test`
4. Assicurati di non committare `vendor/`, `node_modules/`, `dist/`, `build/` o altri artefatti binari.

## Linee guida

- PHP conforme a PSR-12 e WordPress Coding Standards.
- Tutti i nuovi file devono utilizzare `declare(strict_types=1);` dove applicabile.
- Per le traduzioni usa il text domain `fp-restaurant-reservations`.
- Documenta la fase nel `CHANGELOG.md` e aggiorna `.rebuild-state.json` quando completi un blocco di lavoro.

## Segnalazione bug

Apri una issue seguendo il template in `.github/ISSUE_TEMPLATE.md` e includi:
- Versione del plugin
- Versione di WordPress / PHP
- Passi per riprodurre
- Log rilevanti (senza dati sensibili)
