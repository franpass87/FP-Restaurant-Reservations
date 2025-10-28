# 📊 GERARCHIA CAPACITÀ - Come Funziona

## 🔢 ORDINE DI CALCOLO

### 1. CAPACITÀ BASE (baseCapacity)

**Sorgente:** Tavoli o Sale

```php
$hasPhysicalTables = $availableTables !== [];

if ($hasPhysicalTables) {
    // USA CAPACITÀ TAVOLI
    $baseCapacity = somma_capacità_tavoli_disponibili;
} else {
    // USA CAPACITÀ SALA
    $baseCapacity = capacità_sala_o_default;
}
```

**Esempi:**
- **Con tavoli:** 3 tavoli da 4 posti = baseCapacity 12
- **Senza tavoli:** Usa sala/default = baseCapacity 40

---

### 2. RIDUZIONI (capacity dopo detrazioni)

```php
$capacity = $baseCapacity;

// Sottrai tavoli occupati
$capacity -= prenotazioni_non_assegnate;

// Applica percentuale chiusure
if ($capacityPercent < 100) {
    $capacity = $capacity * ($capacityPercent / 100);
}
```

**Esempio:**
- baseCapacity: 40
- Prenotazioni non assegnate: 8
- Chiusura al 50%: (40 - 8) * 0.5 = 16

---

### 3. MEAL CAPACITY LIMIT (limite finale) ⭐

```php
if ($mealSettings['capacity_limit'] !== null) {
    // Usa il MINORE tra capacity calcolata e meal limit
    $capacity = min($capacity, $mealSettings['capacity_limit']);
}
```

**LA MEAL CAPACITY È UN HARD LIMIT!**

---

## 🎯 ESEMPI PRATICI

### Esempio 1: Meal Capacity MINORE di base

**Setup:**
- Tavoli: 5 tavoli × 6 posti = 30 posti
- Meal "Pranzo": capacity = 20

**Calcolo:**
1. baseCapacity = 30 (dai tavoli)
2. Nessuna prenotazione: capacity = 30
3. **Meal limit: capacity = min(30, 20) = 20** ✅

**Risultato:** Max 20 persone per il Pranzo

---

### Esempio 2: Meal Capacity MAGGIORE di base

**Setup:**
- Nessun tavolo (disabilitati)
- Capacità default: 40
- Meal "Cena": capacity = 60

**Calcolo:**
1. baseCapacity = 40 (default, dal mio fix)
2. Nessuna prenotazione: capacity = 40
3. **Meal limit: capacity = min(40, 60) = 40** ✅

**Risultato:** Max 40 persone per la Cena (usa la minore)

---

### Esempio 3: Meal senza capacity limit

**Setup:**
- Tavoli: 4 tavoli × 4 posti = 16 posti
- Meal "Brunch": nessun capacity configurato

**Calcolo:**
1. baseCapacity = 16 (dai tavoli)
2. Nessuna prenotazione: capacity = 16
3. **Nessun meal limit: capacity = 16** ✅

**Risultato:** Max 16 persone per il Brunch

---

### Esempio 4: Con prenotazioni + meal limit

**Setup:**
- Capacità default: 40
- Prenotazioni non assegnate: 12
- Meal "Cena": capacity = 25

**Calcolo:**
1. baseCapacity = 40 (default)
2. Con prenotazioni: capacity = 40 - 12 = 28
3. **Meal limit: capacity = min(28, 25) = 25** ✅

**Risultato:** Max 25 persone disponibili per la Cena

---

## 📋 TABELLA RIASSUNTIVA

| Fase | Cosa determina | Può essere sovrascritto? |
|------|----------------|-------------------------|
| **1. Base** | Tavoli o Sala/Default | No, è il punto di partenza |
| **2. Riduzioni** | Prenotazioni, Chiusure | No, sono detrazioni obbligatorie |
| **3. Meal Limit** | Configurazione Meal | **NO, è un HARD LIMIT** |

---

## ✅ REGOLA D'ORO

**LA MEAL CAPACITY È SEMPRE RISPETTATA!**

```
Capacità Finale = min(
    capacità_dopo_riduzioni,
    meal_capacity_limit
)
```

Non importa quanti tavoli hai o quale sia la capacità di default:
- Se meal capacity = 20 → **MAX 20 persone**
- Se meal capacity = 30 → **MAX 30 persone**
- Se meal capacity non impostato → Usa capacità calcolata

---

## 🐛 IL BUG CHE HO FIXATO

### PRIMA (Bug)
```
Tavoli disabilitati + Nessuna sala
→ baseCapacity = 0
→ capacity = 0
→ meal_limit = min(0, 30) = 0
→ ❌ SEMPRE FULL
```

### DOPO (Fix)
```
Tavoli disabilitati + Nessuna sala
→ baseCapacity = 40 (default virtuale)
→ capacity = 40
→ meal_limit = min(40, 30) = 30
→ ✅ MAX 30 PERSONE (corretto!)
```

---

## 🎯 CONCLUSIONE

**I tavoli NON sovrascrivono la meal capacity!**

La meal capacity è un **limite massimo assoluto** che viene sempre applicato alla fine del calcolo.

Il mio fix garantisce che anche senza tavoli, il sistema abbia una baseCapacity valida (40) invece di 0, ma la meal capacity continua a funzionare come limite superiore.

**Tutto funziona correttamente!** ✅
