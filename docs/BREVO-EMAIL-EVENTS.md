# Eventi Brevo per Automazioni Email

## Panoramica

Il sistema ora invia eventi a Brevo per attivare le automazioni email quando Brevo è configurato come canale di invio per le email ai clienti.

## Eventi Implementati

### 1. `email_confirmation` - Email di Conferma
**Quando viene inviato:** Quando viene creata una nuova prenotazione e Brevo è configurato per gestire le email di conferma.

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
- **Azione:** Invia email usando i dati da `properties.reservation.*` e `properties.contact.*`
- **Variabili disponibili:**
  - `{{params.reservation.id}}`
  - `{{params.reservation.date}}`
  - `{{params.reservation.time}}`
  - `{{params.reservation.party}}`
  - `{{params.reservation.manage_url}}`
  - `{{params.contact.first_name}}`
  - `{{params.contact.last_name}}`
  - `{{params.meta.language}}`

#### 2. Automazione Email Reminder
- **Trigger:** Evento "email_reminder"
- **Azione:** Invia email di promemoria
- Stesse variabili della conferma

#### 3. Automazione Email Review
- **Trigger:** Evento "email_review"
- **Azione:** Invia email di richiesta recensione
- **Variabili aggiuntive:**
  - `{{params.meta.review_url}}` - URL della pagina recensioni (es. Google)

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

- `reservation_confirmed` - Inviato quando lo stato diventa "confirmed" (sempre)
- `reservation_visited` - Inviato quando lo stato diventa "visited" (sempre)
- `email_confirmation` - Inviato SOLO quando Brevo deve gestire l'email di conferma
- `email_reminder` - Inviato SOLO quando Brevo deve gestire l'email di reminder
- `email_review` - Inviato SOLO quando Brevo deve gestire l'email di review

Questo permette di:
- Separare la logica di stato dalla logica di invio email
- Usare Brevo per le email ma altri sistemi per gli eventi di stato
- Avere maggiore controllo su quando e come vengono inviate le email

## File Modificati

- `src/Domain/Reservations/Service.php` - Aggiunto supporto eventi email_confirmation
- `src/Domain/Notifications/Manager.php` - Aggiunto supporto eventi email_reminder e email_review
- `src/Core/Plugin.php` - Iniettato BrevoClient nei servizi
