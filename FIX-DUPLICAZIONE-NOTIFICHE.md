# Fix: Duplicazione Notifiche Email e Eventi Brevo

## Problema Rilevato

Il sistema creava **prenotazioni duplicate** con numerazioni diverse (#123, #124) in tre scenari:

### 1. Doppio Submit (Frontend)
- **Doppio click** rapido sul pulsante "Prenota"
- **Retry automatico** in caso di errore 403 (nonce invalido) che poteva creare una seconda prenotazione

### 2. Email Duplicate al Webmaster  
Se lo stesso indirizzo email era configurato sia in **Email ristorante** che in **Email webmaster**, quella persona riceveva **due email diverse** per la stessa prenotazione:
- Una email come "notifica ristorante" 
- Una email come "notifica webmaster"

Questo faceva sembrare che ci fossero due prenotazioni diverse, anche se avevano lo stesso ID.

### 3. Eventi Brevo Duplicati
Il sistema poteva inviare **due volte lo stesso evento** a Brevo (`email_confirmation`) perché non c'era un controllo anti-duplicazione nel metodo `sendBrevoConfirmationEvent()`.

---

## Soluzioni Implementate

### ✅ 1. Idempotenza Lato Server con Request ID

**Problema risolto:** Prevenire la creazione di prenotazioni duplicate quando il frontend fa retry o doppio submit.

**File modificati:**
- `assets/js/fe/onepage.js` (generazione request_id univoco)
- `src/Domain/Reservations/REST.php` (controllo idempotenza)
- `src/Domain/Reservations/Repository.php` (metodo `findByRequestId`)
- `src/Domain/Reservations/Service.php` (salvataggio request_id)
- `src/Core/Migrations.php` (aggiunta campo `request_id`)
- `src/Core/Plugin.php` (dependency injection)

**Come funziona:**

1. **Frontend:** Genera un ID univoco (`request_id`) per ogni tentativo di prenotazione
   ```javascript
   if (!this.state.requestId) {
       this.state.requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
   }
   payload.request_id = this.state.requestId;
   ```

2. **Protezione doppio submit:**
   ```javascript
   // All'inizio di handleSubmit()
   if (this.state.sending) {
       return false; // Blocca submit multipli
   }
   ```

3. **Backend:** Prima di creare una nuova prenotazione, controlla se esiste già con quel `request_id`
   ```php
   $existing = $this->repository->findByRequestId($requestId);
   if ($existing !== null) {
       // Restituisci la prenotazione esistente invece di crearne una nuova
       return $existing;
   }
   ```

4. **Retry sicuro:** Se c'è un retry (es. per errore 403), usa lo stesso `request_id`, quindi il server riconosce la richiesta duplicata e restituisce la stessa prenotazione

**Risultato:** Anche con retry multipli o doppi click, viene creata **una sola prenotazione**.

---

### ✅ 2. Deduplica Email Staff (Restaurant + Webmaster)

**File modificato:** `src/Domain/Reservations/Service.php`

**Modifica:** Alla riga 606, è stata aggiunta una deduplica che rimuove dai destinatari webmaster quelli già presenti nella lista restaurant:

```php
// Deduplica: rimuovi dai destinatari webmaster quelli già presenti in restaurant
// per evitare di inviare due email alla stessa persona
$webmasterRecipients = array_values(array_diff($webmasterRecipients, $restaurantRecipients));
```

**Risultato:** Se `admin@example.com` è configurato sia in "Email ristorante" che in "Email webmaster", riceverà **solo 1 email** (quella come ristorante).

---

### ✅ 3. Protezione Anti-Duplicazione Eventi Brevo

**File modificati:**
- `src/Domain/Reservations/Service.php` (costruttore + metodo `sendBrevoConfirmationEvent`)
- `src/Core/Plugin.php` (dependency injection)
- `tests/Integration/Reservations/ServiceTest.php` (test aggiornati)

**Modifiche:**

1. **Aggiunto `Brevo\Repository` come dipendenza opzionale** al costruttore di `Service`
2. **Controllo anti-duplicazione** prima di inviare l'evento:
   ```php
   // Controlla se l'evento è già stato inviato con successo per evitare duplicati
   if ($this->brevoRepository !== null && $this->brevoRepository->hasSuccessfulLog($reservationId, 'email_confirmation')) {
       Logging::log('brevo', 'Evento email_confirmation già inviato, skip per evitare duplicati', [
           'reservation_id' => $reservationId,
           'email'          => $email,
       ]);
       return;
   }
   ```
3. **Logging dell'evento** nel repository Brevo dopo l'invio per tracciare gli eventi già inviati

**Risultato:** L'evento `email_confirmation` viene inviato **una sola volta** per prenotazione, anche se il metodo viene chiamato più volte.

---

## Test

### Test Email Duplicate
È stato aggiunto il test `testCreateReservationDeduplicatesStaffEmails()` in `ServiceTest.php` che verifica:
- Se `admin@example.test` è in entrambe le liste (restaurant + webmaster)
- Riceve **solo 1 email** (quella restaurant)
- L'email webmaster viene inviata solo a `tech@example.test` (non duplicato)

### Test Eventi Brevo
Il sistema ora:
- Controlla se l'evento è già stato loggato con successo
- Skip l'invio se già presente
- Logga ogni invio per tracciabilità

---

## Benefici

1. **❌ Zero prenotazioni duplicate** - Il sistema è completamente idempotente
2. **✅ Retry sicuri** - I retry automatici non creano duplicati
3. **✅ Doppio click safe** - L'utente può cliccare più volte senza problemi  
4. **✅ Nessuna confusione** - Lo staff non riceve più email duplicate per la stessa prenotazione
5. **✅ Efficienza** - Meno email inviate, meno carico sul sistema
6. **✅ Brevo pulito** - Nessun evento duplicato che potrebbe triggare automazioni multiple
7. **✅ Tracciabilità** - Tutti gli eventi Brevo e request_id sono loggati nel database per audit

---

## Files Modificati

### Idempotenza (Request ID)
- ✅ `assets/js/fe/onepage.js` - Generazione request_id e protezione doppio submit
- ✅ `src/Domain/Reservations/REST.php` - Controllo idempotenza prima di create()
- ✅ `src/Domain/Reservations/Repository.php` - Metodo findByRequestId()
- ✅ `src/Domain/Reservations/Service.php` - Salvataggio request_id
- ✅ `src/Core/Migrations.php` - Campo request_id nella tabella + indice
- ✅ `src/Core/Plugin.php` - Dependency injection

### Email e Eventi
- ✅ `src/Domain/Reservations/Service.php` - Deduplica email + protezione eventi Brevo
- ✅ `tests/Integration/Reservations/ServiceTest.php` - Test deduplica email

---

## Note Tecniche

### Priorità di Invio Email Staff
Se un indirizzo è presente in entrambe le liste:
1. ✅ Viene **incluso** nelle email "restaurant"
2. ❌ Viene **escluso** dalle email "webmaster"

Questo perché l'email "restaurant" contiene informazioni più pertinenti per la gestione operativa del ristorante.

### Logging Eventi Brevo
Il sistema usa la tabella `wp_fp_brevo_log` per tracciare:
- Quale evento è stato inviato
- Per quale prenotazione
- Con quale esito (success/error)

Il controllo `hasSuccessfulLog()` verifica se esiste già un record con `status = 'success'` per quella combinazione `(reservation_id, action)`.

### Idempotenza Request ID
La chiave dell'idempotenza è il **request_id** univoco:

1. **Generazione Frontend:** `req_{timestamp}_{random}` - univoco per ogni tentativo
2. **Persistenza:** Mantenuto in `this.state.requestId` durante retry
3. **Reset:** Azzerato solo dopo successo confermato
4. **Backend:** Salvato nella tabella `wp_fp_reservations.request_id` con indice
5. **Lookup veloce:** Query `WHERE request_id = ?` prima di ogni insert
6. **Risposta idempotente:** Header `X-FP-Resv-Idempotent: true` se richiesta duplicata

Questo garantisce che retry multipli (anche da client diversi con lo stesso request_id) producano **esattamente una prenotazione**.
