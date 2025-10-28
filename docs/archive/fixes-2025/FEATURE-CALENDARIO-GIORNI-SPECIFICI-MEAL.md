# Calendario con Giorni Specifici per Meal

## Descrizione

Implementata la funzionalità che permette di visualizzare nel calendario solo i giorni disponibili per il servizio (meal type) selezionato. Ad esempio, se un servizio è configurato per essere disponibile solo la domenica, il calendario mostrerà attive solo le domeniche.

## Implementazione

### 1. Backend (PHP)

#### File: `src/Frontend/FormContext.php`

Le funzioni già esistenti sono state utilizzate per estrarre i giorni disponibili da ogni meal:

- **`extractAvailableDays()`** (righe 627-669): Estrae i giorni disponibili aggregati da tutti i meal
- **`enrichMealsWithAvailableDays()`** (righe 706-743): Arricchisce ogni meal con l'array `available_days` contenente i numeri ISO dei giorni della settimana (0=domenica, 1=lunedì, ecc.)
- **`parseDaysFromSchedule()`** (righe 677-697): Parsea i giorni dalla definizione dello schedule nel formato `day=HH:MM-HH:MM`

I giorni disponibili vengono estratti dal campo `hours_definition` di ogni meal (nel formato `mon=19:00-23:00\ntue=19:00-23:00\n...`) e convertiti in numeri ISO.

#### File: `templates/frontend/form.php`

**Modifiche apportate:**

1. **Riga 80**: Aggiunto `'meals' => $meals` al dataset JSON passato al JavaScript
   ```php
   $dataset = [
       'config'  => $config,
       'strings' => $strings,
       'steps'   => $steps,
       'events'  => $events,
       'privacy' => $privacy,
       'meals'   => $meals,  // <-- AGGIUNTO
   ];
   ```

2. **Righe 285-296**: Aggiunto attributo `data-meal-available-days` a ogni button meal per debug e tracciabilità
   ```php
   $mealAvailableDays = isset($meal['available_days']) && is_array($meal['available_days']) 
       ? wp_json_encode($meal['available_days']) 
       : '[]';
   ```
   ```html
   <button
       data-meal-available-days="<?php echo esc_attr($mealAvailableDays); ?>"
       ...
   >
   ```

### 2. Frontend (JavaScript)

#### File: `assets/js/fe/onepage.js`

**Modifiche apportate:**

1. **Riga 766**: Corretto l'accesso ai meals dal dataset
   ```javascript
   // Prima: const meals = this.config && this.config.meals ? this.config.meals : [];
   // Dopo:
   const meals = this.dataset && this.dataset.meals ? this.dataset.meals : [];
   ```

2. **Righe 207-211**: Aggiunta inizializzazione dei giorni disponibili per il meal di default all'avvio
   ```javascript
   if (button.hasAttribute('data-active') && _this.hiddenMeal) {
       _this.applyMealSelection(button);
       // Imposta i giorni disponibili per il meal di default
       const mealKey = button.getAttribute('data-fp-resv-meal') || '';
       if (mealKey) {
           _this.updateAvailableDaysForMeal(mealKey);
       }
   }
   ```

3. **Riga 753**: Funzione `updateAvailableDaysForMeal()` già esistente viene chiamata quando cambia il meal selezionato

### 3. Funzione `updateAvailableDaysForMeal()` (righe 760-798)

Questa funzione, già presente nel codice:

1. Cerca il meal selezionato nell'array `this.dataset.meals`
2. Se il meal ha giorni disponibili specifici (`available_days`), li imposta come `currentAvailableDays`
3. Altrimenti usa i giorni disponibili globali come fallback
4. Valida la data attualmente selezionata e la resetta se non è più disponibile con il nuovo meal
5. Mostra un messaggio informativo all'utente con i giorni disponibili

## Formato Giorni Disponibili

I giorni disponibili sono rappresentati come numeri ISO (0-6):

- `0` = Domenica
- `1` = Lunedì
- `2` = Martedì
- `3` = Mercoledì
- `4` = Giovedì
- `5` = Venerdì
- `6` = Sabato

Esempio: `["0"]` = solo domenica, `["5", "6", "0"]` = venerdì, sabato e domenica

## Esempio di Configurazione

Per configurare un servizio "Brunch" disponibile solo la domenica:

1. Nell'admin WordPress, vai alle impostazioni del plugin
2. Configura il meal plan con un servizio che ha `hours_definition`:
   ```
   sun=12:00-15:00
   ```

3. Il sistema automaticamente:
   - Estrae il giorno `sun` (domenica)
   - Lo converte nel numero ISO `0`
   - Lo aggiunge all'array `available_days` del meal: `["0"]`
   - Passa i dati al frontend tramite JSON

4. Nel form di prenotazione:
   - Quando l'utente seleziona "Brunch", il calendario mostrerà attive solo le domeniche
   - Se l'utente aveva selezionato una data diversa (es. sabato), il campo verrà resettato con un messaggio informativo

## Validazione

Il sistema include validazione lato client:

- Quando l'utente seleziona una data, il sistema verifica se il giorno della settimana è disponibile per il meal selezionato
- Se non è disponibile, mostra un messaggio di errore con l'elenco dei giorni disponibili
- Il campo data viene resettato automaticamente

Messaggio di errore esempio:
```
Questo giorno non è disponibile. Giorni disponibili: domenica.
```

## Compatibilità

- ✅ Compatibile con tutti i browser moderni
- ✅ Supporta meal senza giorni specifici (usa configurazione globale)
- ✅ Supporta meal con configurazioni orarie multiple nello stesso giorno
- ✅ Gestisce correttamente il cambio di meal durante la compilazione del form

## Testing

Per testare la funzionalità:

1. Configura due meal con giorni diversi (es. "Pranzo" solo sabato-domenica, "Cena" tutti i giorni)
2. Apri il form di prenotazione
3. Seleziona "Pranzo" → il calendario mostrerà solo sabato e domenica
4. Seleziona "Cena" → il calendario mostrerà tutti i giorni
5. Verifica che la data selezionata venga resettata quando non è più valida

## File Modificati

- `src/Frontend/FormContext.php` - Nessuna modifica (funzioni già presenti)
- `templates/frontend/form.php` - Aggiunto meals al dataset e attributo data-meal-available-days
- `assets/js/fe/onepage.js` - Corretto accesso ai meals e aggiunta inizializzazione giorni di default
- `assets/dist/fe/onepage.esm.js` - Bundle compilato
- `assets/dist/fe/onepage.iife.js` - Bundle compilato

## Data Implementazione

15 Ottobre 2025

