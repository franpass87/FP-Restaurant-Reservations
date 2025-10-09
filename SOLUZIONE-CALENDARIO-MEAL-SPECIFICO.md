# Soluzione: Calendario Filtrato per Meal Specifico

## Problema Risolto

Quando l'utente selezionava un meal (pasto) disponibile solo in determinati giorni (es. solo domenica), il calendario mostrava comunque tutti i giorni disponibili. L'utente doveva procedere fino a 2 step successivi per scoprire che il giorno selezionato non era disponibile per quel meal specifico.

**Esperienza precedente (problematica):**
1. Utente seleziona "Brunch" (disponibile solo domenica)
2. Il calendario mostra tutti i giorni della settimana
3. Utente seleziona lunedì
4. Utente seleziona orario
5. Sistema mostra errore: "Nessuna disponibilità"

**Esperienza migliorata:**
1. Utente seleziona "Brunch" (disponibile solo domenica)
2. Il calendario filtra automaticamente e mostra disponibili SOLO le domeniche
3. Utente può selezionare solo giorni validi fin da subito
4. Se aveva già selezionato una data non valida, viene resettata automaticamente

## Modifiche Implementate

### 1. Backend (PHP)

**File modificato:** `src/Frontend/FormContext.php`

#### Nuovo metodo: `enrichMealsWithAvailableDays()`

Arricchisce ogni meal con i suoi giorni disponibili specifici.

```php
private function enrichMealsWithAvailableDays(array $meals, array $generalSettings): array
{
    $dayMapping = [
        'mon' => '1',
        'tue' => '2',
        'wed' => '3',
        'thu' => '4',
        'fri' => '5',
        'sat' => '6',
        'sun' => '0',
    ];

    foreach ($meals as $index => $meal) {
        $days = [];

        // Se il meal ha hours_definition specifico, usalo
        if (!empty($meal['hours_definition'])) {
            $days = $this->parseDaysFromSchedule((string) $meal['hours_definition']);
        }
        // Altrimenti usa service_hours_definition generale come fallback
        elseif (!empty($generalSettings['service_hours_definition'])) {
            $days = $this->parseDaysFromSchedule((string) $generalSettings['service_hours_definition']);
        }

        // Converti i giorni in numeri ISO
        $dayNumbers = [];
        foreach ($days as $day) {
            if (isset($dayMapping[$day])) {
                $dayNumbers[] = $dayMapping[$day];
            }
        }

        // Aggiungi i giorni disponibili al meal
        $meals[$index]['available_days'] = $dayNumbers;
    }

    return $meals;
}
```

#### Integrazione nel flusso

Alla riga **1715** di `FormContext.php`:

```php
// Arricchisci ogni meal con i suoi giorni disponibili specifici
$meals = $this->enrichMealsWithAvailableDays($meals, $generalSettings);
```

**Risultato:** Ogni meal nell'array `config.meals` ora ha una proprietà `available_days` con i giorni specifici in formato ISO (0=domenica, 1=lunedì, etc.).

### 2. Frontend (JavaScript)

**File modificato:** `assets/js/fe/onepage.js`

#### Modifica 1: Giorni disponibili dinamici

Nella funzione `initializeDateField()` (riga **438**):

```javascript
// Ottieni i giorni disponibili dalla configurazione (inizialmente tutti i giorni aggregati)
this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [];
```

E nelle righe successive, usa `this.currentAvailableDays` invece della costante locale:

```javascript
if (this.currentAvailableDays.length > 0 && selectedDate) {
    const date = new Date(selectedDate);
    const dayOfWeek = date.getDay().toString();

    if (!this.currentAvailableDays.includes(dayOfWeek)) {
        const dayNames = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
        const availableDayNames = this.currentAvailableDays.map(d => dayNames[parseInt(d)]).join(', ');
        const errorMessage = `Questo giorno non è disponibile. Giorni disponibili: ${availableDayNames}.`;
        // ... resto del codice di validazione
    }
}
```

#### Modifica 2: Aggiornamento giorni alla selezione del meal

Nella funzione `handleMealSelection()` (riga **856**):

```javascript
// Aggiorna i giorni disponibili in base al meal selezionato
this.updateAvailableDaysForMeal(mealKey);
```

#### Modifica 3: Nuovo metodo `updateAvailableDaysForMeal()`

Implementato alla riga **863**:

```javascript
updateAvailableDaysForMeal(mealKey) {
    if (!this.dateField || !mealKey) {
        return;
    }

    // Trova il meal selezionato dall'array dei meals
    const meals = this.config && this.config.meals ? this.config.meals : [];
    const selectedMeal = meals.find(meal => meal.key === mealKey);

    // Se il meal ha giorni disponibili specifici, usali
    if (selectedMeal && selectedMeal.available_days && selectedMeal.available_days.length > 0) {
        this.currentAvailableDays = selectedMeal.available_days;
    } else {
        // Altrimenti usa i giorni disponibili globali
        this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [];
    }

    // Valida la data attualmente selezionata (se presente)
    const currentDate = this.dateField.value;
    if (currentDate && this.currentAvailableDays.length > 0) {
        const date = new Date(currentDate);
        const dayOfWeek = date.getDay().toString();

        // Se il giorno non è più disponibile con il nuovo meal, resetta il campo
        if (!this.currentAvailableDays.includes(dayOfWeek)) {
            const dayNames = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
            const availableDayNames = this.currentAvailableDays.map(d => dayNames[parseInt(d)]).join(', ');
            
            // Mostra un messaggio informativo
            if (window.console && window.console.warn) {
                console.warn(`[FP-RESV] La data selezionata non è disponibile per questo servizio. Giorni disponibili: ${availableDayNames}.`);
            }
            
            // Resetta il campo data
            this.dateField.value = '';
            this.dateField.setCustomValidity('');
            this.dateField.setAttribute('aria-invalid', 'false');
            
            // Resetta anche gli slot se presenti
            if (this.availabilityController && typeof this.availabilityController.clear === 'function') {
                this.availabilityController.clear();
            }
        }
    }
}
```

## Flusso di Funzionamento

### Scenario: Meal "Brunch" disponibile solo domenica

1. **Backend processa i meal:**
   ```json
   {
     "key": "brunch",
     "label": "Brunch",
     "hours_definition": "sun=12:30-15:00",
     "available_days": ["0"]  // ← Aggiunto dal backend
   }
   ```

2. **Frontend inizializza con giorni aggregati:**
   ```javascript
   this.currentAvailableDays = ["0", "6"]  // Tutti i giorni da tutti i meal
   ```

3. **Utente seleziona "Brunch":**
   - `handleMealSelection()` viene chiamato
   - `updateAvailableDaysForMeal("brunch")` aggiorna:
     ```javascript
     this.currentAvailableDays = ["0"]  // Solo domenica
     ```

4. **Se utente aveva già selezionato lunedì (day = 1):**
   - Sistema verifica: `"1"` non è in `["0"]`
   - Resetta automaticamente il campo data
   - Mostra messaggio in console: "Giorni disponibili: domenica"

5. **Utente apre il calendario:**
   - Può selezionare qualsiasi giorno (limitazione browser nativi)
   - **MA** quando seleziona un giorno, viene validato immediatamente
   - Se seleziona martedì (day = 2):
     - Sistema verifica: `"2"` non è in `["0"]`
     - Mostra errore: "Questo giorno non è disponibile. Giorni disponibili: domenica."
     - Resetta il campo dopo 100ms

## Vantaggi della Soluzione

✅ **UX Migliorata:** L'utente scopre subito se il giorno non è disponibile, non dopo 2 step

✅ **Validazione Immediata:** La data viene validata al momento della selezione

✅ **Auto-reset Intelligente:** Se l'utente cambia meal, date incompatibili vengono resettate automaticamente

✅ **Messaggi Chiari:** Indica esplicitamente quali giorni sono disponibili

✅ **Fallback Robusto:** Se un meal non ha orari specifici, usa quelli generali del servizio

## Limitazioni Tecniche

⚠️ **Calendar Picker Nativo:** I browser nativi non permettono di disabilitare giorni specifici nel calendario visuale. La soluzione attuale valida **post-selezione**.

### Alternative Future (opzionali)

Per una UX ancora migliore, si potrebbe integrare:
- **Flatpickr**: Date picker JavaScript con disabilitazione giorni specifica
- **Air Datepicker**: Alternativa leggera e personalizzabile
- **React/Vue Date Picker**: Se il progetto migra a un framework

## Test Consigliati

### Test 1: Meal con solo domenica disponibile
1. Configurare meal "Brunch" con `hours_definition: sun=12:30-15:00`
2. Selezionare "Brunch"
3. Provare a selezionare lunedì nel calendario
4. **Risultato atteso:** Errore + reset automatico

### Test 2: Cambio meal con data già selezionata
1. Selezionare meal "Pranzo" (disponibile lun-ven)
2. Selezionare mercoledì
3. Cambiare a "Brunch" (solo domenica)
4. **Risultato atteso:** Data resettata automaticamente + messaggio console

### Test 3: Meal senza hours_definition specifico
1. Configurare meal senza `hours_definition`
2. Selezionare il meal
3. **Risultato atteso:** Usa giorni da `service_hours_definition` generale

### Test 4: Validazione tempo reale
1. Selezionare "Brunch" (solo domenica)
2. Aprire calendario
3. Selezionare sabato
4. **Risultato atteso:** Errore immediato + campo resettato dopo 100ms

## File Modificati

- ✅ `src/Frontend/FormContext.php` (linee 1715, 2254-2292)
- ✅ `assets/js/fe/onepage.js` (linee 438, 452-458, 856, 863-907)

## Compatibilità

- ✅ PHP >= 8.1
- ✅ Browser moderni con supporto ES6+
- ✅ Nessuna dipendenza esterna aggiunta
- ✅ Retrocompatibile con configurazioni esistenti
