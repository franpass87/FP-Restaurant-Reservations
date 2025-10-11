# 🔍 Istruzioni Debug: Agenda mostra "Nessuna prenotazione"

## Problema

Sei sulla data di ieri nell'agenda ma non vedi le 2 prenotazioni che dovrebbero essere presenti.

## ✅ Soluzione Rapida: Script di Debug

### Passo 1: Esegui lo script di debug

1. **Assicurati di essere loggato come amministratore WordPress**

2. **Apri nel browser**:
   ```
   https://[tuosito.com]/wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
   ```
   
   Sostituisci `[tuosito.com]` con il tuo dominio reale.

3. **Lo script ti mostrerà**:
   - 📊 Quante prenotazioni ci sono nel database
   - 📅 Prenotazioni di ieri in dettaglio
   - 🔌 Test del sistema di caricamento dati (Repository)
   - 🌐 Test dell'endpoint API REST
   - 👤 Verifica permessi utente
   - 💡 Diagnosi e suggerimenti

### Passo 2: Leggi i risultati

Lo script identificherà automaticamente il problema tra:

#### Scenario A: Database vuoto
```
❌ DATABASE VUOTO
Totale prenotazioni: 0
```
**Causa**: Le prenotazioni non sono state salvate.

**Soluzione**: 
- Controlla che il form di prenotazione funzioni
- Verifica errori PHP nei log
- Prova a creare una prenotazione di test dall'agenda admin

#### Scenario B: Nessuna prenotazione per ieri
```
⚠️ NESSUNA PRENOTAZIONE PER IERI
Totale prenotazioni: 15
Prenotazioni per ieri: 0
```
**Causa**: Le prenotazioni sono per altre date, non per ieri.

**Soluzione**:
- Guarda la tabella "Prenotazioni per data" nello script
- Trova la data corretta
- Selezionala nell'agenda

#### Scenario C: Problema nel Repository
```
❌ PROBLEMA NEL REPOSITORY
Database: 2 prenotazioni per ieri
Repository: 0 prenotazioni restituite
```
**Causa**: Bug nella query SQL che carica i dati.

**Soluzione**: Vedi sezione "Fix Repository" sotto

#### Scenario D: Problema nell'API
```
❌ PROBLEMA NELL'ENDPOINT API
Repository: 2 prenotazioni
API Response: []
```
**Causa**: Bug nel mapping/trasformazione dati.

**Soluzione**: Vedi sezione "Fix API" sotto

#### Scenario E: Problema nel Frontend
```
✓ DATI CORRETTI
Database: 2 prenotazioni
Repository: 2 prenotazioni
API: 2 prenotazioni
```
**Causa**: Il problema è nel JavaScript del browser.

**Soluzione**: Vedi sezione "Debug JavaScript" sotto

### Passo 3: Elimina lo script
```bash
rm wp-content/plugins/fp-restaurant-reservations/debug-agenda.php
```

## 🔧 Soluzioni per Scenari Specifici

### Fix Repository (Scenario C)

Se il repository non restituisce dati:

1. **Verifica la query SQL**:
   ```sql
   -- Esegui in phpMyAdmin/Adminer
   SELECT r.*, c.first_name, c.last_name, c.email, c.phone, c.lang AS customer_lang 
   FROM wp_fp_reservations r 
   LEFT JOIN wp_fp_customers c ON r.customer_id = c.id 
   WHERE r.date BETWEEN '2025-10-09' AND '2025-10-09'
   ORDER BY r.date ASC, r.time ASC;
   ```

2. **Se la query restituisce risultati**: problema nel codice PHP
   - Controlla `src/Domain/Reservations/Repository.php::findAgendaRange()`
   - Verifica che non ci siano errori nella conversione dati

3. **Se la query è vuota**: problema nei dati
   - Verifica che `customer_id` sia valido
   - Controlla che le date siano nel formato corretto

### Fix API (Scenario D)

Se l'API non restituisce dati:

1. **Apri il file**: `src/Domain/Reservations/AdminREST.php`

2. **Aggiungi debug temporaneo** nel metodo `handleAgenda()` (riga ~360):
   ```php
   // DOPO: $rows = $this->reservations->findAgendaRange(...)
   error_log('Agenda Debug - Rows from DB: ' . count($rows));
   error_log('Agenda Debug - First row: ' . print_r($rows[0] ?? null, true));
   ```

3. **Controlla il log**:
   ```bash
   tail -f wp-content/debug.log
   ```

4. **Ricarica l'agenda** e verifica cosa viene loggato

### Debug JavaScript (Scenario E)

Se i dati arrivano ma non vengono mostrati:

1. **Apri l'agenda** nel browser

2. **Apri DevTools** (F12)

3. **Vai al tab Console** ed esegui:
   ```javascript
   // Verifica data selezionata
   console.log('Data:', document.querySelector('[data-role="date-picker"]').value);
   
   // Verifica configurazione
   console.log('Settings:', window.fpResvAgendaSettings);
   
   // Test API manuale
   fetch(window.fpResvAgendaSettings.restRoot + '/agenda?date=2025-10-10', {
     headers: {
       'X-WP-Nonce': window.fpResvAgendaSettings.nonce,
       'Content-Type': 'application/json'
     },
     credentials: 'same-origin'
   })
   .then(r => r.json())
   .then(data => {
     console.log('✅ API Response:', data);
     console.log('Count:', data.length);
     if (data.length > 0) {
       console.log('First:', data[0]);
     }
   })
   .catch(err => console.error('❌ Error:', err));
   ```

4. **Vai al tab Network**:
   - Filtra per `agenda`
   - Ricarica la pagina (F5)
   - Clicca sulla richiesta `/wp-json/fp-resv/v1/agenda?date=...`
   - Verifica:
     - **Headers** → Request URL → parametro `date` è corretto?
     - **Response** → contiene l'array di prenotazioni?
     - **Status** → è 200 OK?

5. **Possibili fix**:
   ```javascript
   // Se il problema è cache, forza ricaricamento
   location.reload(true); // o Ctrl+Shift+R
   
   // Se il problema è il nonce, ottienine uno nuovo
   // (fai logout/login)
   ```

## 📋 Alternative: Query SQL Dirette

Se non puoi eseguire lo script PHP, usa queste query in phpMyAdmin/Adminer:

```sql
-- 1. Conta prenotazioni
SELECT COUNT(*) FROM wp_fp_reservations;

-- 2. Prenotazioni di ieri
SELECT r.id, r.date, r.time, r.party, r.status,
       c.first_name, c.last_name, c.email
FROM wp_fp_reservations r
LEFT JOIN wp_fp_customers c ON r.customer_id = c.id
WHERE r.date = DATE_SUB(CURDATE(), INTERVAL 1 DAY);

-- 3. Distribuzione per data
SELECT date, COUNT(*) as count
FROM wp_fp_reservations
WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY date
ORDER BY date DESC;

-- 4. Ultime 10 create
SELECT id, date, time, status, party, created_at
FROM wp_fp_reservations
ORDER BY created_at DESC
LIMIT 10;
```

## 🆘 Se il Problema Persiste

### Informazioni da raccogliere:

1. **Screenshot dello script debug** (tutto l'output)

2. **Output delle query SQL**:
   - Quante prenotazioni totali?
   - Quante per ieri?
   - Quali date hanno prenotazioni?

3. **Screenshot DevTools**:
   - Console (eventuali errori)
   - Network tab (richiesta `/agenda` e risposta)

4. **Versione plugin**: Controlla nel file `fp-restaurant-reservations.php`

5. **Configurazione**:
   - Hai plugin di cache attivi?
   - Hai modificato file del plugin?
   - PHP/WordPress/MySQL version?

### Contatta supporto con:
- Tutti i dati sopra
- Messaggio: "Agenda non mostra prenotazioni - ho eseguito script debug"
- Allega screenshot

## 🔒 Sicurezza

**IMPORTANTE**: Dopo aver risolto il problema:

```bash
# Elimina lo script di debug
rm wp-content/plugins/fp-restaurant-reservations/debug-agenda.php

# Se hai modificato file per debug, ripristinali
git checkout src/Domain/Reservations/AdminREST.php

# Svuota cache
# Se usi WP Rocket, W3 Total Cache, ecc., svuota la cache
```

## 📞 Prossimi Passi

1. ✅ Esegui `debug-agenda.php`
2. ✅ Identifica lo scenario (A/B/C/D/E)
3. ✅ Applica la soluzione corrispondente
4. ✅ Ricarica l'agenda
5. ✅ Se risolto: elimina `debug-agenda.php`
6. ✅ Se non risolto: raccogli dati e contatta supporto

---

**Ultima modifica**: 2025-10-11  
**Branch**: `cursor/no-booking-found-handler-2d10`
