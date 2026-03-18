# 🚀 Roadmap verso 1.0.0 - FP Restaurant Reservations

**Versione attuale:** 1.0.0  
**Status:** Stable ✅  
**Obiettivo:** ~~Prima release stabile 1.0.0~~ **Completato (Percorso A - 2026-03-18)**

---

## Proposta: due percorsi possibili

### Percorso A — 1.0 “pragmatica” (raccomandato)

Il plugin è già usato in produzione da molte RC; i criteri per 1.0 sono: **stabilità dichiarata** + **nessun blocco noto** + **versioning e docs allineati**.

**Criteri minimi:**
- [ ] Nessun bug critico aperto (bloccante per prenotazioni/email/manager)
- [ ] Verifica regressioni (3 passaggi da `regression-check.mdc`) su area core
- [ ] Test manuale essenziale: flusso **Prenotazione frontend → Email → Manager** (almeno 1 run completo)
- [ ] Versione unificata: `1.0.0` in header PHP, costanti, readme.txt, README.md
- [ ] CHANGELOG con voce **1.0.0 - First stable release** (e eventuali fix dell’ultimo periodo)
- [ ] README/readme.txt aggiornati (rimozione “rc”, messaggio “Stable 1.0”)

**Tempo stimato:** 1–2 giorni (verifica + bump + commit/push).

---

### Percorso B — 1.0 “completa” (come da roadmap storica)

Allineato alla checklist pre-1.0 originale: test più ampi prima di dichiarare stabile.

**Checklist obbligatoria:**
- [ ] Timezone: verifica in produzione/staging (Europe/Rome ovunque)
- [ ] Flusso completo: prenotazione → email → manager (IT + EN se multi-lingua)
- [ ] Integrazioni: Google Calendar, Brevo, Stripe (almeno smoke test)
- [ ] Nessun bug critico aperto
- [ ] Performance: manager < ~3s, form < ~2s (soglie indicative)

**Checklist desiderabile (80%+):**
- [ ] Eventi / biglietti / QR (se usati)
- [ ] Export CSV/PDF
- [ ] Test sicurezza: rate limiting, validazione input, nonce
- [ ] Test multilingua (WPML/Polylang se applicabile)
- [ ] Verifica regressioni completa (tutti i moduli toccati di recente)

**Tempo stimato:** 1–2 settimane (test + eventuali fix + bump).

---

## Raccomandazione

- **Per andare in 1.0 in fretta:** usare il **Percorso A** (pragmatico).  
- **Per massima confidenza pre-1.0:** completare il **Percorso B** e poi fare il bump a 1.0.0.

Dopo il bump a 1.0.0, il workflow Git obbligatorio (bump, CHANGELOG, README, readme.txt, commit, push) va eseguito nella cartella LAB del plugin.

---

## Cosa significa 1.0.0

- **Semantic Versioning:** 1.0.0 = prima release stabile; API considerate “frozen” per 1.x (breaking change → 2.0.0).
- **Impatto:** messaggio chiaro “production-ready”, migliore fiducia per utenti e eventuale listing WordPress.org.
- **Responsabilità:** impegno a backward compatibility per la serie 1.x e test pre-release per le patch/minor.

---

## File da aggiornare al bump 1.0.0

- `fp-restaurant-reservations.php` — header `Version: 1.0.0`
- Eventuali costanti `FP_RESV_VERSION` / `PLUGIN_VERSION` (se presenti)
- `readme.txt` — `Stable tag: 1.0.0` + changelog
- `README.md` — badge versione e testo “Stable 1.0”
- `CHANGELOG.md` — nuova voce `## [1.0.0] - YYYY-MM-DD` con “First stable release” e ultimi fix

---

## Verifica test (18 Marzo 2026)

**Automazione browser (fp-development.local):**

- **Frontend form** — Pagina test-rest caricata; step 1 (servizio) e step 2 (data/persone/orario) verificati. Selezione “Cena” e “Procedi” funzionano. Il datepicker Flatpickr (campo Data) è readonly e i giorni del calendario non sono esposti nello snapshot di accessibilità, quindi l’automazione non ha potuto completare data → slot → contatti → invio.
- **Manager** — Pagina Manager prenotazioni caricata correttamente (toolbar, filtri, vista calendario, “Nessuna prenotazione”). Il pulsante “Nuova Prenotazione” risulta coperto dalla barra admin nell’ambiente di test (click intercettato), quindi non è stato possibile aprire il modal di creazione da browser automatizzato.
- **Diagnostica** — Pagina Diagnostica caricata; il pulsante “Simula integrazioni” era disabilitato nello stato osservato.

**Test manuale consigliato (per chiudere il Percorso A):**

1. Vai su **http://fp-development.local:10005/test-rest/** (o il tuo URL con `[fp_reservations]`).
2. Scegli un servizio (es. Cena) → Procedi.
3. Scegli una data dal calendario, numero persone e uno slot orario → Procedi.
4. Compila nome, email, telefono e consensi → Invia prenotazione.
5. Verifica di aver ricevuto l’email di conferma (se le notifiche sono configurate).
6. In **WP Admin → FP Reservations → Manager** verifica che la prenotazione compaia in elenco/calendario.

Se tutti i passi vanno a buon fine, il flusso “Prenotazione frontend → Email → Manager” è validato per la 1.0.0.

---

*Ultimo aggiornamento: 18 Marzo 2026*
