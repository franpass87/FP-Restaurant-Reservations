# Fix: Backend-Frontend Communication per Disponibilità

## Problema Risolto

Il sistema mostrava "Nessuna disponibilità" perché il **frontend non comunicava correttamente con il backend** quando il parametro `meal` non era obbligatorio.

### Sintomi
- Il messaggio "Nessuna disponibilità per questo servizio" appariva anche con orari configurati correttamente
- La richiesta API non veniva effettuata dal frontend
- Il problema si verificava quando il meal non era selezionato o non era obbligatorio

## Causa del Problema

Nel file `assets/js/fe/availability.js`, la funzione `showEmpty()` richiedeva **sempre** il parametro `meal` per considerare il sistema pronto a fare la richiesta:

```javascript
// PRIMA (SBAGLIATO)
const readyForAvailability = hasMeal && hasDate && hasParty;
```

Questo significava che:
1. Se l'utente non selezionava un meal, il frontend non faceva la richiesta API
2. Il backend non veniva mai chiamato
3. L'utente vedeva "nessuna disponibilità" anche se gli slot esistevano

## Soluzione Implementata

Ho corretto la logica per rispettare il flag `requiresMeal` che indica se il meal è obbligatorio o opzionale:

```javascript
// DOPO (CORRETTO)
const requiresMeal = hasParams && Boolean(params.requiresMeal);
const readyForAvailability = hasDate && hasParty && (!requiresMeal || hasMeal);
```

Ora:
1. Se `requiresMeal` è `true` → il meal è obbligatorio, come prima
2. Se `requiresMeal` è `false` → il meal è opzionale, la richiesta viene fatta anche senza meal
3. Il backend riceve la richiesta e può calcolare la disponibilità correttamente

## File Modificati

```
assets/js/fe/availability.js          | Corretto logica readyForAvailability
assets/dist/fe/onepage.esm.js         | File compilato aggiornato
assets/dist/fe/onepage.iife.js        | File compilato aggiornato
```

## Come Verificare il Fix

1. **Configurazione con meal opzionale**:
   - Il widget dovrebbe mostrare disponibilità anche senza selezionare il meal
   - La richiesta API viene fatta con `date` e `party` ma senza `meal`

2. **Configurazione con meal obbligatorio** (`requiresMeal: true`):
   - Il comportamento resta invariato
   - Il meal deve essere selezionato prima di vedere la disponibilità

3. **Console del browser**:
   - Aprire DevTools (F12) → Tab Network
   - Vedere che la richiesta a `/wp-json/fp-resv/v1/availability?date=...&party=...` viene effettuata
   - La risposta contiene gli slot disponibili

## Test della Comunicazione

Per verificare che backend e frontend comunichino correttamente:

### Test 1: Verifica richiesta API
```javascript
// Nella console del browser
fetch('/wp-json/fp-resv/v1/availability?date=2025-10-15&party=2')
  .then(r => r.json())
  .then(data => console.log(data))
```

Dovresti vedere:
```json
{
  "date": "2025-10-15",
  "slots": [...],
  "meta": {
    "has_availability": true/false
  }
}
```

### Test 2: Verifica parametri frontend
```javascript
// Nel codice, dopo collectAvailabilityParams()
console.log('Params:', {
  date: this.dateField.value,
  party: this.partyField.value,
  meal: this.hiddenMeal?.value,
  requiresMeal: this.mealButtons.length > 0
});
```

## Compilazione

Per applicare le modifiche:

```bash
npm install
npm run build:all
```

## Ulteriori Debug

Se il problema persiste dopo il fix:

1. **Verificare endpoint API**:
   - Controllare che `/wp-json/fp-resv/v1/availability` risponda correttamente
   - Testare con parametri: `?date=YYYY-MM-DD&party=N`

2. **Verificare configurazione orari**:
   - Backend → Prenotazioni → Impostazioni → Generale
   - Verificare che ci siano orari configurati in "Orari di servizio"

3. **Console errori**:
   - Aprire DevTools → Console
   - Cercare errori JavaScript o errori di rete

---

**Data fix**: 2025-10-09  
**File modificati**: 3  
**Build completato**: ✅
