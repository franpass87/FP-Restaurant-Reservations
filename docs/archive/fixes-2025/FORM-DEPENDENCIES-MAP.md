# Mappa delle Dipendenze - Form Frontend

**Generato**: 2025-10-19  
**File analizzato**: `templates/frontend/form.php` + JavaScript

---

## 📊 Data Attributes Critici (NON modificare)

### **Selettori principali JavaScript**

| Data Attribute | Usato da | Scopo | File JS |
|----------------|----------|-------|---------|
| `data-fp-resv-app` | Root widget | Inizializza l'app | init.js, form-app-*.js |
| `data-fp-resv-form` | Form element | Riferimento al form | onepage.js:58 |
| `data-fp-resv-section` | Ogni step | Navigazione tra step | onepage.js:60, dom-helpers.js:26 |
| `data-fp-resv-steps` | Container steps | Wrapper degli step | form.php:234 |

### **Campi del form**

| Data Attribute | Campo | Validazione | File |
|----------------|-------|-------------|------|
| `data-fp-resv-field="date"` | Input data | Required, future dates | onepage.js:93, form-validation.js:26 |
| `data-fp-resv-field="time"` | Hidden time | Required (via JS) | onepage.js:165, form-validation.js:38 |
| `data-fp-resv-field="party"` | Numero persone | Min 1, Max 40 | onepage.js:94 |
| `data-fp-resv-field="first_name"` | Nome | Required | form-validation.js:134 |
| `data-fp-resv-field="last_name"` | Cognome | Required | form-validation.js:135 |
| `data-fp-resv-field="email"` | Email | Required, email format | form-validation.js:136 |
| `data-fp-resv-field="phone"` | Telefono | Required, phone format | onepage.js:96, form-validation.js:137 |
| `data-fp-resv-field="phone_prefix"` | Prefisso tel | - | onepage.js:97 |
| `data-fp-resv-field="occasion"` | Occasione | Optional | form.php:526 |
| `data-fp-resv-field="notes"` | Note | Optional | form.php:543 |
| `data-fp-resv-field="allergies"` | Allergie | Optional | form.php:551 |
| `data-fp-resv-field="high_chair_count"` | Seggioloni | Optional | form.php:569 |
| `data-fp-resv-field="wheelchair_table"` | Accessibilità | Optional | form.php:575 |
| `data-fp-resv-field="pets"` | Animali | Optional | form.php:579 |
| `data-fp-resv-field="consent"` | Consenso GDPR | Required | form-validation.js:138 |
| `data-fp-resv-field="marketing_consent"` | Marketing | Optional | form.php:605 |
| `data-fp-resv-field="profiling_consent"` | Profilazione | Optional | form.php:614 |

### **Navigazione**

| Data Attribute | Elemento | Azione |
|----------------|----------|--------|
| `data-fp-resv-nav="prev"` | Bottone Indietro | Va allo step precedente |
| `data-fp-resv-nav="next"` | Bottone Continua | Va allo step successivo |
| `data-fp-resv-progress` | Barra progresso | Aggiorna stato step |

### **Meals (servizi)**

| Data Attribute | Elemento | Scopo |
|----------------|----------|-------|
| `data-fp-resv-meals` | Container meals | Wrapper dei pulsanti meal |
| `data-fp-resv-meal="lunch/dinner"` | Bottoni meal | Selezione servizio (onepage.js:242, MealManager.js:53) |
| `data-fp-resv-meal-notice` | Paragrafo notice | Mostra avviso per meal selezionato |
| `data-fp-resv-meal-notice-text` | Testo notice | Contenuto dinamico notice |

### **Slots orari**

| Data Attribute | Elemento | Scopo |
|----------------|----------|-------|
| `data-fp-resv-slots` | Container slots | Root modulo disponibilità (availability.js:33) |
| `data-fp-resv-slots-status` | Messaggio stato | "Caricamento..." / "Seleziona un orario" |
| `data-fp-resv-slots-list` | Lista <ul> | Contiene i bottoni degli orari |
| `data-fp-resv-slots-empty` | Messaggio vuoto | "Nessun orario disponibile" |
| `data-fp-resv-slots-legend` | Legenda colori | Mostra disponibilità/limitato/pieno |
| `data-fp-resv-slots-boundary` | Alert errore | Errore caricamento slot |
| `data-fp-resv-slots-boundary-message` | Testo errore | Messaggio errore specifico |
| `data-fp-resv-slots-retry` | Bottone riprova | Ricarica gli slot |
| `data-fp-resv-availability-indicator` | Indicatore | Stato disponibilità generale |

### **Summary (riepilogo)**

| Data Attribute | Elemento | Valore Popolato |
|----------------|----------|-----------------|
| `data-fp-resv-summary` | Container | Wrapper summary |
| `data-fp-resv-summary="date"` | <dd> | Data formattata |
| `data-fp-resv-summary="time"` | <dd> | Orario selezionato |
| `data-fp-resv-summary="party"` | <dd> | Numero persone |
| `data-fp-resv-summary="name"` | <dd> | Nome Cognome |
| `data-fp-resv-summary="contact"` | <dd> | Email e telefono |
| `data-fp-resv-summary="occasion"` | <dd> | Occasione (se presente) |
| `data-fp-resv-summary="notes"` | <dd> | Note e allergie |
| `data-fp-resv-summary="extras"` | <dd> | Richieste extra |
| `data-fp-resv-summary-occasion-row` | <div> | Riga occasione (hidden se vuoto) |

### **Submit e feedback**

| Data Attribute | Elemento | Scopo |
|----------------|----------|-------|
| `data-fp-resv-submit` | Bottone submit | Invia prenotazione |
| `data-fp-resv-submit-label` | <span> | Testo bottone (cambia stato) |
| `data-fp-resv-submit-spinner` | <span> | Spinner caricamento |
| `data-fp-resv-submit-hint` | <p> | Hint sotto bottone |
| `data-fp-resv-sticky-cta` | Container | Wrapper submit fisso |
| `data-fp-resv-success` | Alert | Messaggio successo |
| `data-fp-resv-error` | Alert | Messaggio errore |
| `data-fp-resv-error-message` | <p> | Testo errore |
| `data-fp-resv-error-retry` | Bottone | Riprova dopo errore |

### **Party size**

| Data Attribute | Elemento | Azione |
|----------------|----------|--------|
| `data-fp-resv-party-decrement` | Bottone - | Decrementa persone (onepage.js:569) |
| `data-fp-resv-party-increment` | Bottone + | Incrementa persone (onepage.js:570) |

### **Errori di validazione**

| Data Attribute | Elemento | Scopo |
|----------------|----------|-------|
| `data-fp-resv-error="first_name"` | <small> | Errore nome |
| `data-fp-resv-error="last_name"` | <small> | Errore cognome |
| `data-fp-resv-error="email"` | <small> | Errore email |
| `data-fp-resv-error="phone"` | <small> | Errore telefono |
| `data-fp-resv-error="consent"` | <small> | Errore consenso |
| `data-fp-resv-date-status` | <small> | Status picker data |

### **Tracking / Analytics**

| Data Attribute | Elemento | Scopo |
|----------------|----------|-------|
| `data-fp-resv-event` | Elementi cliccabili | Nome evento analytics |
| `data-fp-resv-label` | Elementi cliccabili | Label evento |
| `data-fp-resv-payload` | - | Payload JSON personalizzato |
| `data-fp-resv-start` | Form | Evento inizio compilazione |

---

## 🎨 Classi CSS Funzionali (NON solo estetiche)

### **Classi con logica JavaScript**

| Classe CSS | Usata per | File JS |
|------------|-----------|---------|
| `.fp-meal-pill[data-active="true"]` | Meal selezionato | onepage.js (toggle active) |
| `.fp-resv-slots__list button[aria-pressed="true"]` | Slot selezionato | availability.js |
| `.fp-input:invalid` | Validazione | form-validation.js |
| `[hidden]` | Visibilità elementi | Ovunque (JS toglie/aggiunge) |

### **Classi per stati**

| Classe | Stato | Dove |
|--------|-------|------|
| `[data-state="active"]` | Step attivo | Step corrente |
| `[data-state="completed"]` | Step completato | Step passati |
| `[data-state="locked"]` | Step bloccato | Step futuri |

---

## 🔗 Dipendenze PHP → JavaScript

### **Dataset iniziale**

Il template PHP inietta configurazione via `data-fp-resv` attribute:

```php
// form.php:116
<div data-fp-resv="<?php echo esc_attr($datasetJson); ?>">
```

**Contenuto del dataset:**
```json
{
  "config": {
    "formId": "...",
    "location": "...",
    "defaults": {...}
  },
  "strings": {
    "fields": {...},
    "messages": {...},
    "steps": {...}
  },
  "steps": [...],
  "events": {...},
  "privacy": {...},
  "meals": [...]
}
```

Letto da: `assets/js/fe/utils/data.js:parseDataset()`

---

## 📝 Ordine degli Step

**Definito in**: `assets/js/fe/constants.js:STEP_ORDER`

```javascript
const STEP_ORDER = ['service', 'date', 'party', 'slots', 'details', 'confirm'];
```

**Nel PHP**: Switch case in `form.php:265-662`

Gli step DEVONO avere `data-step` corrispondente:
- `data-step="service"`
- `data-step="date"`
- `data-step="party"`
- `data-step="slots"`
- `data-step="details"`
- `data-step="confirm"`

---

## ⚠️ ATTENZIONE: Non Rimuovere

### **Data attributes CRITICI**

Se rimuovi uno di questi, il form si rompe:

1. `data-fp-resv-app` - App non si inizializza
2. `data-fp-resv-form` - Form non trovato
3. `data-fp-resv-section` - Navigazione rotta
4. `data-fp-resv-field` - Validazione rotta
5. `data-fp-resv-meal` - Selezione meal rotta
6. `data-fp-resv-slots-list` - Slot non caricano
7. `data-fp-resv-submit` - Submit non funziona

### **Campi hidden CRITICI**

Non rimuovere questi input:
- `fp_resv_meal` (meal selezionato)
- `fp_resv_price_per_person` (prezzo)
- `fp_resv_time` (orario - populated by JS)
- `fp_resv_slot_start` (slot ISO)
- `fp_resv_phone_e164` (telefono formato E164)
- `fp_resv_phone_cc` (country code)
- `fp_resv_phone_local` (numero locale)
- `fp_resv_nonce` (WordPress security)

---

## 🔄 Flusso di Validazione

```
User compila campo
    ↓
Blur event → onepage.js:718
    ↓
form-validation.js:validateField()
    ↓
Mostra/nasconde errore → data-fp-resv-error="field_name"
    ↓
form-validation.js:isFormValid()
    ↓
Abilita/disabilita submit
```

---

## 📡 Endpoint API

**Definito in**: `form.php:127`

```php
action="<?php echo esc_url(rest_url('fp-resv/v1/reservations')); ?>"
```

**Chiamato da**: `onepage.js` (metodo submit)

---

## 🧩 Moduli JavaScript

### **File principali**

1. **init.js** - Inizializza i widget
2. **onepage.js** - App principale (2700+ righe)
3. **availability.js** - Gestisce slot orari
4. **phone.js** - Validazione telefono
5. **form-validation.js** - Validazione campi
6. **form-navigation.js** - Navigazione step
7. **form-state.js** - Stato del form
8. **MealManager.js** - Gestione meals

### **Dipendenze tra moduli**

```
init.js
  └── onepage.js (FormApp)
        ├── form-validation.js
        ├── form-navigation.js
        ├── form-state.js
        ├── MealManager.js
        └── availability.js
              └── slots-renderer.js
```

---

## ✅ Conclusioni

### **Modifiche sicure:**
- ✅ Aggiungere classi CSS estetiche
- ✅ Modificare testi e label
- ✅ Aggiungere campi opzionali (con `data-fp-resv-field`)
- ✅ Cambiare ordine campi DENTRO lo stesso step
- ✅ Modificare placeholder

### **Modifiche rischiose:**
- ⚠️ Rimuovere data attributes
- ⚠️ Cambiare ID campi hidden
- ⚠️ Modificare struttura step
- ⚠️ Cambiare ordine degli step

### **Modifiche pericolose:**
- ❌ Rimuovere campi required
- ❌ Cambiare nomi input (name="fp_resv_*")
- ❌ Modificare data-step values
- ❌ Rimuovere nonce field
