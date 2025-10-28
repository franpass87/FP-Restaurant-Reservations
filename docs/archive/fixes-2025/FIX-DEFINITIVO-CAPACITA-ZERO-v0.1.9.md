# ✅ FIX DEFINITIVO "Completamente Prenotato" - v0.1.9

**Data:** 2025-10-09  
**Versione:** 0.1.9  
**Branch:** cursor/check-booking-availability-and-fix-issues-86e9

---

## 🎯 PROBLEMA IDENTIFICATO

Il sistema mostrava **"Completamente prenotato"** anche quando NON c'erano prenotazioni.

### Causa ROOT: Capacity = 0 quando tavoli disabilitati

**Scenario dell'utente:**
- ✅ Orari di servizio configurati correttamente
- ✅ Meal plan configurato correttamente  
- ❌ Tavoli DISABILITATI (l'utente non usa la gestione tavoli)
- ❌ Sale NON configurate

**Cosa succedeva nel backend:**

```php
// 1. Carica sale e tavoli
$rooms = $this->loadRooms($roomId);  // → [] (vuoto!)
$tables = $this->loadTables($roomId); // → [] (vuoto!)

// 2. Aggrega capacità
$roomCapacities = $this->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
// → [] (vuoto perché non ci sono sale né tavoli!)

// 3. Risolve capacità per lo slot
$baseCapacity = $this->resolveCapacityForScope($roomCapacities, null, false);
// → 0 (perché $roomCapacities è vuoto!)

// 4. Determina status
if ($capacity <= 0 || $capacity < $party) {
    return 'full';  // ← SEMPRE 'full' perché capacity è 0!
}
```

**Risultato:** TUTTI gli slot → `status: 'full'` → "Completamente prenotato"

---

## ✅ SOLUZIONE IMPLEMENTATA

### Fix 1: Fallback a capacità virtuale (PRINCIPALE)

**File:** `src/Domain/Reservations/Availability.php`

**Modificato:** `aggregateRoomCapacities()`

```php
private function aggregateRoomCapacities(array $rooms, array $tables, int $defaultRoomCap): array
{
    $capacities = [];
    
    foreach ($rooms as $room) {
        $capacities[$room['id']] = [
            'capacity'       => max($room['capacity'], $defaultRoomCap),
            'table_capacity' => 0,
        ];
    }

    foreach ($tables as $table) {
        // ... gestione tavoli
    }

    // ✅ FIX: Se non ci sono sale né tavoli, crea una sala virtuale
    // con capacità di default. Questo previene capacity = 0 quando
    // i tavoli sono disabilitati.
    if (empty($capacities)) {
        $capacities[0] = [
            'capacity'       => $defaultRoomCap,
            'table_capacity' => 0,
        ];
    }

    return $capacities;
}
```

**Cosa fa:**
- Se non ci sono sale né tavoli configurati
- Crea una "sala virtuale" con ID 0
- Usa `defaultRoomCap` (default: 40, configurabile)
- Così `resolveCapacityForScope` ritorna la capacità di default invece di 0

---

### Fix 2: Miglioramento UX - Stato `unavailable` (BONUS)

**File:** `assets/js/fe/availability.js` + `onepage.js`

Aggiunto nuovo stato `unavailable` per distinguere:
- **`full`** = Prenotazioni occupate
- **`unavailable`** = Orari non configurati

**Messaggi migliorati:**
- Prima: "Completamente prenotato" (sempre)
- Ora: "Completamente prenotato" (solo se veramente pieno)
- Ora: "Non disponibile per questa data" (se schedule vuoto)

---

## 📊 COSA È CAMBIATO

### File Backend (FIX PRINCIPALE)

| File | Modifica |
|------|----------|
| `src/Domain/Reservations/Availability.php` | Aggiunto fallback sala virtuale in `aggregateRoomCapacities()` |

### File Frontend (BONUS UX)

| File | Modifica |
|------|----------|
| `assets/js/fe/availability.js` | Gestione stato `unavailable` |
| `assets/js/fe/onepage.js` | UI per stato `unavailable` |
| `assets/dist/fe/onepage.esm.js` | Ricompilato |
| `assets/dist/fe/onepage.iife.js` | Ricompilato |

### Versione

| File | Versione |
|------|----------|
| `fp-restaurant-reservations.php` | 0.1.8 → **0.1.9** |
| `src/Core/Plugin.php` | 0.1.8 → **0.1.9** |
| `readme.txt` | 0.1.8 → **0.1.9** |

---

## 🧪 COME TESTARE

### Test 1: Verifica Fix Principale (Tavoli Disabilitati)

**Setup:**
- ❌ Nessuna sala configurata
- ❌ Nessun tavolo configurato (disabilitati)
- ✅ Orari servizio configurati
- ✅ Meal plan configurato

**Test:**
1. Seleziona data: Domani
2. Seleziona persone: 2
3. Seleziona meal: "Cena"

**PRIMA (Bug):**
- ❌ "Completamente prenotato"
- ❌ Nessuno slot disponibile

**DOPO (Corretto):**
- ✅ Slot disponibili
- ✅ Usa capacità di default (40 persone)
- ✅ Sistema funziona normalmente

### Test 2: Verifica con Prenotazioni Esistenti

**Setup:**
- Aggiungi una prenotazione per domani ore 20:00

**Test:**
- Seleziona domani, 2 persone, "Cena"

**ATTESO:**
- ✅ Mostra slot disponibili
- ⚠️  Slot 20:00 potrebbe essere "limited" o "full" (corretto!)
- ✅ Altri slot disponibili

### Test 3: Verifica Capacità Massima

**Setup:**
- Capacità di default: 40 persone

**Test:**
- Seleziona 50 persone

**ATTESO:**
- ❌ "Completamente prenotato" (corretto! Party > Capacity)

---

## 🚀 DEPLOY

### Opzione A: Riattiva Plugin (Raccomandato)

```bash
# 1. Carica ZIP v0.1.9
# 2. WordPress Admin > Plugin > Disattiva
# 3. Riattiva il plugin
# 4. Hard refresh browser (Ctrl+Shift+R)
```

### Opzione B: Automatico

Il sistema `AutoCacheBuster` rileverà v0.1.9 e forzerà il refresh.

---

## 📋 CONFIGURAZIONE CONSIGLIATA

### Se NON usi i tavoli:

1. **Imposta capacità di default:**
   - WordPress Admin > Prenotazioni > Impostazioni
   - Cerca "Capacità sala predefinita" o "default_room_capacity"
   - Imposta il numero massimo di coperti (es: 40, 60, 80)

2. **Verifica orari servizio:**
   - Configura orari per ogni giorno
   - Formato: `lun=19:00-23:00`

3. **Configura meal plan (opzionale):**
   - Se usi pranzo/cena separati
   - Imposta orari specifici per ogni meal

### Se usi i tavoli:

Il fix non impatta la gestione tavoli. Continuerà a funzionare come prima.

---

## ✅ CHECKLIST VERIFICA

### Post-Deploy

- [ ] Versione browser: `onepage.iife.js?ver=0.1.9.XXXXX`
- [ ] Console browser: Nessun errore
- [ ] Test senza tavoli: Slot disponibili ✅
- [ ] Test con prenotazioni: Comportamento corretto
- [ ] Test capacità massima: Messaggio corretto

### Configurazione

- [ ] Capacità di default impostata (se non usi tavoli)
- [ ] Orari servizio configurati
- [ ] Meal plan configurato (se necessario)

---

## 🐛 TROUBLESHOOTING

### Problema: Continua a dire "Completamente prenotato"

**Verifica:**

1. **Capacità di default impostata?**
   ```
   Admin > Prenotazioni > Impostazioni
   Cerca: "Capacità sala predefinita"
   Valore consigliato: 40-60
   ```

2. **Orari servizio configurati?**
   ```
   Admin > Prenotazioni > Impostazioni
   Cerca: "Orari servizio" 
   Esempio: lun=19:00-23:00
   ```

3. **Versione caricata?**
   ```
   DevTools > Network
   Cerca: onepage.iife.js
   Verifica: ver=0.1.9.XXXXX
   ```

4. **Hard refresh?**
   ```
   Ctrl + Shift + R (Win/Linux)
   Cmd + Shift + R (Mac)
   ```

---

## 📊 CONFRONTO PRIMA/DOPO

### PRIMA (Bug) ❌

```
Setup:
- Tavoli disabilitati
- Nessuna sala configurata
- Orari servizio OK
- Meal plan OK

Comportamento:
1. aggregateRoomCapacities() → []
2. resolveCapacityForScope() → 0
3. determineStatus(0, ...) → 'full'
4. Tutti gli slot → 'full'
5. Frontend → "Completamente prenotato"

Risultato: ❌ Sistema inutilizzabile
```

### DOPO (Corretto) ✅

```
Setup:
- Tavoli disabilitati
- Nessuna sala configurata
- Orari servizio OK
- Meal plan OK
- Capacità default: 40

Comportamento:
1. aggregateRoomCapacities() → [0 => ['capacity' => 40, ...]]
2. resolveCapacityForScope() → 40
3. determineStatus(40, 40, 2) → 'available'
4. Slot → 'available', 'limited', etc.
5. Frontend → Slot disponibili

Risultato: ✅ Sistema funziona perfettamente
```

---

## 🎉 CONCLUSIONE

### Problema Risolto! ✅

Il bug era causato da una logica incompleta nel backend che non gestiva correttamente il caso "nessuna sala + nessun tavolo".

### Fix Applicato ✅

Aggiunto fallback a "sala virtuale" con capacità di default quando non ci sono sale né tavoli configurati.

### Bonus UX ✅

Migliorata la distinzione tra "pieno" e "non configurato" nel frontend.

---

**Preparato da:** Background Agent  
**Data:** 2025-10-09  
**Versione Plugin:** 0.1.9  
**Status:** ✅ FIX DEFINITIVO APPLICATO

---

## 📎 FILE DI SUPPORTO

- `tools/debug-availability.php` - Diagnostica completa
- `tools/test-availability-debug.php` - Test rapido
- `DIAGNOSI-PROBLEMA-CAPACITA.md` - Analisi del problema
