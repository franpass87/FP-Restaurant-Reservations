# 🔍 DIAGNOSI PROBLEMA "Completamente Prenotato"

## ❓ DOMANDE CHIAVE PER L'UTENTE

Per capire il vero problema, ho bisogno di sapere:

### 1. Configurazione Tavoli
**Hai configurato i tavoli fisici nel sistema?**
- Sì → Quanti tavoli hai e qual è la loro capacità?
- No → Usi solo la capacità della sala?

**Dove controllare:**
- WordPress Admin > Prenotazioni > Tavoli

### 2. Capacità Sala
**Hai configurato la capacità della sala?**
- Impostazioni > Sale
- Oppure: Impostazioni Generali > Capacità di default

### 3. Test Specifico
**Quando provi a prenotare:**
- Quante persone selezioni? (2, 4, 6, 8, etc.)
- Per quale meal? (pranzo, cena, etc.)
- Per quale data?

### 4. Prenotazioni Esistenti
**Ci sono prenotazioni attive per quella data/ora?**
- WordPress Admin > Prenotazioni > Lista prenotazioni

---

## 🔍 POSSIBILI CAUSE IDENTIFICATE

### Causa 1: Tavoli NON configurati + Capacità sala = 0
**Se:**
- Non hai tavoli configurati
- E la capacità della sala è 0 o non impostata
- **ALLORA:** Capacity = 0 → Tutti gli slot diventano 'full'

**Soluzione:**
- Configura i tavoli OPPURE
- Imposta capacità sala/default

### Causa 2: Capacità insufficiente per il party
**Se:**
- Party richiesto = 8 persone
- Capacità totale disponibile = 4 persone
- **ALLORA:** `capacity < party` → Status = 'full'

**Soluzione:**
- Aggiungi più tavoli
- Aumenta capacità sala
- Oppure è corretto che sia 'full' per quel party!

### Causa 3: Tavoli tutti occupati (anche se senza prenotazioni)
**Se:**
- Tavoli configurati
- Ma qualcosa li marca come "occupati" 
- **ALLORA:** availableTables = [] → Capacity = 0

**Soluzione:**
- Verifica chiusure programmate
- Verifica se ci sono prenotazioni "nascoste"

### Causa 4: Logica hasTables problematica
**Il sistema decide:**
```php
$hasPhysicalTables = $availableTables !== [];

if ($hasPhysicalTables) {
    // Usa capacità TAVOLI
} else {
    // Usa capacità SALA
}
```

**Problema:** Se hai tavoli configurati MA unavailable (es: chiusi), il sistema usa table_capacity = 0 invece di room capacity.

---

## 🔧 SOLUZIONE POSSIBILE

Ho trovato un potenziale bug nella logica! Guarda questo:

```php
// Linea 266
$baseCapacity = $this->resolveCapacityForScope($roomCapacities, $roomId, $hasPhysicalTables);

// Linea 267-272
$allowedCapacity = $this->applyCapacityReductions(
    $baseCapacity,
    $availableTables,  // ← Potrebbe essere vuoto!
    0,
    $closureEffect['capacity_percent']
);
```

In `applyCapacityReductions`:
```php
$tableCapacity = array_sum(...);  // Se $availableTables è [], questo è 0
$capacity = $tables === [] ? $baseCapacity : $tableCapacity;
```

**Il bug:**
- Se `$availableTables` è vuoto (tavoli occupati o chiusi)
- `tableCapacity` = 0
- Ma `$tables === []` è false (perché array vuoto !== array inesistente)
- Quindi usa `tableCapacity` = 0 invece di fallback a `baseCapacity`!

---

## ✅ FIX PROPOSTO

Modificare la logica in `applyCapacityReductions` per usare `baseCapacity` quando `tableCapacity` è 0:

```php
private function applyCapacityReductions(int $baseCapacity, array $tables, int $unassignedCapacity, int $capacityPercent): int
{
    $tableCapacity = array_sum(array_map(static fn (array $table): int => $table['capacity'], $tables));
    
    // FIX: Se tableCapacity è 0, usa baseCapacity come fallback
    $capacity = ($tableCapacity > 0) ? $tableCapacity : $baseCapacity;
    
    $capacity = max(0, $capacity - $unassignedCapacity);
    
    if ($capacityPercent < 100) {
        $capacity = (int) floor($capacity * ($capacityPercent / 100));
    }
    
    return max(0, $capacity);
}
```

---

## 📋 AZIONI RICHIESTE

**Per favore dimmi:**

1. **Hai tavoli configurati?** Sì/No
2. **Se sì, quanti e che capacità?** Es: 5 tavoli da 4 posti
3. **Se no, hai impostato capacità sala/default?** Es: 40 posti
4. **Quante persone provi a prenotare?** Es: 2, 4, 8...
5. **Per quale meal e data?** Es: Cena, oggi

Con queste info posso darti la soluzione esatta!

---

**Data:** 2025-10-09  
**Status:** In attesa info utente
