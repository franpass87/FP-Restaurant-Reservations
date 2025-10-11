# Troubleshooting: Email e Eventi Brevo Non Inviati per Prenotazioni Manuali

## Problema

Quando si crea una prenotazione manuale dal backend WordPress (Agenda), non vengono inviate:
- Email di conferma al cliente
- Eventi Brevo per le automazioni
- Notifiche email allo staff

## Causa

Le prenotazioni manuali **utilizzano lo stesso codice** delle prenotazioni normali e **dovrebbero** inviare email/eventi automaticamente. Se non funziona, il problema è nella **configurazione**, non nel codice.

## Diagnosi Rapida

Esegui lo script di debug per identificare il problema:

```bash
# Se hai WP-CLI
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-manual-booking-notifications.php

# Oppure via browser
# Carica il file via FTP e aprilo nel browser con un parametro di sicurezza
```

## Cause Comuni e Soluzioni

### 1. Brevo Non Abilitato

**Sintomo**: Nessuna email viene inviata, né tramite Brevo né tramite il plugin.

**Soluzione**:
1. Vai su **WordPress Admin → FP Reservations → Impostazioni → Brevo**
2. Abilita l'integrazione Brevo
3. Inserisci una **API Key valida** di Brevo
4. Salva le impostazioni

### 2. Canale Email Non Configurato

**Sintomo**: Brevo è abilitato ma gli eventi non vengono inviati.

**Soluzione**:
1. Vai su **WordPress Admin → FP Reservations → Impostazioni → Notifiche**
2. Nella sezione **Email Conferma**, imposta **Canale** = **"Usa Brevo"**
3. Salva le impostazioni

**Nota**: Se vuoi usare il sistema email interno del plugin invece di Brevo, lascia il canale su **"Plugin"**. In questo caso, assicurati che il server WordPress possa inviare email correttamente.

### 3. API Key Brevo Non Valida

**Sintomo**: Brevo è abilitato ma la connessione fallisce.

**Soluzione**:
1. Verifica che l'API Key sia corretta nel tuo account Brevo
2. Copia-incolla l'API Key nelle impostazioni del plugin
3. Assicurati che non ci siano spazi o caratteri extra
4. Testa la connessione creando una prenotazione di prova

### 4. Email Staff Non Configurate

**Sintomo**: Il cliente riceve l'email ma lo staff non riceve notifiche.

**Soluzione**:
1. Vai su **WordPress Admin → FP Reservations → Impostazioni → Notifiche**
2. Configura almeno un indirizzo email in:
   - **Email Ristorante** (per notifiche operative)
   - **Email Webmaster** (per copie di backup)
3. Salva le impostazioni

### 5. Automazioni Brevo Non Configurate

**Sintomo**: Gli eventi arrivano a Brevo ma le email non vengono inviate.

**Soluzione**:
1. Accedi al tuo account Brevo
2. Vai su **Automations**
3. Crea o verifica le automazioni per questi eventi:
   - `email_confirmation` → Email di conferma al cliente
   - `email_reminder` → Promemoria pre-arrivo (opzionale)
   - `email_review` → Richiesta recensione post-visita (opzionale)
4. Assicurati che le automazioni siano **attive**

### 6. Server Email Non Configurato

**Sintomo**: Il canale è impostato su "Plugin" ma le email non vengono inviate.

**Soluzione**:
1. Testa la funzione email di WordPress:
   ```bash
   wp eval 'wp_mail("tuo@email.it", "Test", "Test email WordPress");'
   ```
2. Se il test fallisce, configura un plugin SMTP come:
   - WP Mail SMTP
   - Easy WP SMTP
   - FluentSMTP
3. Dopo la configurazione SMTP, riprova a creare una prenotazione

## Flusso Tecnico (per Sviluppatori)

### Come Funzionano le Notifiche

Quando viene creata una prenotazione (manuale o automatica):

1. **`AdminREST::handleCreateReservation()`** (per prenotazioni manuali) chiama:
   - `Service::create($payload)` ← Stesso metodo delle prenotazioni normali!

2. **`Service::create()`** esegue:
   - Salva la prenotazione nel database
   - Chiama `sendCustomerEmail()` → Invia email/evento Brevo al cliente
   - Chiama `sendStaffNotifications()` → Invia email allo staff
   - Triggera `do_action('fp_resv_reservation_created', ...)` → Attiva i webhook

3. **`sendCustomerEmail()`** decide cosa fare:
   ```php
   if ($this->notificationSettings->shouldUseBrevo(CHANNEL_CONFIRMATION)) {
       // Invia SOLO evento a Brevo
       $this->sendBrevoConfirmationEvent(...);
       return;
   }
   
   if ($this->notificationSettings->shouldUsePlugin(CHANNEL_CONFIRMATION)) {
       // Invia email tramite plugin
       $this->mailer->send(...);
   }
   ```

4. **Hook `fp_resv_reservation_created`** attiva:
   - `AutomationService::onReservationCreated()` → Sincronizza contatto e invia eventi Brevo
   - `GoogleCalendarService::handleReservationCreated()` → Crea evento Google Calendar
   - `TrackingManager::handleReservationCreated()` → Invia conversioni GA4/Ads/Meta
   - Altri servizi configurati

### Condizioni per Brevo

**Brevo viene usato quando**:
```php
$brevoEnabled = '1' 
AND $brevoApiKey != '' 
AND $channel == 'brevo'
```

**Plugin viene usato quando**:
```php
$channel == 'plugin' 
OR ($channel == 'brevo' AND NOT $brevoActive)
```

## Verifica Manuale

### 1. Controlla i Log Brevo

```sql
SELECT * FROM wp_fp_brevo_log 
WHERE event_type = 'email_confirmation' 
ORDER BY created_at DESC 
LIMIT 10;
```

**Cosa cercare**:
- `status = 'success'` → Evento inviato correttamente
- `status = 'error'` → Controlla `error_message` per dettagli

### 2. Controlla i Log Email

```sql
SELECT * FROM wp_fp_mail_log 
WHERE channel = 'customer_confirmation' 
ORDER BY created_at DESC 
LIMIT 10;
```

**Cosa cercare**:
- `status = 'sent'` → Email inviata correttamente
- `status = 'failed'` → Controlla `error_message` per dettagli

### 3. Verifica Configurazione DB

```sql
-- Configurazione Brevo
SELECT option_value FROM wp_options WHERE option_name = 'fp_resv_brevo';

-- Configurazione Notifiche
SELECT option_value FROM wp_options WHERE option_name = 'fp_resv_notifications';
```

## Test Completo

Per verificare che tutto funzioni:

1. **Configura le impostazioni** come indicato sopra
2. **Crea una prenotazione manuale** dal backend
3. **Verifica che**:
   - Il cliente riceva l'email di conferma (o l'automazione Brevo parta)
   - Lo staff riceva la notifica email
   - I log non mostrino errori
   - L'evento Brevo sia loggato correttamente (se Brevo è abilitato)

## Script di Debug

Lo script `tools/debug-manual-booking-notifications.php` verifica automaticamente:

- ✅ Configurazione Brevo (abilitato, API Key)
- ✅ Configurazione canali notifiche
- ✅ Email staff configurate
- ✅ Connessione a Brevo
- ✅ Log recenti (Brevo ed email)
- ✅ Raccomandazioni specifiche per il tuo setup

**Output esempio**:

```
=== DEBUG: Notifiche Prenotazioni Manuali ===

✅ Plugin caricato correttamente

--- CONFIGURAZIONE BREVO ---
Brevo abilitato: ✅ SI
API Key Brevo: xkeysib-a1...

--- CONFIGURAZIONE NOTIFICHE ---
Canale email conferma: brevo
Canale email reminder: brevo
Canale email review: brevo

--- EMAIL STAFF ---
Email ristorante: ristorante@example.com
Email webmaster: webmaster@example.com

--- TEST CONNESSIONE BREVO ---
✅ Connessione a Brevo: OK

--- LOG RECENTI (ultimi 5) ---
✅ 2025-10-11 14:23:45 - email_confirmation - success
✅ 2025-10-11 13:15:22 - contact_upsert - success

=== RIEPILOGO E RACCOMANDAZIONI ===

✅ La configurazione sembra corretta!

Se le notifiche ancora non funzionano, verifica:
   - Che le automazioni in Brevo siano configurate e attive
   - Che il server WordPress possa inviare email (test con wp_mail)
   - I log sopra per eventuali errori specifici
```

## Domande Frequenti

### Q: Le email arrivano per le prenotazioni dal frontend, ma non per quelle manuali. Perché?

**R**: Questo non dovrebbe accadere, perché entrambe usano lo stesso codice. Se succede:
1. Controlla i log durante la creazione manuale
2. Verifica che la prenotazione manuale abbia tutti i campi richiesti (email, nome, ecc.)
3. Esegui lo script di debug e controlla gli errori

### Q: Gli eventi arrivano a Brevo ma le email non vengono inviate. Cosa faccio?

**R**: Il problema è nelle automazioni Brevo, non nel plugin:
1. Accedi a Brevo
2. Vai su Automations
3. Verifica che l'automazione per `email_confirmation` sia **attiva**
4. Controlla i trigger e i template configurati
5. Testa l'automazione manualmente da Brevo

### Q: Posso usare sia Brevo che le email del plugin?

**R**: Sì, ma per canali diversi:
- Email conferma → Brevo
- Email reminder → Plugin
- Email review → Brevo
- Notifiche staff → Sempre plugin (non passano mai da Brevo)

### Q: Come faccio a sapere se un evento è stato inviato a Brevo?

**R**: Controlla la tabella `wp_fp_brevo_log`:
```sql
SELECT event_type, status, error_message, created_at 
FROM wp_fp_brevo_log 
WHERE reservation_id = 123 
ORDER BY created_at DESC;
```

Oppure usa lo script di debug che mostra gli ultimi log automaticamente.

## Riferimenti

- [Documentazione Eventi Brevo](./BREVO-EMAIL-EVENTS.md)
- [Documentazione Fix Eventi Brevo](./FIX-BREVO-EVENT-DELIVERY.md)
- [Test Scenarios](./TEST-SCENARIOS.md#4-brevo--dual-list--attributi)
- [API Brevo - Events](https://developers.brevo.com/reference/createevent)

---

**Ultima modifica**: 2025-10-11
**Versione**: 1.0
