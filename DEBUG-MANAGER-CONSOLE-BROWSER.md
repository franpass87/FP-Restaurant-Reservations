# 🔍 Debug Manager - Solo Console Browser

> **Problema**: Il manager non mostra prenotazioni anche se le email vengono inviate
> **Soluzione**: Verifica passo-passo usando solo la console del browser

---

## 🎯 STEP 1: Verifica se le Prenotazioni sono nel Database

### Apri Console Browser (F12)

1. Vai sul sito WordPress (front-end o admin, indifferente)
2. Premi **F12** per aprire Developer Tools
3. Clicca sul tab **Console**
4. Copia e incolla questo codice:

```javascript
fetch('/wp-json/wp/v2/users/me', {
    credentials: 'include',
    headers: {
        'X-WP-Nonce': document.querySelector('meta[name="wp-nonce"]')?.content || ''
    }
}).then(r => r.json()).then(data => {
    console.log('✅ Utente loggato:', data.name);
    
    // Ora controlla le prenotazioni
    return fetch('/wp-json/fp-resv/v1/agenda?range=month&date=' + new Date().toISOString().split('T')[0], {
        credentials: 'include',
        headers: {
            'X-WP-Nonce': data._wpnonce || ''
        }
    });
}).then(r => r.json()).then(agenda => {
    console.log('📊 RISPOSTA AGENDA:', agenda);
    console.log('📝 Numero prenotazioni:', agenda.reservations?.length || 0);
    
    if (agenda.reservations && agenda.reservations.length > 0) {
        console.log('✅ PRENOTAZIONI TROVATE!');
        console.table(agenda.reservations.slice(0, 10));
    } else {
        console.error('❌ NESSUNA PRENOTAZIONE NELL\'ENDPOINT!');
        console.log('Meta:', agenda.meta);
    }
}).catch(err => console.error('❌ ERRORE:', err));
```

### Risultati Possibili:

#### ✅ Caso A: Vedi prenotazioni
```
✅ PRENOTAZIONI TROVATE!
📝 Numero prenotazioni: 15
```
**➡️ Il database funziona! Il problema è nel JavaScript del manager**  
**Vai allo STEP 2A**

#### ❌ Caso B: Nessuna prenotazione
```
❌ NESSUNA PRENOTAZIONE NELL'ENDPOINT!
📝 Numero prenotazioni: 0
```
**➡️ Il problema è nel salvatagg o nella query SQL**  
**Vai allo STEP 2B**

---

## 📊 STEP 2A: Database OK, Manager Rotto

Se hai visto prenotazioni nello STEP 1, il problema è il manager che non le visualizza.

### Test Manager:

1. Vai su **WordPress Admin → Prenotazioni → Agenda**
2. Apri **Console (F12)**
3. Guarda se vedi questi log:

```javascript
[Agenda] 🚀 Inizializzazione...
[Agenda] 📥 Caricamento dati...
[Agenda] ✅ Dati caricati: X prenotazioni
```

### Se NON vedi questi log:

Il JavaScript del manager non si sta caricando. Esegui questo:

```javascript
// Verifica se config manager esiste
console.log('fpResvAgendaSettings:', window.fpResvAgendaSettings);
```

#### Se `undefined`:
**PROBLEMA**: PHP non passa configurazione al JavaScript

**SOLUZIONE**:
```javascript
// Manualmente inizializza (test)
window.fpResvAgendaSettings = {
    restRoot: '/wp-json/fp-resv/v1/',
    nonce: 'test',
    locale: 'it_IT'
};
```

Poi ricarica. Se funziona → **problema in `AdminREST::enqueueScripts()`**

---

## 🔥 STEP 2B: Nessuna Prenotazione nel Database

Se nello STEP 1 hai visto 0 prenotazioni, significa che **il form NON salva nel database**.

### Test Creazione Prenotazione:

Nella console, prova a creare una prenotazione manualmente:

```javascript
// Test: Crea prenotazione via API
fetch('/wp-json/fp-resv/v1/reservations', {
    method: 'POST',
    credentials: 'include',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings?.nonce || ''
    },
    body: JSON.stringify({
        date: '2025-10-15',
        time: '19:00',
        party: 2,
        first_name: 'Test',
        last_name: 'Debug',
        email: 'test@example.com',
        phone: '+39 123456789',
        language: 'it',
        location: '1',
        policy_version: '1.0',
        marketing_consent: false,
        profiling_consent: false
    })
})
.then(r => r.text())
.then(text => {
    console.log('📦 RISPOSTA RAW:', text);
    try {
        const data = JSON.parse(text);
        console.log('✅ PRENOTAZIONE CREATA:', data);
        console.log('🆔 ID:', data.id);
        
        if (data.id) {
            console.log('✅ DATABASE FUNZIONA! Il problema è nel form frontend');
        }
    } catch (e) {
        console.error('❌ RISPOSTA NON È JSON:', text);
    }
})
.catch(err => console.error('❌ ERRORE:', err));
```

### Risultati:

#### ✅ Se crea la prenotazione (vedi ID):
**PROBLEMA**: Il database funziona, ma il **form frontend** ha un bug nel salvataggio
**➡️ Vai allo STEP 3**

#### ❌ Se vedi errore:
Copia l'errore e analizzalo:

| Errore | Significato | Fix |
|--------|-------------|-----|
| `"No spots available"` | Disponibilità zero | Fix capacità |
| `"Transaction rollback"` | Verifica disponibilità fallisce | Fix logica disponibilità |
| `"Unable to create reservation"` | INSERT fallisce | Problema database/permessi |
| `401/403 Forbidden` | Problema autenticazione | Fix nonce |

---

## 🐛 STEP 3: Debug Form Frontend

Se il test manuale crea la prenotazione ma il form no, il problema è nel form.

### Verifica Cosa Invia il Form:

1. Vai alla pagina del form prenotazioni
2. Apri Console (F12) e tab **Network**
3. Compila il form e invia
4. Nella tab Network, cerca la richiesta a `/wp-json/fp-resv/v1/reservations`
5. Clicca sulla richiesta e guarda:
   - **Headers** → verifica che ci sia `X-WP-Nonce`
   - **Payload** → verifica dati inviati
   - **Response** → vedi la risposta

### Copia Richiesta nella Console:

Nella tab Network:
1. Trova la richiesta POST
2. Tasto destro → **Copy** → **Copy as fetch**
3. Incolla nella console
4. Modifica per vedere la risposta:

```javascript
// Incolla qui il fetch copiato, ma aggiungi alla fine:
.then(r => r.json())
.then(data => {
    console.log('📦 RISPOSTA:', data);
    if (data.id) {
        console.log('✅ Salvata! ID:', data.id);
    } else if (data.message) {
        console.error('❌ ERRORE:', data.message);
    }
});
```

---

## 🎯 STEP 4: Analisi Errori Specifici

### Errore: "Transaction rollback"

**Causa**: La verifica disponibilità fallisce e fa rollback

**Verifica disponibilità**:
```javascript
fetch('/wp-json/fp-resv/v1/availability?date=2025-10-15&party=2&meal=dinner')
.then(r => r.json())
.then(data => {
    console.log('📊 DISPONIBILITÀ:', data);
    console.table(data.slots);
    
    const available = data.slots?.filter(s => s.available);
    console.log(`✅ Slot disponibili: ${available?.length || 0}`);
    
    if (!available || available.length === 0) {
        console.error('❌ PROBLEMA: Nessuno slot disponibile!');
        console.log('Questo causa il rollback della transazione');
    }
});
```

### Errore: "No spots available"

**Fix**: Aumenta capacità ristorante

Nella console:
```javascript
// Verifica capacità attuale
fetch('/wp-json/fp-resv/v1/settings/capacity')
.then(r => r.json())
.then(data => {
    console.log('📊 CAPACITÀ:', data);
    
    if (data.lunch_capacity === 0 && data.dinner_capacity === 0) {
        console.error('❌ CAPACITÀ A ZERO! Questo blocca tutte le prenotazioni');
        console.log('➡️ Vai su WP Admin → Prenotazioni → Impostazioni → Capacità');
        console.log('➡️ Imposta valori > 0');
    }
});
```

---

## 📋 CHECKLIST FINALE

Dopo aver completato gli step sopra, dovresti sapere:

- [ ] **Le prenotazioni SONO nel database?** (STEP 1)
- [ ] **Il manager carica il JavaScript?** (STEP 2A)
- [ ] **Il form invia correttamente?** (STEP 3)
- [ ] **La disponibilità è configurata?** (STEP 4)
- [ ] **Ci sono errori nella console?** (Tutti gli step)

---

## 🎯 SOLUZIONI RAPIDE

### Problema: "Database vuoto, ma email inviate"

**Causa più probabile**: La transazione va in rollback perché la verifica disponibilità fallisce

**Fix**:
1. Verifica capacità ristorante (deve essere > 0)
2. Verifica orari configurati per il meal type
3. Verifica che la data non sia chiusa

### Problema: "Manager non mostra prenotazioni"

**Causa più probabile**: Date diverse tra ricerca e database

**Fix**: Nella console dell'agenda:
```javascript
// Forza range ampio
fetch('/wp-json/fp-resv/v1/agenda?range=month&date=2020-01-01', {
    credentials: 'include'
}).then(r => r.json()).then(data => {
    console.log('Prenotazioni nel periodo 2020-2030:', data.reservations?.length);
    if (data.reservations?.length > 0) {
        console.log('Prima prenotazione:', data.reservations[0].date);
        console.log('➡️ Le prenotazioni ci sono! Il problema è il filtro date');
    }
});
```

---

## 🆘 Se Nulla Funziona

**Inviami questi dati** (copia dalla console):

```javascript
// Esegui questo e copiami l'output
const diagnostics = {
    // Test 1: Prenotazioni
    reservations: await fetch('/wp-json/fp-resv/v1/agenda?range=month').then(r => r.json()),
    
    // Test 2: Disponibilità
    availability: await fetch('/wp-json/fp-resv/v1/availability?date=2025-10-15&party=2&meal=dinner').then(r => r.json()),
    
    // Test 3: Config
    config: window.fpResvAgendaSettings,
    
    // Test 4: Errori
    errors: console.errors || []
};

console.log(JSON.stringify(diagnostics, null, 2));
```

Copia l'output e inviamelo!

---

**Creato**: 2025-10-12  
**Versione**: 1.0  
**Tempo stimato**: 10-15 minuti

