# Tracking Map

Questa mappa riassume il flusso end-to-end degli eventi di tracciamento tra dataLayer, GA4, Google Ads, Meta Pixel e Microsoft Clarity per il widget delle prenotazioni FP Restaurant Reservations.

## Panoramica pipeline

1. Il contesto del form (`FormContext`) effettua un push iniziale `reservation_view` nel `DataLayer` server-side, includendo lingua, locale e sede. Il payload viene serializzato nelle variabili di pagina e sarà consumato nel footer per popolare `window.dataLayer` e invocare `fpResvTracking.dispatch`.
2. Lo script frontend `assets/js/fe/onepage.js` orchestra gli eventi di interazione (`reservation_start`, `meal_selected`, `section_unlocked`, `form_valid`, `reservation_submit`, `reservation_confirmed`, `purchase`) e ascolta i trigger delegati `data-fp-resv-event`.
3. Il bootstrap tracking (`Tracking\Manager::generateBootstrapScript`) inizializza `fpResvTracking`, imposta lo stato consenso e, quando consentito, carica GA4/Ads/Meta/Clarity propagando gli eventi verso le rispettive API (`gtag`, `fbq`, `clarity`).

## Gating dei consensi

| Canale       | Condizione perché il tracking parta |
|--------------|--------------------------------------|
| GA4 (gtag)   | Sempre inizializzato, ma rispetta `gtag('consent', 'default', …)`; eventi utili solo con `analytics_storage = granted`. |
| Google Ads   | Eventi `ads` inviati solo se il consenso Ads è `granted`; in caso contrario rimane `denied` nel `gtag` e non vengono elaborate conversioni. |
| Meta Pixel   | Script caricato e `fbq('consent','grant')` solo se il consenso Ads è `granted`. |
| Microsoft Clarity | Script caricato solo se i consensi Analytics **e** Clarity sono `granted`. |

## Tabella eventi

### GA4 / DataLayer

| Evento dataLayer | Evento GA4 | Origine trigger | Parametri principali | Consent richiesto |
|------------------|------------|-----------------|----------------------|-------------------|
| `reservation_view` | `reservation_view` | Costruzione contesto form | `reservation_language`, `reservation_locale`, `reservation_location` | Analytics |
| `reservation_start` | `reservation_start` | Prima interazione focus sul form | `source="form"` | Analytics |
| `meal_selected` | `meal_selected` | Click su pill pasto | `meal_type`, `meal_label` | Analytics |
| `section_unlocked` | `section_unlocked` | Sblocco sezione progressiva | `section` | Analytics |
| `form_valid` | `form_valid` | Tutto il form valido | `timestamp` | Analytics |
| `reservation_submit` | `reservation_submit` (o `reservation_confirmed`/`waitlist_joined`/`reservation_payment_required`) | Submit lato server (hook `fp_resv_reservation_created`) | `reservation_id`, `reservation_status`, `reservation_party`, `reservation_date`, `reservation_time`, `reservation_location`, `value`, `currency` | Analytics |
| `reservation_confirmed` | `reservation_confirmed` | Dispatch JS `fp-resv:reservation:confirmed` o risposta server | `reservation_id`, `party_size`, `meal_type` | Analytics |
| `purchase` | `purchase` | Stimato lato server quando Stripe è OFF | `value`, `currency`, `value_is_estimated`, `meal_type`, `party_size` | Analytics |
| `event_ticket_purchase` | `event_ticket_purchase` | Vendita biglietti evento (`fp_resv_event_booked`) | `items[]`, `value`, `currency` | Analytics |
| `pdf_download_click` | `pdf_download_click` | Click su link PDF (`data-fp-resv-event`) | `trigger`, `href`, `label` | Analytics |
| `waitlist_joined` | `waitlist_joined` | Stato prenotazione in waitlist (backend) | Stessi parametri di `reservation_submit` | Analytics |
| `reservation_payment_required` | `reservation_payment_required` | Prenotazione con pagamento richiesto | Stessi parametri di `reservation_submit` | Analytics |
| `reservation_cancelled` / `reservation_modified` | (solo dataLayer) | Da emettere manualmente con `fp-resv:tracking:push` o custom JS | Dipende dall'implementazione custom | Analytics |

### UI instrumentation (dataLayer only)

| Evento dataLayer | Origine trigger | Parametri principali | Note |
|------------------|-----------------|----------------------|------|
| `ui_latency` | JS frontend (`availability` e `submit`) | `op` (`availability`\|`submit`), `ms` | Misura la latenza client-side per fetch disponibilità e submit ottimistico. |
| `availability_retry` | Controller disponibilità | `attempt` | Emetto su retry progressivi (0.5/1/2s) fino a 3 tentativi. |
| `ui_validation_error` | Blur campo invalidato | `field` (`email`, `phone`, ...) | Traccia errori di validazione lato client. |
| `phone_validation_error` | Blur telefono non valido | `field: 'phone'` | Specifico per normalizzazione telefono E.164. |
| `cta_state_change` | Toggle CTA smart | `enabled` (`true`\|`false`) | Monitor fallback/attivazione CTA principale. |
| `submit_error` | Catch submit REST fallita | `code`, `latency` | Inviato su HTTP 4xx/5xx con latenza client. |

### Google Ads

| Evento | Trigger | Parametri | Consent |
|--------|---------|-----------|---------|
| `conversion` (`ads.name`) per `reservation_confirmed` | Prenotazione confermata lato server | Conversion payload (`value`, `currency`, `transaction_id`) generato da `Ads::conversionPayload` | Ads = granted |
| `conversion` per `event_ticket_purchase` | Vendita biglietti evento | Conversion payload con valore totale | Ads = granted |

### Meta Pixel

| Evento | Trigger | Parametri | Consent |
|--------|---------|-----------|---------|
| `Purchase` | Prenotazione confermata o evento acquistato | `value`, `currency`, `contents` | Ads = granted |

### Microsoft Clarity

| Evento | Trigger | Parametri | Consent |
|--------|---------|-----------|---------|
| Sessione Clarity | Script caricato post-consenso | N/A (strumento proprietario) | Analytics + Clarity = granted |

## Snippet esempi `dataLayer.push`

```js
// Vista iniziale del widget
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
  event: 'reservation_view',
  reservation: {
    language: 'it',
    locale: 'it_IT',
    location: 'default'
  }
});
```

```js
// Prima interazione sul form one-page
window.dataLayer.push({
  event: 'reservation_start',
  source: 'form'
});
```

```js
// Selezione del pasto
window.dataLayer.push({
  event: 'meal_selected',
  meal_type: 'lunch',
  meal_label: 'Pranzo'
});
```

```js
// Sezione sbloccata automaticamente
window.dataLayer.push({
  event: 'section_unlocked',
  section: 'details'
});
```

```js
// Form completato correttamente
window.dataLayer.push({
  event: 'form_valid',
  timestamp: Date.now()
});
```

```js
// Click su CTA Prenota ora
window.dataLayer.push({
  event: 'reservation_submit',
  trigger: 'click',
  party_size: 4,
  meal_type: 'dinner'
});
```

```js
// Conferma ricevuta via evento custom dispatch
window.dataLayer.push({
  event: 'reservation_confirmed',
  reservation_id: 1234,
  party_size: 4,
  meal_type: 'dinner'
});
```

```js
// Purchase stimato se Stripe è disattivo
window.dataLayer.push({
  event: 'purchase',
  value: 112,
  currency: 'EUR',
  value_is_estimated: true,
  meal_type: 'dinner',
  party_size: 4
});
```

```js
// Click su bottone PDF menu tramite attributi data-fp-resv-event
window.dataLayer.push({
  event: 'pdf_download_click',
  trigger: 'click',
  href: 'https://example.com/menu.pdf',
  label: 'Scarica menu'
});
```

```js
// Vendita biglietti evento
window.dataLayer.push({
  event: 'event_ticket_purchase',
  event_meta: {
    event_id: 42,
    tickets: 2
  },
  ga4: {
    name: 'event_ticket_purchase',
    params: {
      value: 90,
      currency: 'EUR'
    }
  }
});
```

```js
// Trigger manuale per annullamento/modifica (via fp-resv:tracking:push)
window.dispatchEvent(new CustomEvent('fp-resv:tracking:push', {
  detail: {
    event: 'reservation_cancelled',
    payload: {
      reservation_id: 1234,
      reason: 'customer_request'
    }
  }
}));
```

## Gap e azioni consigliate

- Gli eventi `reservation_cancelled` e `reservation_modified` sono mappati nel dataset ma non vengono emessi automaticamente: utilizzare `fp-resv:tracking:push` o implementare hook server-side dedicati quando si gestiscono cancellazioni/modifiche.
- Aggiungere test end-to-end (es. Cypress/Playwright) per validare la propagazione dei consensi e la presenza di `dataLayer` in condizioni di consenso negato.
