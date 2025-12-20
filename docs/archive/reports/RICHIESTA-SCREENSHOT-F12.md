# 🔬 RICHIESTA SCREENSHOT F12 - URGENTE

**Situazione:** Bordo verde visibile MA asterischi e checkbox ancora errati  
**Conclusione:** Salient modifica gli inline styles con JavaScript o ha specificità impossibile

---

## 📸 **SCREENSHOT RICHIESTI**

### Screenshot 1: CSS Asterisco

**PROCEDURA:**
```
1. Apri la pagina del form
2. F12 (Developer Tools)
3. Tab "Elements"
4. Click destro su un asterisco rosso * → "Inspect" (Ispeziona)
5. Nella tab "Styles" (a destra), fai screenshot di TUTTO
```

**COSA DEVO VEDERE:**
- Tutti i blocchi CSS applicati all'asterisco
- Quali proprietà sono barrate (crossed out)
- Quale CSS "vince" (quello più in alto)

---

### Screenshot 2: CSS Checkbox

**PROCEDURA:**
```
1. F12 → Elements
2. Click destro su un checkbox → "Inspect"
3. Tab "Styles", fai screenshot di TUTTO
```

**COSA DEVO VEDERE:**
- Tutti i blocchi CSS del checkbox
- Width/height applicati
- Display property

---

### Screenshot 3: CSS Checkbox Wrapper

**PROCEDURA:**
```
1. F12 → Elements
2. Click sul DIV che contiene checkbox + label
3. Dovrebbe avere class="fp-checkbox-wrapper"
4. Tab "Styles", fai screenshot
```

**COSA DEVO VEDERE:**
- `display: flex` applicato?
- `flex-direction: row` applicato?
- Quali proprietà sono barrate?

---

### Screenshot 4: HTML Asterisco

**PROCEDURA:**
```
1. F12 → Elements
2. Trova un <abbr class="fp-required">
3. Fai screenshot del TAG HTML completo
```

**COSA DEVO VEDERE:**
```html
<abbr class="fp-required" 
      style="display:inline!important;white-space:nowrap!important;..."
      title="Obbligatorio">*</abbr>
```

Verifica che l'attribute `style` sia presente!

---

### Screenshot 5: Computed Styles Asterisco

**PROCEDURA:**
```
1. F12 → Elements
2. Click su asterisco rosso *
3. Tab "Computed" (accanto a "Styles")
4. Cerca proprietà:
   - display
   - white-space
   - float
   - position
5. Screenshot di queste 4 proprietà
```

**COSA DEVO VEDERE:**
- `display: inline` (non block, non flex)
- `white-space: nowrap` (non normal)

---

## 🎯 **INFORMAZIONI CHIAVE**

Dalle screenshot capirò:

1. **Se inline styles sono presenti nell'HTML**
   - Se SI → Salient li sovrascrive con JavaScript
   - Se NO → File non salvato o cache

2. **Quale CSS "vince"**
   - Se vedo Salient CSS sopra inline styles → IMPOSSIBILE
   - Se vedo inline styles barrati → Salient usa JavaScript

3. **Computed values**
   - Valore finale applicato dal browser
   - Ignora tutto e mostra la realtà

---

## 📊 **ALTERNATIVE SE INLINE NON FUNZIONA**

### Opzione A: JavaScript che forza style
```javascript
// Dopo DOM ready, forza inline styles
document.querySelectorAll('abbr.fp-required').forEach(el => {
    el.style.setProperty('display', 'inline', 'important');
    el.style.setProperty('white-space', 'nowrap', 'important');
});
```

### Opzione B: Disabilita JavaScript Salient
```php
// functions.php o mu-plugin
add_action('wp_print_scripts', function() {
    wp_dequeue_script('salient-main');
}, 999);
```

### Opzione C: Iframe isolato
```html
<!-- Carica form in iframe isolato da Salient -->
<iframe src="/form-standalone" style="width:100%;border:none;"></iframe>
```

---

## 🆘 **COSA FARE ORA**

### Step 1: Fai i 5 screenshot richiesti

### Step 2: Dimmi anche:
- Browser usato? (Chrome, Edge, Firefox, Safari?)
- Versione browser?
- Sistema operativo?

### Step 3: Mandami screenshot + info

---

## 💡 **TEORIA**

Se inline styles non funzionano, le possibilità sono SOLO 2:

**Possibilità A:** Salient JavaScript
```javascript
// Salient esegue dopo il nostro:
document.querySelectorAll('abbr').forEach(el => {
    el.style.display = 'block'; // Sovrascrive inline!
});
```

**Possibilità B:** CSS `!important` più forte
```
IMPOSSIBILE! Inline + !important = massima specificità.
```

---

**Dalle screenshot capirò ESATTAMENTE cosa sta succedendo e avrò la soluzione definitiva!** 🔬

**Autore:** AI Assistant  
**Richiesta:** 5 screenshot F12  
**Urgenza:** 🔴 MASSIMA

