# 🚨 ISTRUZIONI IMMEDIATE - Debug Risposta Vuota

## Cosa Ho Fatto

Ho aggiunto **logging diagnostico dettagliato** per capire esattamente dove si blocca il processo di creazione della prenotazione.

## ⚡ AZIONE IMMEDIATA RICHIESTA

### STEP 1: Attiva i Log (se non già attivo)

Apri il file `wp-config.php` e verifica/aggiungi queste righe:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### STEP 2: Pulisci Cache (IMPORTANTE!)

Il codice PHP modificato potrebbe essere in cache. Fai una di queste:

**Opzione A - Più Semplice**:
1. Vai in WordPress Admin
2. Plugin → FP Restaurant Reservations → **Disattiva**
3. Aspetta 2 secondi
4. Plugin → FP Restaurant Reservations → **Attiva**

**Opzione B - Se hai Opcache**:
```bash
# Da terminale nella root di WordPress
wp cache flush
```

**Opzione C - Manuale**:
1. Rinomina la cartella `fp-restaurant-reservations` in `fp-restaurant-reservations-old`
2. Aspetta 2 secondi
3. Rinomina di nuovo in `fp-restaurant-reservations`

### STEP 3: Riprova a Creare una Prenotazione

1. Apri il Manager: **WordPress Admin → FP Reservations → Manager**
2. Clicca **"Nuova Prenotazione"**
3. Completa tutti e 3 gli step
4. Osserva se compare ancora l'errore "Risposta vuota dal server"

### STEP 4: Leggi i Log

#### Windows (PowerShell):
```powershell
# Nella root di WordPress
Get-Content wp-content/debug.log -Tail 200
```

#### Se hai accesso al file:
Apri `wp-content/debug.log` con un editor di testo e cerca le righe più recenti che contengono:
```
[FP Resv Admin]
```

### STEP 5: Analisi Log

Copia TUTTI i log che iniziano con `[FP Resv Admin]` e guardali attentamente.

#### ✅ SCENARIO 1: Vedi log dettagliati

Se vedi qualcosa tipo:
```
[FP Resv Admin] === CREAZIONE PRENOTAZIONE DAL MANAGER START ===
[FP Resv Admin] Request method: POST
[FP Resv Admin] STEP 1: Estrazione payload...
[FP Resv Admin] STEP 2: Payload estratto...
```

**OTTIMO!** L'endpoint viene chiamato. Dimmi a quale STEP si ferma.

#### ❌ SCENARIO 2: NON vedi nessun log

Se NON vedi **nessun log** con `[FP Resv Admin]`, significa che:
1. L'endpoint non è registrato
2. La cache non è stata pulita
3. Il routing WordPress non funziona

**SOLUZIONE**: Riprova lo STEP 2 (pulisci cache).

#### ⚠️ SCENARIO 3: Vedi errori PHP

Se vedi errori tipo:
```
PHP Fatal error: ...
PHP Warning: ...
```

Copiali TUTTI e inviali.

## 🎯 Info da Raccogliere

Dopo aver provato, dimmi:

1. **Hai visto i log `[FP Resv Admin]`?** (Sì/No)
2. **Se sì, a quale STEP si ferma?** (es: STEP 3, STEP 7, ecc.)
3. **Ci sono errori PHP?** (Copia qui gli errori)
4. **La prenotazione è stata creata comunque?** (Controlla nel database o nella lista prenotazioni)

## 🔧 Test Alternativo Veloce

Se hai poco tempo, usa questo script di test che ho creato:

1. Apri nel browser:
   ```
   http://tuo-sito.local/wp-content/plugins/fp-restaurant-reservations/test-create-reservation-endpoint.php
   ```

2. Clicca "Crea Prenotazione"

3. Guarda i risultati direttamente nella pagina

## 📋 File Modificato

- `src/Domain/Reservations/AdminREST.php` - Aggiunto logging dettagliato

## ⏱️ Tempo Stimato

- **5 minuti** per seguire questi step
- **2 minuti** per raccogliere i log
- **1 minuto** per condividerli

---

**IMPORTANTE**: Una volta raccolti i log, saprò esattamente qual è il problema e potrò applicare il fix definitivo!

