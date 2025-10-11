# Eventi Brevo per Automazioni Email

## 🎯 Panoramica

Il sistema ora invia eventi a Brevo per attivare le automazioni email quando Brevo è configurato come canale di invio per le email ai clienti.

---

## ⚙️ Come Funziona: Plugin vs Brevo

### 📮 CANALE = PLUGIN (Sistema Interno)

```
┌─────────────────┐
│  Prenotazione   │
│    Creata       │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Sistema WordPress                  │
│                                     │
│  ✅ Usa template dal backend        │
│  ✅ Usa logo configurato            │
│  ✅ Usa header/footer configurati   │
│  ✅ Usa email mittente configurata  │
│  ✅ Invia email direttamente        │
└─────────────────────────────────────┘
         │
         ▼
   📧 Email inviata
```

**Configurazione necessaria:**
- ✏️ Logo in "Preferenze di invio"
- ✏️ Header/Footer template
- ✏️ Email mittente
- ✏️ Nome mittente
- ✏️ Template email nel backend

---

### 🚀 CANALE = BREVO (Automazioni)

```
┌─────────────────┐
│  Prenotazione   │
│    Creata       │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Sistema WordPress                  │
│                                     │
│  ❌ NON usa template backend        │
│  ❌ NON usa logo backend            │
│  ❌ NON usa preferenze backend      │
│  ✅ Invia SOLO evento a Brevo       │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  🎨 BREVO                           │
│                                     │
│  ✅ Riceve evento                   │
│  ✅ Attiva automazione              │
│  ✅ Usa template Brevo              │
│  ✅ Usa logo Brevo                  │
│  ✅ Usa mittente Brevo              │
│  ✅ Invia email                     │
└─────────────────────────────────────┘
         │
         ▼
   📧 Email inviata
```

**Configurazione necessaria:**
- ✏️ API Key Brevo nel backend WordPress
- ✏️ Selezionare "Brevo" come canale
- ✏️ **Template email in Brevo**
- ✏️ **Logo in Brevo**
- ✏️ **Mittente in Brevo**
- ✏️ **Automazione in Brevo con trigger sull'evento**

---

## ⚠️ IMPORTANTE

> **Quando usi Brevo, le "Preferenze di invio" WordPress NON vengono utilizzate per le email ai clienti!**
> 
> Tutto (logo, template, mittente) deve essere configurato in Brevo.

### 📧 Email ai Clienti vs Email Interne

**Email ai CLIENTI (possono usare Brevo):**
- ✅ Email di Conferma
- ✅ Email di Reminder
- ✅ Email di Review

**Email INTERNE Staff/Webmaster (SEMPRE WordPress):**
- 🔔 Notifica allo Staff (restaurant_emails)
- 🔔 Notifica al Webmaster (webmaster_emails)
- ❌ NON passano MAI da Brevo
- ✅ Usano sempre i template e le impostazioni WordPress
- ✅ Invio immediato e affidabile

## Eventi Implementati

### 1. `email_confirmation` - Email di Conferma
**Quando viene inviato:** Quando viene creata una nuova prenotazione e Brevo è configurato per gestire le email di conferma.

**Payload inviato a Brevo (API v3):**
```json
{
  "event_name": "email_confirmation",
  "identifiers": {
    "email_id": "cliente@example.com"
  },
  "contact_properties": {
    "FIRSTNAME": "Mario",
    "LASTNAME": "Rossi",
    "PHONE": "+39123456789",
    "MARKETING_CONSENT": true
  },
  "event_properties": {
    "reservation": {
      "id": 123,
      "date": "2025-10-15",
      "time": "20:00",
      "party": 4,
      "status": "confirmed",
      "location": "1",
      "manage_url": "https://example.com/?fp_resv_manage=123&fp_resv_token=..."
    },
    "contact": {
      "first_name": "Mario",
      "last_name": "Rossi",
      "phone": "+39123456789"
    },
    "meta": {
      "language": "it",
      "notes": "...",
      "marketing_consent": true,
      "utm_source": "google",
      "utm_medium": "cpc",
      "value": 100.00,
      "currency": "EUR"
    }
  }
}
```

### 2. `email_reminder` - Email di Promemoria
**Quando viene inviato:** Quando viene schedulato l'invio di un promemoria pre-prenotazione e Brevo è configurato per gestire i reminder.

**Proprietà evento:**
```json
{
  "email": "cliente@example.com",
  "properties": {
    "reservation": {
      "id": 123,
      "date": "2025-10-15",
      "time": "20:00",
      "party": 4,
      "status": "confirmed",
      "location": "1",
      "manage_url": "https://example.com/?fp_resv_manage=123&fp_resv_token=..."
    },
    "contact": {
      "first_name": "Mario",
      "last_name": "Rossi",
      "phone": "+39123456789"
    },
    "meta": {
      "language": "it"
    }
  }
}
```

### 3. `email_review` - Email di Richiesta Recensione
**Quando viene inviato:** Quando viene schedulato l'invio di una richiesta recensione post-visita e Brevo è configurato per gestire le review.

**Proprietà evento:**
```json
{
  "email": "cliente@example.com",
  "properties": {
    "reservation": {
      "id": 123,
      "date": "2025-10-15",
      "time": "20:00",
      "party": 4,
      "status": "visited",
      "location": "1",
      "manage_url": "https://example.com/?fp_resv_manage=123&fp_resv_token=..."
    },
    "contact": {
      "first_name": "Mario",
      "last_name": "Rossi",
      "phone": "+39123456789"
    },
    "meta": {
      "language": "it",
      "review_url": "https://g.page/..."
    }
  }
}
```

## Configurazione

### Backend WordPress

Per ogni tipo di email (Conferma, Reminder, Review), puoi scegliere il canale di invio:

1. **Plugin** - Il sistema interno gestisce l'invio delle email
2. **Brevo** - Il sistema invia un evento a Brevo che attiva l'automazione

Quando selezioni "Brevo" come canale:
- L'email NON viene inviata dal sistema interno
- Viene inviato un evento a Brevo con tutti i dati necessari
- Brevo riceve l'evento e attiva l'automazione configurata

### Configurazione Automazioni in Brevo

Per ogni evento devi creare un'automazione in Brevo:

#### 1. Automazione Email Conferma
- **Trigger:** Evento "email_confirmation"
- **Azione:** Invia email usando le proprietà dell'evento
- **Variabili disponibili (event_properties):**
  - `{{event.reservation.id}}`
  - `{{event.reservation.date}}`
  - `{{event.reservation.time}}`
  - `{{event.reservation.party}}`
  - `{{event.reservation.manage_url}}`
  - `{{event.contact.first_name}}`
  - `{{event.contact.last_name}}`
  - `{{event.meta.language}}`
- **Proprietà contatto aggiornate automaticamente:**
  - `{{contact.FIRSTNAME}}`
  - `{{contact.LASTNAME}}`
  - `{{contact.PHONE}}`
  - `{{contact.MARKETING_CONSENT}}`

#### 2. Automazione Email Reminder
- **Trigger:** Evento "email_reminder"
- **Azione:** Invia email di promemoria
- Stesse variabili della conferma

#### 3. Automazione Email Review
- **Trigger:** Evento "email_review"
- **Azione:** Invia email di richiesta recensione
- **Variabili aggiuntive:**
  - `{{event.meta.review_url}}` - URL della pagina recensioni (es. Google)

## Logging

Tutti gli eventi inviati a Brevo vengono registrati nei log del sistema:

```php
Logging::log('brevo', 'Evento email_confirmation inviato a Brevo', [
    'reservation_id' => $reservationId,
    'email'          => $email,
    'success'        => true/false,
    'response'       => [...]
]);
```

Puoi verificare l'invio degli eventi controllando i log nel backend.

## Vantaggi dell'Approccio a Eventi

1. **Flessibilità:** Puoi personalizzare completamente le email in Brevo
2. **A/B Testing:** Usa gli strumenti di Brevo per testare diverse versioni
3. **Analytics:** Traccia aperture, click, conversioni direttamente in Brevo
4. **Multilingua:** Gestisci template diversi per lingua usando `params.meta.language`
5. **Personalizzazione:** Usa tutti i dati della prenotazione per personalizzare le email

## Differenza con Eventi Esistenti

Gli eventi email (`email_confirmation`, `email_reminder`, `email_review`) sono **diversi** dagli eventi di stato esistenti:

- `reservation_confirmed` - Inviato quando lo stato diventa "confirmed" (SOLO se Brevo NON gestisce già le email di conferma)
- `reservation_visited` - Inviato quando lo stato diventa "visited" (sempre)
- `email_confirmation` - Inviato SOLO quando Brevo deve gestire l'email di conferma
- `email_reminder` - Inviato SOLO quando Brevo deve gestire l'email di reminder
- `email_review` - Inviato SOLO quando Brevo deve gestire l'email di review

### ⚠️ Protezione Duplicati Email

Il sistema previene automaticamente l'invio di email duplicate:

- Se Brevo gestisce le email di conferma tramite `email_confirmation`, l'evento `reservation_confirmed` NON viene inviato
- Questo evita che entrambi gli eventi attivino automazioni email in Brevo
- La logica è gestita automaticamente dal sistema

Questo permette di:
- Separare la logica di stato dalla logica di invio email
- Usare Brevo per le email ma altri sistemi per gli eventi di stato
- Avere maggiore controllo su quando e come vengono inviate le email
- **Evitare automaticamente email duplicate**

## File Modificati

- `src/Domain/Brevo/Client.php` - **Aggiornato endpoint a `/v3/events` con nuovo formato payload**
- `src/Domain/Reservations/Service.php` - Aggiunto supporto eventi email_confirmation
- `src/Domain/Notifications/Manager.php` - Aggiunto supporto eventi email_reminder e email_review
- `src/Core/Plugin.php` - Iniettato BrevoClient nei servizi

## API Reference

**Endpoint:** `POST https://api.brevo.com/v3/events`  
**Header:** `api-key: YOUR_API_KEY`  
**Documentazione:** [Brevo Events API](https://developers.brevo.com/reference/createevent)
