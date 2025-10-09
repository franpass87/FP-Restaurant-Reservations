# Guida al Tracking Server-Side

## Panoramica

Il plugin supporta ora il tracking **server-side** per GA4 e Meta, che permette di:
- **Bypassare gli ad blocker** del browser
- **Migliorare la precisione** dei dati di tracciamento
- **Evitare la perdita di dati** dovuta a JavaScript disabilitato o limitazioni del browser
- **Deduplicare eventi** tra client-side e server-side

## Configurazione

### 1. GA4 Measurement Protocol

Per abilitare il tracking server-side GA4:

1. Vai su **Google Analytics 4** → **Admin** → **Data Streams**
2. Seleziona il tuo data stream web
3. Clicca su **Measurement Protocol API secrets**
4. Crea un nuovo **API Secret**
5. Copia il secret generato

Nel plugin WordPress:
1. Vai in **Prenotazioni** → **Impostazioni** → **Tracking**
2. Inserisci il **GA4 Measurement ID** (es. `G-XXXXXXXXXX`)
3. Inserisci il **GA4 API Secret** appena copiato
4. Salva le impostazioni

### 2. Meta Conversions API

Per abilitare il tracking server-side Meta:

1. Vai su **Meta Events Manager** → **Impostazioni**
2. Clicca su **Conversions API**
3. Genera un **Access Token** (assicurati di dargli i permessi necessari)
4. Copia il token generato

Nel plugin WordPress:
1. Vai in **Prenotazioni** → **Impostazioni** → **Tracking**
2. Inserisci il **Meta Pixel ID** (es. `123456789012345`)
3. Inserisci il **Meta Access Token** appena copiato
4. Salva le impostazioni

## Come funziona

### Deduplicazione Eventi

Per evitare la duplicazione degli eventi tra client-side e server-side, il sistema utilizza:

**GA4:**
- Genera un `event_id` univoco per ogni evento
- Lo invia sia lato client (via gtag) che lato server (via Measurement Protocol)
- GA4 deduplica automaticamente gli eventi con lo stesso `event_id`

**Meta:**
- Genera un `event_id` univoco per ogni evento
- Lo invia come `eventID` sia lato client (via fbq) che lato server (via Conversions API)
- Meta deduplica automaticamente gli eventi con lo stesso `eventID`

### Dati Inviati

**GA4 riceve:**
- Nome evento
- Parametri dell'evento (value, currency, reservation_id, etc.)
- Client ID (estratto dal cookie `_ga`)
- Event ID per deduplicazione

**Meta riceve:**
- Nome evento (es. Purchase)
- Custom data (value, currency, contents)
- User data (email, phone, IP, user agent, cookie fbp/fbc) - **hashati con SHA256**
- Event ID per deduplicazione
- Event source URL

### Privacy e Sicurezza

Il sistema rispetta la privacy degli utenti:

1. **Hashing dei dati personali**: Email, telefono e altri dati PII vengono hashati con SHA256 prima dell'invio a Meta
2. **Rispetto del consenso**: Gli eventi server-side vengono inviati solo se l'utente ha dato il consenso
3. **IP detection**: Gestisce correttamente proxy, Cloudflare e load balancer
4. **Timeout**: Le richieste hanno timeout di 5 secondi per non rallentare il sito

## Debug

Per attivare il debug degli invii server-side:

1. Vai in **Prenotazioni** → **Impostazioni** → **Tracking**
2. Abilita **"Debug tracking"**
3. Controlla i log di WordPress per eventuali errori

I log mostreranno:
- `[FP Resv GA4]` per errori GA4
- `[FP Resv Meta]` per errori Meta

## Eventi Tracciati Server-Side

Gli eventi seguenti vengono inviati sia lato client che server-side:

### Prenotazioni
- `reservation_submit` - Prenotazione inviata
- `reservation_confirmed` - Prenotazione confermata (con Purchase per Meta)
- `waitlist_joined` - Iscrizione alla lista d'attesa
- `reservation_payment_required` - Pagamento richiesto

### Eventi con Biglietti
- `event_ticket_purchase` - Acquisto biglietto evento (con Purchase per Meta)

### Purchase Stimato
- `purchase` - Acquisto stimato basato sul prezzo a persona

## Verifica Funzionamento

### GA4
1. Vai su **GA4** → **Reports** → **Realtime**
2. Crea una prenotazione di test
3. Verifica che l'evento appaia nei report in tempo reale
4. Controlla che il parametro `event_id` sia presente

### Meta
1. Vai su **Meta Events Manager** → **Test Events**
2. Inserisci il codice di test nella configurazione (opzionale)
3. Crea una prenotazione di test
4. Verifica che l'evento appaia in Test Events con:
   - Event Name
   - Event ID
   - User data hashati
   - Match Quality Score

### Deduplicazione
Per verificare che la deduplicazione funzioni:
1. Crea una prenotazione
2. In GA4, verifica che l'evento appaia UNA sola volta (non duplicato)
3. In Meta Event Manager, verifica che l'evento abbia "Deduplicated" = Yes

## Troubleshooting

### Gli eventi non vengono inviati

**Verifica:**
- Token configurati correttamente
- Debug abilitato per vedere errori nei log
- Connessione internet dal server WordPress

### Errore "Invalid Access Token" (Meta)

**Soluzione:**
- Rigenera l'Access Token in Meta Events Manager
- Assicurati di aver dato i permessi corretti al token

### Errore "Invalid API Secret" (GA4)

**Soluzione:**
- Verifica che il Measurement ID corrisponda allo stream corretto
- Rigenera l'API Secret in GA4

### Eventi duplicati

**Verifica:**
- L'event_id viene generato correttamente
- La versione del browser supporta l'API di deduplicazione
- Nei log non ci sono errori di timeout

## Note Tecniche

- Le richieste HTTP sono asincrone e non bloccano la risposta al client
- Timeout di 5 secondi per le API esterne
- I dati vengono inviati solo se i token sono configurati
- La mancanza del cookie `_ga` non impedisce l'invio (viene generato un client_id temporaneo)
- I cookie Meta (`_fbp`, `_fbc`) sono opzionali ma migliorano il match quality

## API Reference

### GA4 Measurement Protocol
Endpoint: `https://www.google-analytics.com/mp/collect`

Documentazione: https://developers.google.com/analytics/devguides/collection/protocol/ga4

### Meta Conversions API
Endpoint: `https://graph.facebook.com/v18.0/{pixel_id}/events`

Documentazione: https://developers.facebook.com/docs/marketing-api/conversions-api
