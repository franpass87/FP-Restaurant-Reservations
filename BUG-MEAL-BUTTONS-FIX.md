# 🐛 BUG PRODUZIONE: Bottoni Meal Non Funzionano
**Data:** 3 Novembre 2025  
**Tipo:** CRITICAL BUG - Production Issue
**Reporter:** Utente (Production Testing)

---

## 🚨 **BUG CONFERMATO**

**Sintomo:** Bottoni meal non rispondono al click in produzione

---

## 🔍 **3 CAUSE TROVATE**

### CAUSA #1: `user-select: none` BLOCCA CLICK ❌❌❌

**FILE:** `form-simple-inline.css` **LINEE 569-572**

```css
.fp-resv-simple button,
.fp-resv-simple input,
.fp-resv-simple select,
.fp-resv-simple textarea,
.fp-resv-simple a {
    cursor: pointer !important;
    pointer-events: auto !important;
    user-select: none !important;  /* ❌ QUESTO È IL PROBLEMA! */
    -webkit-user-select: none !important;
    -moz-user-select: none !important;
    -ms-user-select: none !important;
}
```

**PROBLEMA:**
- `user-select: none` su BUTTON può bloccare eventi click su alcuni browser (Firefox, Safari vecchi)
- Era stato aggiunto per "garantire cliccabilità" ma ha effetto opposto!
- `!important` rende impossibile override

**IMPATTO:**
- Firefox: bottoni potrebbero non rispondere
- Safari iOS: touch potrebbe non funzionare
- Chrome: solitamente OK

**CORREZIONE:**
```css
/* RIMUOVERE user-select: none dai button */
.fp-resv-simple button {
    cursor: pointer !important;
    pointer-events: auto !important;
    /* ✅ RIMUOVERE user-select: none */
}

/* Lasciare solo su input/textarea (serve per evitare selezione accidentale) */
.fp-resv-simple input,
.fp-resv-simple textarea {
    -webkit-user-select: text;
    user-select: text;
}
```

---

### CAUSA #2: FORM ID MISMATCH? ⚠️

**FILE:** `form-simple.js` **LINEA 5**

```javascript
const form = document.getElementById('fp-resv-default') || 
             document.getElementById('fp-resv-simple') || 
             document.querySelector('.fp-resv-simple');
```

**TEMPLATE:** `form-simple.php` **LINEA 45**
```php
<div id="<?php echo esc_attr($formId); ?>" class="fp-resv-simple">
```

**PROBLEMA:**
- Template genera ID dinamico: `$formId` (default: 'fp-resv-simple')
- JavaScript cerca prima 'fp-resv-default' (vecchio nome!)
- Se $formId è diverso: form NON trovato → tutto fallisce

**SCENARIO FALLIMENTO:**
```php
// Se nel config:
$config['formId'] = 'fp-resv-custom';  // Custom ID

// HTML generato:
<div id="fp-resv-custom" class="fp-resv-simple">

// JavaScript:
const form = document.getElementById('fp-resv-default')  // null
          || document.getElementById('fp-resv-simple')   // null
          || document.querySelector('.fp-resv-simple');  // ✅ TROVA per classe

// Se querySelector fallisce: form = null → CRASH
```

**CORREZIONE:**
```javascript
// Cercare PRIMA per classe (più affidabile)
const form = document.querySelector('.fp-resv-simple') ||
             document.getElementById('fp-resv-simple') ||
             document.getElementById('fp-resv-default');

// O meglio: usare data-attribute
const form = document.querySelector('[data-fp-resv-form]');
```

---

### CAUSA #3: MANCA NULL CHECK dopo querySelectorAll ⚠️

**LINEA 55-61:**
```javascript
let mealBtns = form.querySelectorAll('.fp-meal-btn');

function setupMealButtons() {
    mealBtns = form.querySelectorAll('.fp-meal-btn');
    console.log('Trovati', mealBtns.length, 'pulsanti pasto');
    
    mealBtns.forEach(btn => {
        btn.addEventListener('click', function() { ... });
    });
}
```

**PROBLEMA:**
```javascript
// Se form = null (non trovato):
form.querySelectorAll('.fp-meal-btn')
// 💥 CRASH: Cannot read property 'querySelectorAll' of null

// O se meals array vuoto nel PHP:
mealBtns.length = 0
// forEach non attacca nessun listener
// Bottoni esistono ma non cliccabili!
```

**CORREZIONE:**
```javascript
function setupMealButtons() {
    if (!form) {
        console.error('❌ Form non trovato, impossibile setup meal buttons');
        return;
    }
    
    mealBtns = form.querySelectorAll('.fp-meal-btn');
    console.log('Trovati', mealBtns.length, 'pulsanti pasto');
    
    if (mealBtns.length === 0) {
        console.warn('⚠️ NESSUN bottone meal trovato! Verifica $meals in PHP');
        return;
    }
    
    mealBtns.forEach(btn => {
        btn.addEventListener('click', function() { ... });
    });
}
```

---

## 🔧 **DEBUGGING IN PRODUZIONE**

### Test 1: Apri Console Browser (F12)

Cerca questi messaggi:
```javascript
✅ "🚀 JavaScript del form caricato!"
✅ "DOM caricato, inizializzo form..."
✅ "Form trovato: <div...>"
✅ "Trovati X pulsanti pasto"  // X deve essere > 0!

❌ "Form non trovato!"  // SE vedi questo: form non trovato
❌ "Trovati 0 pulsanti pasto"  // SE vedi questo: bottoni non esistono
```

### Test 2: Clicca Bottone Meal

Cerca:
```javascript
✅ "Pulsante pasto cliccato: pranzo"  // Deve apparire!

❌ NESSUN messaggio  // Listener non attaccato
❌ "TypeError: ..."  // JavaScript crash
```

### Test 3: Controlla Elementi

Console browser:
```javascript
// Verifica form
document.querySelector('.fp-resv-simple')
// Deve ritornare: <div id="...">
// Se ritorna: null → PROBLEMA!

// Verifica bottoni
document.querySelectorAll('.fp-meal-btn')
// Deve ritornare: NodeList[2] { <button>, <button> }
// Se ritorna: NodeList[] (vuoto) → PROBLEMA!

// Verifica click manuale
const btn = document.querySelector('.fp-meal-btn');
btn.click();  // Simula click
// Deve stampare "Pulsante pasto cliccato"
```

---

## ✅ **FIX IMMEDIATO**

<function_calls>
<invoke name="search_replace">
<parameter name="file_path">wp-content/plugins/FP-Restaurant-Reservations/assets/css/form-simple-inline.css
