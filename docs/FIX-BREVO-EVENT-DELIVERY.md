# Fix: Invio Eventi Brevo per Automazioni Email

## 🐛 Problema Identificato

Gli eventi Brevo per le automazioni email (`email_confirmation`, `email_reminder`, `email_review`) non venivano inviati correttamente a causa di un **header HTTP errato** nell'API call.

### Sintomi
- Le email di conferma/reminder/review non partivano quando Brevo era configurato come canale
- I log di Brevo mostravano errori di autenticazione o 401/403
- Le automazioni in Brevo non venivano triggerate

### Causa Root
Il metodo `Client::sendEvent()` utilizzava l'header `api-key` invece di `ma-key` per le chiamate all'endpoint di Marketing Automation di Brevo.

**Endpoint:** `https://in-automate.brevo.com/api/v2/trackEvent`  
**Header richiesto:** `ma-key` (Marketing Automation Key)  
**Header usato erroneamente:** `api-key`

---

## ✅ Fix Applicato

### File Modificato: `src/Domain/Brevo/Client.php`

```php
// PRIMA (❌ ERRATO)
public function sendEvent(string $event, array $payload): array
{
    // ...
    $response = wp_remote_post(
        'https://in-automate.brevo.com/api/v2/trackEvent',
        [
            'headers' => [
                'api-key' => $this->apiKey(),  // ❌ Header sbagliato
                // ...
            ],
            // ...
        ]
    );
    // ...
}

// DOPO (✅ CORRETTO)
public function sendEvent(string $event, array $payload): array
{
    // ...
    $response = wp_remote_post(
        'https://in-automate.brevo.com/api/v2/trackEvent',
        [
            'headers' => [
                'ma-key' => $this->apiKey(),  // ✅ Header corretto
                // ...
            ],
            // ...
        ]
    );
    // ...
}
```

### File Aggiornato: `tools/debug-brevo-events.php`
Anche lo script di debug è stato aggiornato per usare l'header corretto durante il test di connessione.

---

## 🔍 Come Verificare il Fix

### 1. Prerequisiti
Assicurati che Brevo sia configurato correttamente:

1. **API Key Brevo configurata**: `Impostazioni → Brevo → API key Brevo`
2. **Brevo abilitato**: `Impostazioni → Brevo → Abilita Brevo` = ON
3. **Canali email impostati su Brevo**:
   - `Impostazioni → Notifiche → Email Conferma → Canale` = "Usa Brevo"
   - `Impostazioni → Notifiche → Email Reminder → Canale` = "Usa Brevo" (opzionale)
   - `Impostazioni → Notifiche → Email Review → Canale` = "Usa Brevo" (opzionale)

### 2. Esegui lo Script di Debug

```bash
# Nel container WordPress o tramite wp-cli
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-brevo-events.php
```

Lo script verificherà:
- ✅ Configurazione Brevo (enabled, API key)
- ✅ Configurazione canali email
- ✅ Log recenti di Brevo
- ✅ Test connessione all'endpoint trackEvent
- ✅ Eventuali errori nei log

### 3. Verifica Manuale

1. **Crea una nuova prenotazione** dal frontend
2. **Controlla i log di Brevo** nel database:
   ```sql
   SELECT * FROM wp_fp_brevo_log 
   WHERE action = 'email_confirmation' 
   ORDER BY created_at DESC 
   LIMIT 5;
   ```
3. **Verifica che lo status sia 'success'**
4. **Controlla in Brevo** che l'evento sia arrivato e l'automazione sia partita

### 4. Verifica in Brevo (Dashboard)

1. Vai su **Automation → Event-based automations**
2. Verifica che l'automazione configurata per l'evento (es. `email_confirmation`) sia stata triggerata
3. Controlla i contatti: dovrebbero avere l'evento registrato nella loro timeline

---

## 📋 Checklist Post-Fix

- [ ] Il fix è stato applicato (`ma-key` invece di `api-key`)
- [ ] API Key Brevo è configurata correttamente
- [ ] Brevo è abilitato nelle impostazioni
- [ ] I canali email sono impostati su "Usa Brevo"
- [ ] Le automazioni sono configurate in Brevo per gli eventi:
  - [ ] `email_confirmation` → Trigger per email di conferma
  - [ ] `email_reminder` → Trigger per email di reminder (opzionale)
  - [ ] `email_review` → Trigger per richiesta recensione (opzionale)
- [ ] Test: creare una prenotazione e verificare che l'evento arrivi in Brevo
- [ ] Verificare i log: `wp_fp_brevo_log` deve mostrare status 'success'

---

## 🎯 Cosa Cambia per l'Utente

### Prima del Fix
- ❌ Eventi non inviati a Brevo
- ❌ Automazioni non triggerate
- ❌ Email non inviate (anche se Brevo configurato)
- ❌ Log mostrano errori 401/403

### Dopo il Fix
- ✅ Eventi inviati correttamente a Brevo
- ✅ Automazioni triggerate automaticamente
- ✅ Email inviate tramite Brevo
- ✅ Log mostrano 'success'

---

## 📚 Riferimenti

- [Documentazione API Brevo - Track Events](https://developers.brevo.com/reference/trackevent)
- [Documentazione interna: Eventi Email Brevo](./BREVO-EMAIL-EVENTS.md)
- [Test Scenarios: Brevo](./TEST-SCENARIOS.md#4-brevo--dual-list--attributi)

---

## 🔧 Note Tecniche

### Differenza tra api-key e ma-key

**`api-key`** - Usato per:
- Gestione contatti (`/v3/contacts`)
- Invio email transazionali
- SMTP API
- Altre API REST standard

**`ma-key`** - Usato per:
- **Marketing Automation** (`/api/v2/trackEvent`)
- Eventi personalizzati
- Trigger di automazioni

### Formato Payload Corretto

```json
{
  "event": "email_confirmation",
  "email": "user@example.com",
  "properties": {
    "reservation": {
      "id": 123,
      "date": "2025-10-15",
      "time": "20:00",
      "party": 4,
      "status": "confirmed",
      "location": "1",
      "manage_url": "https://..."
    },
    "contact": {
      "first_name": "Mario",
      "last_name": "Rossi",
      "phone": "+39123456789"
    },
    "meta": {
      "language": "it",
      "notes": "...",
      // ... altri campi
    }
  }
}
```

---

## ⚠️ Troubleshooting

### Gli eventi ancora non arrivano?

1. **Verifica API Key**: Assicurati che la chiave API sia una **Marketing Automation Key** (non solo una API Key standard)
2. **Controlla i log**: `SELECT * FROM wp_fp_brevo_log WHERE status = 'error' ORDER BY created_at DESC LIMIT 10`
3. **Verifica canale**: Controlla che il canale sia impostato su "Brevo" e non "Plugin"
4. **Test manuale**: Usa lo script `tools/debug-brevo-events.php` per testare la connessione
5. **Controlla Brevo**: Verifica che le automazioni siano attive e configurate correttamente

### Errore "401 Unauthorized"
- La API Key non è valida o è scaduta
- Verifica che sia una Marketing Automation Key

### Errore "404 Not Found"
- L'endpoint potrebbe essere cambiato
- Verifica la documentazione Brevo aggiornata

### Eventi arrivano ma automazioni non partono
- Controlla che il nome dell'evento in Brevo corrisponda esattamente (case-sensitive)
- Verifica che l'automazione sia attiva
- Controlla i filtri dell'automazione (es. email, liste, condizioni)
