# 🚀 Guida Rapida: Risolvere Problema Email/Eventi Brevo per Prenotazioni Manuali

## Il Problema

Hai creato una prenotazione manuale dall'Agenda del backend ma:
- ❌ Il cliente non ha ricevuto l'email di conferma
- ❌ Non è stato inviato nessun evento a Brevo
- ❌ Lo staff non ha ricevuto notifiche

## La Soluzione Rapida (5 minuti)

### Step 1: Esegui lo Script di Debug

```bash
wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-manual-booking-notifications.php
```

Lo script ti dirà esattamente cosa non va e come risolverlo.

### Step 2: Controlla la Configurazione

**Se vuoi usare Brevo:**

1. Vai su **WordPress Admin → FP Reservations → Impostazioni → Brevo**
   - ✅ Abilita l'integrazione Brevo
   - ✅ Inserisci l'API Key di Brevo
   - ✅ Salva

2. Vai su **WordPress Admin → FP Reservations → Impostazioni → Notifiche**
   - ✅ Imposta "Canale email conferma" = **"Usa Brevo"**
   - ✅ Salva

3. Vai su **Brevo Dashboard → Automations**
   - ✅ Verifica che l'automazione per `email_confirmation` sia **attiva**

**Se vuoi usare le email del plugin:**

1. Vai su **WordPress Admin → FP Reservations → Impostazioni → Notifiche**
   - ✅ Imposta "Canale email conferma" = **"Plugin"**
   - ✅ Configura gli indirizzi email per lo staff
   - ✅ Salva

2. Verifica che il server WordPress possa inviare email:
   ```bash
   wp eval 'wp_mail("tuo@email.it", "Test", "Test email");'
   ```
   Se fallisce, installa un plugin SMTP (WP Mail SMTP, Easy WP SMTP, etc.)

### Step 3: Prova una Nuova Prenotazione

1. Vai su **WordPress Admin → FP Reservations → Agenda**
2. Crea una nuova prenotazione di test
3. Verifica che:
   - ✅ Il cliente riceva l'email
   - ✅ Lo staff riceva la notifica
   - ✅ Gli eventi siano loggati correttamente

## Problemi Comuni e Soluzioni Immediate

### Problema: "Brevo non è abilitato"

**Soluzione**: Vai su Impostazioni → Brevo e abilita l'integrazione.

### Problema: "API Key Brevo mancante"

**Soluzione**: 
1. Accedi al tuo account Brevo
2. Vai su Settings → API Keys
3. Copia l'API Key (v3)
4. Incollala in WordPress Admin → Impostazioni → Brevo

### Problema: "Canale non impostato su Brevo"

**Soluzione**: Vai su Impostazioni → Notifiche e imposta il canale su "Usa Brevo".

### Problema: "Le automazioni Brevo non partono"

**Soluzione**: 
1. Accedi a Brevo
2. Vai su Automations
3. Verifica che l'automazione sia **attiva** (non in bozza)
4. Controlla che il trigger sia configurato correttamente per l'evento `email_confirmation`

### Problema: "Le email del plugin non vengono inviate"

**Soluzione**:
1. Testa wp_mail con il comando sopra
2. Se fallisce, installa un plugin SMTP
3. Configura SMTP con le credenziali del tuo provider email
4. Riprova

## Cosa Sapere

### ✅ Le Prenotazioni Manuali FUNZIONANO Come Quelle Normali

Il codice è **identico**. Se le email arrivano per le prenotazioni dal frontend ma non per quelle manuali, il problema è nella configurazione, non nel codice.

### 🔍 Come Verificare i Log

**Log Brevo**:
```sql
SELECT * FROM wp_fp_brevo_log 
WHERE event_type = 'email_confirmation' 
ORDER BY created_at DESC 
LIMIT 10;
```

**Log Email**:
```sql
SELECT * FROM wp_fp_mail_log 
WHERE channel = 'customer_confirmation' 
ORDER BY created_at DESC 
LIMIT 10;
```

### 📊 Monitoraggio Real-Time

Dopo ogni prenotazione manuale, controlla:

1. **Database Log**: Vedi se l'evento è stato registrato
2. **Brevo Dashboard**: Vedi se l'evento è arrivato
3. **Email Inbox**: Verifica che l'email sia stata consegnata

## Hai Ancora Problemi?

1. **Leggi la documentazione completa**: `docs/TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md`
2. **Esegui lo script di debug**: Ti dirà esattamente cosa non va
3. **Controlla i log**: Cerca messaggi di errore specifici
4. **Verifica le automazioni Brevo**: Assicurati che siano attive e configurate correttamente

## Link Utili

- 📚 [Documentazione Completa](./docs/TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md)
- 🔧 [Script di Debug](./tools/debug-manual-booking-notifications.php)
- 📖 [Documentazione Eventi Brevo](./docs/BREVO-EMAIL-EVENTS.md)
- 🧪 [Test Scenarios](./docs/TEST-SCENARIOS.md)

---

**Tempo stimato per la risoluzione**: 5-10 minuti  
**Difficoltà**: ⭐ Facile (configurazione) o ⭐⭐ Media (se serve configurare SMTP)

