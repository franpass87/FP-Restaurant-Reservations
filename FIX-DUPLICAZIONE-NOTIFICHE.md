# Fix: Duplicazione Notifiche Email e Eventi Brevo

## Problema Rilevato

Il sistema inviava **notifiche duplicate** in due scenari:

### 1. Email Duplicate al Webmaster
Se lo stesso indirizzo email era configurato sia in **Email ristorante** che in **Email webmaster**, quella persona riceveva **due email diverse** per la stessa prenotazione:
- Una email come "notifica ristorante" 
- Una email come "notifica webmaster"

Questo faceva sembrare che ci fossero due prenotazioni diverse, anche se avevano lo stesso ID.

### 2. Eventi Brevo Duplicati
Il sistema poteva inviare **due volte lo stesso evento** a Brevo (`email_confirmation`) perché non c'era un controllo anti-duplicazione nel metodo `sendBrevoConfirmationEvent()`.

---

## Soluzioni Implementate

### ✅ 1. Deduplica Email Staff (Restaurant + Webmaster)

**File modificato:** `src/Domain/Reservations/Service.php`

**Modifica:** Alla riga 606, è stata aggiunta una deduplica che rimuove dai destinatari webmaster quelli già presenti nella lista restaurant:

```php
// Deduplica: rimuovi dai destinatari webmaster quelli già presenti in restaurant
// per evitare di inviare due email alla stessa persona
$webmasterRecipients = array_values(array_diff($webmasterRecipients, $restaurantRecipients));
```

**Risultato:** Se `admin@example.com` è configurato sia in "Email ristorante" che in "Email webmaster", riceverà **solo 1 email** (quella come ristorante).

---

### ✅ 2. Protezione Anti-Duplicazione Eventi Brevo

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

1. **Nessuna confusione** - Lo staff non riceve più email duplicate per la stessa prenotazione
2. **Efficienza** - Meno email inviate, meno carico sul sistema
3. **Brevo pulito** - Nessun evento duplicato che potrebbe triggare automazioni multiple
4. **Tracciabilità** - Tutti gli eventi Brevo sono loggati nel database per audit

---

## Files Modificati

- ✅ `src/Domain/Reservations/Service.php`
- ✅ `src/Core/Plugin.php`
- ✅ `tests/Integration/Reservations/ServiceTest.php`

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
