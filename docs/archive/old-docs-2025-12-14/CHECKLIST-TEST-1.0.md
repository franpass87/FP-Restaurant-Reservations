# 🧪 CHECKLIST TEST VERSO 1.0.0

**Versione:** 0.9.0-rc1  
**Data:** 25 Ottobre 2025  
**Obiettivo:** Verifica completa prima del lancio 1.0.0  

---

## 🎯 **TEST CRITICI (OBBLIGATORI)**

### 1️⃣ **Test Timezone (CRITICO)**
- [ ] **Verifica WordPress Timezone**
  ```php
  echo "Timezone WP: " . wp_timezone_string(); // Deve essere Europe/Rome
  echo "Ora locale: " . current_time('mysql');
  echo "Ora UTC: " . gmdate('Y-m-d H:i:s');
  ```
  **Atteso:** Differenza di 1-2 ore tra locale e UTC

- [ ] **Test Prenotazione alle 23:30**
  1. Apri form frontend
  2. Seleziona oggi alle 23:30
  3. Compila e invia
  4. Verifica database:
     ```sql
     SELECT date, time, created_at 
     FROM wp_fp_reservations 
     ORDER BY id DESC LIMIT 1;
     ```
  **Atteso:** `date: 2025-10-25`, `time: 23:30:00`, `created_at: 2025-10-25 23:30:xx`

- [ ] **Test Manager Backend**
  1. Vai su `/wp-admin/admin.php?page=fp-resv-reservations`
  2. Vista SETTIMANA
  3. Verifica che "Oggi" sia evidenziato correttamente
  4. Verifica che le prenotazioni siano nel giorno giusto

- [ ] **Test Statistiche "Oggi"**
  1. Crea prenotazione alle 22:30
  2. Verifica che appaia nelle statistiche "Oggi"
  3. Verifica che NON appaia nelle statistiche del giorno dopo

---

### 2️⃣ **Test Flusso Prenotazione Completo**
- [ ] **Frontend Form**
  - [ ] Selezione meal plan
  - [ ] Calendario disponibilità (giorni corretti)
  - [ ] Selezione orario
  - [ ] Compilazione dati cliente
  - [ ] Invio prenotazione
  - [ ] Messaggio successo + scroll automatico

- [ ] **Email Automatiche**
  - [ ] Email conferma ricevuta
  - [ ] Email conferma contiene data/ora corretti
  - [ ] Email conferma contiene dettagli prenotazione
  - [ ] Formato data italiano (gg/mm/aaaa)

- [ ] **Manager Backend**
  - [ ] Prenotazione appare in vista settimana
  - [ ] Prenotazione appare in vista giorno
  - [ ] Dettagli prenotazione corretti
  - [ ] Stato prenotazione "pending"

---

### 3️⃣ **Test Integrazioni**

#### **Google Calendar**
- [ ] **Configurazione**
  - [ ] API key configurata
  - [ ] Calendar ID configurato
  - [ ] Test connessione

- [ ] **Sincronizzazione**
  - [ ] Crea prenotazione
  - [ ] Verifica evento creato in Google Calendar
  - [ ] Verifica data/ora corretti
  - [ ] Verifica dettagli evento

#### **Brevo (CRM)**
- [ ] **Configurazione**
  - [ ] API key configurata
  - [ ] Lista contatti configurata
  - [ ] Test connessione

- [ ] **Automazioni**
  - [ ] Crea prenotazione
  - [ ] Verifica contatto aggiunto in Brevo
  - [ ] Verifica campi mappati correttamente
  - [ ] Test email automazioni

#### **Stripe (Pagamenti)**
- [ ] **Configurazione**
  - [ ] Chiavi API configurate
  - [ ] Webhook configurati
  - [ ] Test connessione

- [ ] **Flusso Pagamento**
  - [ ] Crea prenotazione con pagamento
  - [ ] Test pagamento (modalità test)
  - [ ] Verifica transazione in Stripe
  - [ ] Verifica stato prenotazione aggiornato

---

## 🔍 **TEST AVANZATI**

### 4️⃣ **Test Eventi e Biglietti**
- [ ] **Creazione Evento**
  - [ ] Crea evento con biglietti
  - [ ] Configura prezzo e disponibilità
  - [ ] Pubblica evento

- [ ] **Acquisto Biglietti**
  - [ ] Acquista biglietti da frontend
  - [ ] Verifica QR code generato
  - [ ] Verifica email biglietti
  - [ ] Test validazione QR code

### 5️⃣ **Test Multilingua**
- [ ] **WPML/Polylang**
  - [ ] Cambia lingua sito
  - [ ] Verifica form in lingua corretta
  - [ ] Verifica email in lingua corretta
  - [ ] Verifica manager in lingua corretta

### 6️⃣ **Test Performance**
- [ ] **Load Test**
  - [ ] Crea 50+ prenotazioni
  - [ ] Verifica velocità caricamento manager
  - [ ] Verifica velocità export CSV
  - [ ] Monitor memoria PHP

- [ ] **Database Performance**
  - [ ] Verifica query ottimizzate
  - [ ] Verifica indici database
  - [ ] Test con 100+ prenotazioni

### 7️⃣ **Test Export e Report**
- [ ] **Export CSV**
  - [ ] Export prenotazioni
  - [ ] Verifica formato CSV
  - [ ] Verifica date/ora corrette
  - [ ] Verifica encoding UTF-8

- [ ] **Export PDF**
  - [ ] Export singola prenotazione
  - [ ] Verifica layout PDF
  - [ ] Verifica dati corretti

---

## 🛡️ **TEST SICUREZZA**

### 8️⃣ **Test Sicurezza**
- [ ] **Rate Limiting**
  - [ ] Test invio multipli rapidi
  - [ ] Verifica blocco dopo limite
  - [ ] Verifica reset automatico

- [ ] **Validazione Input**
  - [ ] Test XSS (script injection)
  - [ ] Test SQL injection
  - [ ] Test file upload maliziosi

- [ ] **Privacy GDPR**
  - [ ] Test consenso privacy
  - [ ] Test export dati utente
  - [ ] Test cancellazione dati

---

## 🔄 **TEST BACKUP & RESTORE**

### 9️⃣ **Test Backup**
- [ ] **Backup Database**
  - [ ] Crea backup completo
  - [ ] Verifica integrità backup
  - [ ] Test restore su ambiente test

- [ ] **Backup File**
  - [ ] Backup plugin files
  - [ ] Backup uploads
  - [ ] Test restore completo

---

## 📊 **CRITERI DI SUCCESSO**

### ✅ **PASSAGGIO A 1.0.0**
- [ ] **Tutti i test critici** (sezione 1-3) ✅
- [ ] **Almeno 80% test avanzati** (sezione 4-7) ✅
- [ ] **Nessun bug critico** scoperto
- [ ] **Performance accettabili** (< 3s caricamento)
- [ ] **Timezone corretto** in tutti i punti

### ❌ **NON PASSARE A 1.0.0 SE**
- [ ] Timezone ancora sbagliato
- [ ] Email non funzionano
- [ ] Manager backend non carica
- [ ] Integrazioni falliscono
- [ ] Performance degradate
- [ ] Bug critici scoperti

---

## 📝 **REPORT TEST**

### **Template Report:**
```
=== REPORT TEST 0.9.0-rc1 ===
Data: [DATA]
Tester: [NOME]
Ambiente: [URL]

TEST CRITICI:
✅ Timezone: PASS
✅ Flusso Prenotazione: PASS
✅ Email: PASS
✅ Manager: PASS

TEST INTEGRAZIONI:
✅ Google Calendar: PASS/FAIL
✅ Brevo: PASS/FAIL
✅ Stripe: PASS/FAIL

TEST AVANZATI:
✅ Eventi: PASS/FAIL
✅ Multilingua: PASS/FAIL
✅ Performance: PASS/FAIL

BUG TROVATI:
- [Lista bug se presenti]

RACCOMANDAZIONE:
✅ PRONTO PER 1.0.0
❌ RICHIEDE FIX PRIMA DI 1.0.0
```

---

## 🎯 **PROSSIMI PASSI**

### **Se TUTTI i test passano:**
1. ✅ Aggiorna a **1.0.0**
2. ✅ Pubblica su WordPress.org
3. ✅ Annuncia release stabile
4. ✅ Aggiorna documentazione

### **Se alcuni test falliscono:**
1. 🔧 Fix bug trovati
2. 🔄 Rilascia **0.9.1-rc2**
3. 🧪 Ripeti test
4. 🔄 Ciclo fino a 1.0.0

---

**Status:** 🚀 Release Candidate 1  
**Target 1.0.0:** 7-14 giorni  
**Responsabile Test:** [NOME]  
**Deadline:** [DATA]
