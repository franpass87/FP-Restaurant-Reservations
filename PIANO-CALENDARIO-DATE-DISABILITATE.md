# 📅 Piano: Calendario Date Disabilitate

**Richiesta:** Disabilitare le date non disponibili nel calendario (non cliccabili)  
**Data:** 2 Novembre 2025  
**Plugin:** FP Restaurant Reservations v0.9.0-rc7

---

## 🎉 OTTIMA NOTIZIA!

**Il sistema è GIÀ IMPLEMENTATO!** ✅

Il calendario Flatpickr è già configurato per disabilitare automaticamente le date non disponibili.

---

## 📋 STATO ATTUALE

### ✅ Sistema Già Presente

Il file `assets/js/fe/onepage.js` contiene già tutto il necessario:

#### 1. Configurazione Flatpickr (riga 328)
```javascript
this.flatpickrInstance = window.flatpickr(this.dateField, {
    minDate: 'today',
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'd/m/Y',
    locale: window.flatpickr.l10ns.it || 'it',
    enable: [], // ← Date abilitate (vuoto inizialmente)
    allowInput: false,
    disableMobile: false,
});
```

#### 2. Caricamento Date Disponibili (riga 369)
```javascript
loadAvailableDays(meal = null) {
    // Calcola range (oggi + 90 giorni)
    const from = this.formatLocalDate(today);
    const to = this.formatLocalDate(future); // +90 giorni
    
    // Chiama API
    const endpoint = '/wp-json/fp-resv/v1/available-days';
    url.searchParams.set('from', from);
    url.searchParams.set('to', to);
    if (meal) url.searchParams.set('meal', meal);
    
    fetch(url)
        .then(data => {
            this.availableDaysCache = data.days;
            this.applyDateRestrictions(); // ← Applica restrizioni!
        });
}
```

#### 3. Applicazione Restrizioni (riga 415)
```javascript
applyDateRestrictions() {
    const enabledDates = [];
    
    // Filtra solo date disponibili per il meal selezionato
    Object.entries(this.availableDaysCache).forEach(([date, info]) => {
        if (selectedMeal && info.meals) {
            // Se meal specifico, controlla disponibilità per quel meal
            if (info.meals[selectedMeal]) {
                enabledDates.push(date);
            }
        } else if (info.available) {
            // Altrimenti usa disponibilità generale
            enabledDates.push(date);
        }
    });
    
    // Aggiorna Flatpickr
    this.flatpickrInstance.set('enable', enabledDates);
}
```

#### 4. Endpoint API (Backend già pronto!)
```
GET /wp-json/fp-resv/v1/available-days?from=2025-11-02&to=2026-02-01&meal=cena
```

Response:
```json
{
  "days": {
    "2025-11-02": {
      "available": true,
      "meals": { "cena": true, "pranzo": false }
    },
    "2025-11-03": {
      "available": false,
      "meals": { "cena": false, "pranzo": false }
    }
  }
}
```

---

## 🔍 ANALISI FUNZIONAMENTO

### Flusso Completo

```
1. Utente apre il form
   ↓
2. JavaScript inizializza Flatpickr
   ↓
3. loadAvailableDays() chiamato automaticamente
   ↓
4. Fetch API /available-days (90 giorni)
   ↓
5. Risposta salvata in availableDaysCache
   ↓
6. applyDateRestrictions() chiamato
   ↓
7. Costruisce array enabledDates
   ↓
8. flatpickrInstance.set('enable', enabledDates)
   ↓
9. Flatpickr disabilita date non in enabledDates
   ↓
10. Utente vede solo date cliccabili ✅
```

---

## 🎯 PIANO DI VERIFICA E OTTIMIZZAZIONE

### ✅ FASE 1: Verifica Funzionamento Attuale

**Obiettivo:** Confermare che il sistema funzioni correttamente

#### Step 1.1: Verifica Backend API ✅
```bash
# Test endpoint
curl "https://tuosito.com/wp-json/fp-resv/v1/available-days?from=2025-11-02&to=2025-12-02"
```

**Verifica:**
- [ ] API restituisce giorni disponibili
- [ ] Campo `meals` presente
- [ ] Logica corretta per ogni giorno

**File:** `src/Domain/Reservations/REST.php` (riga 628)  
**Status:** ✅ GIÀ IMPLEMENTATO

---

#### Step 1.2: Verifica Frontend JavaScript ✅
```javascript
// Console browser su pagina con form
console.log('Flatpickr:', window.flatpickr);
console.log('Instance:', fpResvForm.flatpickrInstance);
console.log('Cache:', fpResvForm.availableDaysCache);
```

**Verifica:**
- [ ] Flatpickr caricato
- [ ] Instance inizializzata
- [ ] Cache popolata con giorni

**File:** `assets/js/fe/onepage.js`  
**Status:** ✅ GIÀ IMPLEMENTATO

---

#### Step 1.3: Verifica Visuale
- [ ] Aprire form prenotazioni
- [ ] Cliccare sul campo data
- [ ] Verificare che date non disponibili siano grigie/non cliccabili

**Status:** ⏳ DA TESTARE

---

### 🔧 FASE 2: Possibili Ottimizzazioni (Se necessario)

**Solo se il sistema non funziona o necessita miglioramenti:**

#### Opzione 2.1: Migliorare UX Date Disabilitate

**Problema potenziale:** Date disabilitate potrebbero non essere abbastanza evidenti

**Soluzione:**
```javascript
onDayCreate: function(dObj, dStr, fp, dayElem) {
    const dateStr = dayElem.dateObj.toISOString().split('T')[0];
    
    // Se la data NON è disponibile, aggiungi classe custom
    if (!enabledDates.includes(dateStr)) {
        dayElem.classList.add('fp-date-unavailable');
        dayElem.title = 'Data non disponibile';
    }
}
```

**CSS:**
```css
.flatpickr-day.fp-date-unavailable {
    background: #f1f1f1 !important;
    color: #ccc !important;
    cursor: not-allowed !important;
    text-decoration: line-through;
}
```

---

#### Opzione 2.2: Pre-caricamento Date All'Apertura

**Problema potenziale:** Le date vengono caricate dopo l'apertura del calendario

**Soluzione:**
```javascript
// In initializeCalendar(), PRIMA di creare Flatpickr:
async initializeCalendar() {
    // 1. Carica prima le date disponibili
    await this.loadAvailableDays();
    
    // 2. POI inizializza Flatpickr con le date già caricate
    this.flatpickrInstance = window.flatpickr(this.dateField, {
        enable: this.getEnabledDatesFromCache(), // ← Date già disponibili
        // ... resto config
    });
}
```

---

#### Opzione 2.3: Loading Indicator

**Problema potenziale:** Nessun feedback durante caricamento date

**Soluzione:**
```javascript
loadAvailableDays(meal = null) {
    // Mostra loading
    this.dateField.setAttribute('placeholder', 'Caricamento date disponibili...');
    this.dateField.disabled = true;
    
    fetch(url)
        .then(data => {
            // ... carica date
        })
        .finally(() => {
            this.dateField.disabled = false;
            this.dateField.setAttribute('placeholder', 'Seleziona data');
        });
}
```

---

#### Opzione 2.4: Highlight Date Disponibili

**Soluzione:** Evidenziare visivamente le date disponibili
```javascript
onDayCreate: function(dObj, dStr, fp, dayElem) {
    const dateStr = dayElem.dateObj.toISOString().split('T')[0];
    
    if (enabledDates.includes(dateStr)) {
        // Aggiungi badge o bordo verde
        dayElem.classList.add('fp-date-available');
    }
}
```

**CSS:**
```css
.flatpickr-day.fp-date-available {
    border: 2px solid #10b981;
    font-weight: 600;
}
```

---

## 📋 PIANO DI IMPLEMENTAZIONE

### 🎯 Scenario A: Sistema Già Funzionante

**Se il sistema funziona già:**

1. ✅ **Nessuna modifica necessaria!**
2. 📝 **Solo documentazione:**
   - Creare guida utente
   - Spiegare come funziona
   - Troubleshooting

**Tempo stimato:** 30 minuti (solo docs)

---

### 🔧 Scenario B: Sistema Necessita Miglioramenti

**Se il sistema non funziona perfettamente:**

#### Step 1: Diagnostica (30 min)
- [ ] Test API /available-days
- [ ] Verifica console browser
- [ ] Verifica network requests
- [ ] Identificare problema esatto

#### Step 2: Fix Backend (Se necessario - 1h)
- [ ] Verificare logica `findAvailableDaysForAllMeals()`
- [ ] Controllare che restituisca date corrette
- [ ] Testare con vari meal plans

**File:** `src/Domain/Reservations/Availability.php`

#### Step 3: Fix Frontend (Se necessario - 1h)
- [ ] Verificare `loadAvailableDays()` viene chiamato
- [ ] Controllare `applyDateRestrictions()` funzioni
- [ ] Debuggare enabledDates array

**File:** `assets/js/fe/onepage.js`

#### Step 4: Ottimizzazioni UX (Opzionale - 2h)
- [ ] Implementare loading indicator
- [ ] Migliorare styling date disabilitate
- [ ] Pre-caricamento date
- [ ] Highlight date disponibili

---

### 🚀 Scenario C: Miglioramento Proattivo

**Anche se funziona, migliorare UX:**

#### Step 1: Styling Date (30 min)
```css
/* assets/css/form.css */
.flatpickr-day.flatpickr-disabled {
    background: #f9fafb !important;
    color: #d1d5db !important;
    cursor: not-allowed !important;
    position: relative;
}

.flatpickr-day.flatpickr-disabled::after {
    content: '✕';
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 8px;
    color: #ef4444;
}
```

#### Step 2: Loading Feedback (15 min)
```javascript
// In loadAvailableDays()
const loadingMsg = document.createElement('div');
loadingMsg.className = 'fp-calendar-loading';
loadingMsg.textContent = 'Caricamento date disponibili...';
this.dateField.parentElement.appendChild(loadingMsg);

// ... fetch ...

finally(() => {
    loadingMsg.remove();
});
```

#### Step 3: Tooltip Date (30 min)
```javascript
onDayCreate: function(dObj, dStr, fp, dayElem) {
    const dateStr = formatDate(dayElem.dateObj);
    const dayInfo = availableDaysCache[dateStr];
    
    if (!dayInfo || !dayInfo.available) {
        dayElem.title = 'Non disponibile';
    } else {
        const meals = Object.keys(dayInfo.meals).filter(m => dayInfo.meals[m]);
        dayElem.title = 'Disponibile: ' + meals.join(', ');
    }
}
```

---

## 🧪 PIANO DI TEST

### Test 1: Verifica Sistema Attuale (15 min)

1. **Apri il form** su una pagina
2. **Clicca sul campo data**
3. **Osserva il calendario**

**Domande:**
- [ ] Le date si caricano?
- [ ] Vedi solo alcune date cliccabili?
- [ ] Le altre sono grigie/disabilitate?

**Se SÌ a tutte:** ✅ Sistema funzionante!  
**Se NO:** Procedi con diagnostica

---

### Test 2: Verifica API (5 min)

Apri in browser:
```
/wp-json/fp-resv/v1/available-days?from=2025-11-02&to=2025-12-02
```

**Verifica risposta:**
```json
{
  "days": {
    "2025-11-05": { "available": true, "meals": {...} },
    "2025-11-06": { "available": false, "meals": {...} }
  }
}
```

**Se OK:** ✅ Backend funzionante!

---

### Test 3: Verifica Console (5 min)

F12 → Console, poi apri form:

```javascript
// Dovrebbero apparire:
[FP-RESV] Inizializzazione calendario...
[FP-RESV] Caricamento giorni disponibili...
[FP-RESV] Date caricate: 90
```

**Se vedi log:** ✅ JavaScript funzionante!

---

### Test 4: Verifica Network (5 min)

F12 → Network → Apri form

**Cerca richiesta:**
```
GET /wp-json/fp-resv/v1/available-days?from=...&to=...
Status: 200
Response: { days: {...} }
```

**Se 200 OK:** ✅ API risponde!

---

## 🎯 PIANO STEP-BY-STEP

### ✅ STEP 1: VERIFICA (Priorità ALTA)

**Tempo:** 30 minuti  
**Obiettivo:** Confermare che il sistema funzioni

#### Checklist:
- [ ] Aprire form su frontend
- [ ] Cliccare campo data
- [ ] Verificare calendario Flatpickr
- [ ] Controllare date disabilitate
- [ ] Testare selezione date
- [ ] Verificare console (F12)
- [ ] Verificare network (F12)
- [ ] Testare API manualmente

**Output:** Report stato attuale

---

### 🔧 STEP 2: FIX (Se necessario - Priorità MEDIA)

**Tempo:** 1-2 ore  
**Obiettivo:** Risolvere eventuali problemi

#### Possibili Problemi & Soluzioni:

##### Problema A: API non restituisce date
**Diagnosi:**
```bash
# Test API diretto
curl "/wp-json/fp-resv/v1/available-days?from=2025-11-02&to=2025-12-02"
```

**Fix:** Verificare `Availability::findAvailableDaysForAllMeals()`

---

##### Problema B: JavaScript non carica date
**Diagnosi:**
```javascript
console.log(fpResvForm.availableDaysCache);
```

**Fix:** Verificare `loadAvailableDays()` viene chiamato

---

##### Problema C: Flatpickr non applica restrizioni
**Diagnosi:**
```javascript
console.log(fpResvForm.flatpickrInstance.config.enable);
```

**Fix:** Verificare `applyDateRestrictions()` viene chiamato

---

### 🎨 STEP 3: OTTIMIZZAZIONI UX (Opzionale - Priorità BASSA)

**Tempo:** 2-3 ore  
**Obiettivo:** Migliorare esperienza utente

#### 3.1: Styling Migliorato (30 min)

**File:** `assets/css/form.css`

```css
/* Date disabilitate più evidenti */
.flatpickr-day.flatpickr-disabled {
    background: repeating-linear-gradient(
        45deg,
        #f9fafb,
        #f9fafb 10px,
        #f3f4f6 10px,
        #f3f4f6 20px
    ) !important;
    color: #d1d5db !important;
    cursor: not-allowed !important;
    text-decoration: line-through;
}

/* Date disponibili evidenziate */
.flatpickr-day:not(.flatpickr-disabled):not(.today):not(.selected) {
    border: 1px solid #d1fae5;
    background: #f0fdf4;
}

.flatpickr-day:not(.flatpickr-disabled):hover {
    background: #d1fae5 !important;
    border-color: #10b981 !important;
}
```

---

#### 3.2: Loading Indicator (15 min)

**File:** `assets/js/fe/onepage.js`

```javascript
loadAvailableDays(meal = null) {
    // Mostra loading
    this.showCalendarLoading();
    
    fetch(url)
        .then(...)
        .finally(() => {
            this.hideCalendarLoading();
        });
}

showCalendarLoading() {
    const loader = document.createElement('div');
    loader.className = 'fp-calendar-loading';
    loader.innerHTML = '<span class="spinner"></span> Caricamento date...';
    this.dateField.parentElement.appendChild(loader);
}

hideCalendarLoading() {
    const loader = this.form.querySelector('.fp-calendar-loading');
    if (loader) loader.remove();
}
```

**CSS:**
```css
.fp-calendar-loading {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    padding: 8px 12px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-size: 14px;
    color: #666;
}
```

---

#### 3.3: Pre-caricamento Intelligente (45 min)

**File:** `assets/js/fe/onepage.js`

```javascript
async initializeForm() {
    // 1. Pre-carica date disponibili PRIMA di tutto
    await this.preloadAvailableDays();
    
    // 2. Inizializza calendario con date già pronte
    this.initializeCalendar();
    
    // 3. Resto dell'init
    // ...
}

async preloadAvailableDays() {
    return new Promise((resolve) => {
        this.loadAvailableDays();
        
        // Aspetta che loadAvailableDays finisca
        const checkInterval = setInterval(() => {
            if (!this.availableDaysLoading) {
                clearInterval(checkInterval);
                resolve();
            }
        }, 100);
    });
}
```

---

#### 3.4: Messaggio Date Disabilitate (30 min)

**HTML Template:** Aggiungere hint

```html
<div class="fp-calendar-hint">
    <span class="fp-hint-icon">ℹ️</span>
    <span class="fp-hint-text">
        Solo le date disponibili sono selezionabili.
        Date grigie = non disponibili per il servizio selezionato.
    </span>
</div>
```

---

### 📊 STEP 4: DOCUMENTAZIONE (30 min)

#### 4.1: Guida Utente
**File:** `docs/guides/user/CALENDARIO-DATE-DISABILITATE.md`

Contenuto:
- Come funziona
- Perché alcune date sono grigie
- Cosa fare se nessuna data è disponibile

#### 4.2: Guida Tecnica
**File:** `docs/guides/developer/FLATPICKR-INTEGRATION.md`

Contenuto:
- Architettura sistema
- API /available-days
- Configurazione Flatpickr
- Troubleshooting

---

## 🔍 TROUBLESHOOTING

### Problema: "Tutte le date sono disabilitate"

**Cause possibili:**
1. ❌ Nessun orario configurato in backend
2. ❌ API /available-days restituisce array vuoto
3. ❌ Meal plan configurato male

**Soluzione:**
```
1. Verifica: Admin → Impostazioni → Orari di Servizio
2. Controlla che ci siano orari configurati
3. Test API: /available-days?from=oggi&to=+90giorni
```

---

### Problema: "Tutte le date sono cliccabili"

**Cause possibili:**
1. ❌ `loadAvailableDays()` non viene chiamato
2. ❌ `applyDateRestrictions()` non viene chiamato
3. ❌ `enable: []` non viene aggiornato

**Soluzione:**
```javascript
// Console browser
console.log('Cache:', fpResvForm.availableDaysCache); // Deve essere popolato
console.log('Instance:', fpResvForm.flatpickrInstance.config.enable); // Deve avere date
```

---

### Problema: "Date sbagliate disabilitate"

**Cause possibili:**
1. ❌ Timezone sbagliato (UTC vs Europe/Rome)
2. ❌ Logica backend errata
3. ❌ Mapping giorni settimana sbagliato

**Soluzione:**
1. Verifica timezone WP: Europe/Rome
2. Test: `php tools/verify-slot-times.php`
3. Controlla logica `resolveScheduleForDay()`

---

## ✅ CHECKLIST IMPLEMENTAZIONE

### Prima di Iniziare
- [ ] Backup codice attuale
- [ ] Test sistema esistente
- [ ] Documentare comportamento attuale

### Durante Sviluppo
- [ ] Test API /available-days
- [ ] Verifica console per errori
- [ ] Test con vari meal plans
- [ ] Test su mobile
- [ ] Test su vari browser

### Dopo Implementazione
- [ ] Test completo funzionamento
- [ ] Verifica accessibilità
- [ ] Aggiornare documentazione
- [ ] Creare guida troubleshooting

---

## 📦 DELIVERABLES

### Se Sistema Funziona (Scenario A)
1. ✅ Documento verifica funzionamento
2. ✅ Guida utente calendario
3. ✅ Troubleshooting guide

### Se Necessita Fix (Scenario B)
1. ✅ Fix backend (se necessario)
2. ✅ Fix frontend (se necessario)
3. ✅ Test completi
4. ✅ Documentazione aggiornata

### Se Ottimizzazioni (Scenario C)
1. ✅ Styling CSS migliorato
2. ✅ Loading indicator
3. ✅ Pre-caricamento date
4. ✅ UX migliorata

---

## 🎯 RACCOMANDAZIONE

### 🔍 PROSSIMO PASSO IMMEDIATO:

**Eseguire STEP 1 (Verifica)** per determinare lo stato attuale:

```bash
# 1. Test API
curl "/wp-json/fp-resv/v1/available-days?from=2025-11-02&to=2025-12-02"

# 2. Apri form su browser
# 3. F12 → Console
# 4. Clicca campo data
# 5. Verifica calendario
```

**Basandoti sul risultato:**
- ✅ **Funziona:** Solo docs (Scenario A)
- ⚠️ **Non funziona:** Fix necessari (Scenario B)
- 🎨 **Funziona ma migliorabile:** Ottimizzazioni (Scenario C)

---

## 📞 SUPPORTO IMPLEMENTAZIONE

### Domande da Rispondere:

1. **Il calendario attualmente funziona?**
   - Le date si disabilitano?
   - Il caricamento è veloce?

2. **Cosa vuoi migliorare?**
   - Solo far funzionare?
   - Migliorare UX?
   - Styling?

3. **Hai preferenze?**
   - Loading indicator?
   - Date evidenziate?
   - Tooltip informativi?

---

## 🎉 CONCLUSIONE

### Sistema GIÀ Presente! ✅

Il plugin **ha già** un sistema completo di disabilitazione date:
- ✅ Backend API `/available-days`
- ✅ Frontend `loadAvailableDays()`
- ✅ Flatpickr con `enable: []`
- ✅ Refresh automatico per meal

**Dobbiamo solo:**
1. ✅ Verificare che funzioni
2. ⚠️ Eventualmente ottimizzare UX
3. 📝 Documentare

---

**Piano Creato:** 2 Novembre 2025  
**Prossimo Step:** VERIFICA funzionamento attuale  
**Tempo Stimato:** 30min - 4h (dipende dallo scenario)


