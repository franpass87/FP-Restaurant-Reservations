# Come impostare i tracciamenti correttamente

Guida alla configurazione di **GA4**, **Google Ads**, **Meta Pixel** e **Microsoft Clarity** nel plugin FP Restaurant Reservations.

---

## Google Tag Manager (GTM) – consigliato

**Sì, è meglio usare Google Tag Manager** se vuoi:

- Gestire GA4, Google Ads, Meta e Clarity da **un solo pannello** (GTM)
- Aggiungere o modificare tag **senza toccare il plugin** (versioni, anteprima, rollback)
- Centralizzare **Consent Mode** e regole di attivazione in GTM
- Dare al team marketing autonomia sui tag

**Come fare:**

1. **Nel plugin**: **Prenotazioni → Impostazioni → Tracking** → attiva **«Usa Google Tag Manager»**. Lascia vuoti GA4 Measurement ID, ID conversione Google Ads, Meta Pixel ID e Clarity (il plugin non caricherà più quei tag).
2. **Nel sito**: installa il **contenitore GTM** (snippet in head + body), ad esempio dal tema, da un plugin “Insert Headers and Footers” o da un modulo GTM del tema.
3. **In GTM**: crea i tag (GA4, Google Ads Conversion, Meta Pixel, Clarity) e i trigger basati sugli **eventi del dataLayer** che il plugin già invia (es. `reservation_view`, `reservation_confirmed`, `meal_selected`, `purchase`). La struttura degli eventi è descritta in [TRACKING-MAP.md](../api/TRACKING-MAP.md).

Con **«Usa Google Tag Manager»** attivo il plugin:

- **Non** carica più gtag.js, Meta Pixel né Clarity
- Continua a **scrivere tutti gli eventi nel `dataLayer`** (stessa struttura di sempre)
- Mantiene **consenso e cookie** (es. `fpResvTracking.updateConsent`); puoi far leggere a GTM lo stato di consenso da `fpResvTracking.getConsent()` o gestire il consenso direttamente in GTM

Per i nomi e i parametri degli eventi da usare in GTM, vedi [TRACKING-MAP.md](../api/TRACKING-MAP.md).

---

## Dove si configurano

1. In WordPress vai in **Prenotazioni** → **Impostazioni** (menu laterale).
2. Apri la scheda **Tracking** (o **Tracking & Consent**).
3. Compila i campi nella sezione **Integrazioni marketing** e, se serve, **Privacy & GDPR**.
4. Clicca **Salva modifiche**.

Tutti i valori vengono salvati nel gruppo di opzioni `fp_resv_tracking`.

---

## Campi da compilare

### GA4 (Google Analytics 4)

| Campo | Cosa inserire | Dove trovarlo |
|-------|----------------|----------------|
| **GA4 Measurement ID** | ID del tipo `G-XXXXXXXXXX` (solo lettere maiuscole e numeri dopo `G-`) | GA4 → Admin → Data Streams → tuo stream web → **Measurement ID** |
| **GA4 API Secret (per invii server-side)** | Token lungo (≥20 caratteri) | GA4 → Admin → Data Streams → Measurement Protocol API secrets → **Crea** e copia il secret |

- **Solo client-side**: basta il **Measurement ID**. Gli eventi partono dal browser (gtag).
- **Anche server-side**: compila anche **GA4 API Secret**. Gli eventi vengono inviati anche dal server (bypass ad blocker, più affidabilità).

Il plugin valida: Measurement ID nel formato `G-` + caratteri; API Secret con lunghezza minima 20.

---

### Google Ads

| Campo | Cosa inserire | Dove trovarlo |
|-------|----------------|----------------|
| **ID conversione Google Ads** | Valore nel formato `AW-XXXXXXXXX/YYYYYYYY` (ID account / etichetta conversione) | Google Ads → Strumenti → Conversioni → crea o modifica conversione → **Tag di conversione** → copia il valore `send_to` (es. `AW-123456789/AbCdEfGhIjKlMnOp`) |

Lo script carica gtag con la parte prima dello slash (`AW-XXXXXXXXX`). Le conversioni (es. prenotazione confermata) usano l’ID completo. Gli eventi Ads vengono inviati solo se il consenso **Ads** è concesso (Consent Mode v2).

---

### Meta (Facebook / Instagram)

| Campo | Cosa inserire | Dove trovarlo |
|-------|----------------|----------------|
| **Meta Pixel ID** | Numero del Pixel (es. `123456789012345`) | Meta Events Manager → Impostazioni Pixel → **ID Pixel** |
| **Meta Access Token (per invii server-side)** | Token lungo (≥50 caratteri) | Meta Events Manager → Impostazioni → Conversions API → **Genera token** e copia |

- **Solo client-side**: basta il **Meta Pixel ID**. Lo script fbq viene caricato solo se il consenso Ads è concesso.
- **Anche server-side**: compila anche **Meta Access Token** per inviare gli eventi (es. Purchase) tramite Conversions API.

Il plugin valida la lunghezza minima del token (50 caratteri).

---

### Microsoft Clarity

| Campo | Cosa inserire | Dove trovarlo |
|-------|----------------|----------------|
| **Microsoft Clarity Project ID** | ID alfanumerico del progetto | Clarity → Impostazioni progetto → **Project ID** (o dall’URL dopo `/project/`) |

Clarity viene caricato solo se **Analytics** e **Clarity** sono entrambi concessi nel consenso.

---

## Consent Mode e privacy

- **Stato Consent Mode predefinito**
  - **Negato**: tutti i consensi partono negati; si attivano solo dopo scelta esplicita dell’utente (es. banner).
  - **Concesso**: consensi concessi di default (solo se il sito non richiede banner consenso).
  - **Determina automaticamente**: il plugin imposta lo stato in base al contesto (consigliato se usi un banner cookie).

- **Durata cookie tracciamento** (giorni): TTL dei cookie di consenso (default 180).
- **Durata cookie UTM** (giorni): conservazione parametri UTM per attribuzione (default 90).

Nella sezione **Privacy & GDPR** puoi impostare:
- URL e versione dell’informativa privacy
- Checkbox consenso marketing/profilazione nel form
- Mesi dopo i quali anonimizzare i dati (0 = disattiva pulizia automatica)

---

## Checklist rapida

**Se usi GTM:** attiva «Usa Google Tag Manager», lascia vuoti gli ID nel plugin, installa GTM sul sito e configura i tag in GTM (vedi sezione GTM sopra).

**Se usi i tag dal plugin (senza GTM):**

1. **GA4**: Measurement ID `G-...` inserito; opzionale API Secret per server-side.
2. **Google Ads**: ID conversione `AW-.../...` copiato da Google Ads.
3. **Meta**: Pixel ID inserito; opzionale Access Token per Conversions API.
4. **Clarity**: Project ID inserito (se usi Clarity).
5. **Consent Mode**: scelto lo stato predefinito (di solito “Determina automaticamente”).
6. **Privacy**: URL informativa e, se serve, consensi aggiuntivi e retention.
7. **Salva** le impostazioni.

---

## Verifica

- **GA4**: Report → Realtime; fai una prenotazione di test e controlla gli eventi (es. `reservation_view`, `reservation_confirmed`).
- **Meta**: Events Manager → Test Events; verifica che gli eventi (es. Purchase) arrivino e, se usi CAPI, che non siano duplicati.
- **Clarity**: dashboard progetto; verifica che le sessioni vengano registrate dopo aver accettato Analytics e Clarity.
- **Debug**: in Tracking abilita **Modalità debug** per log in console (solo in sviluppo); per errori server-side controlla i log PHP (`[FP Resv GA4]`, `[FP Resv Meta]`).

---

## Documentazione correlata

- [TRACKING-MAP.md](../api/TRACKING-MAP.md) – Eventi dataLayer, GA4, Ads, Meta, Clarity e consensi.
- [SERVER-SIDE-TRACKING.md](../api/SERVER-SIDE-TRACKING.md) – Dettaglio su GA4 Measurement Protocol e Meta Conversions API.
