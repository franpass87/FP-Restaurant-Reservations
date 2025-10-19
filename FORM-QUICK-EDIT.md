# Guida Rapida: Modifiche al Form

**Per**: Sviluppatori che devono fare modifiche veloci  
**Tempo lettura**: 5 minuti  
**Prerequisiti**: Conoscenza base PHP e HTML

---

## 🎯 Casi d'Uso Comuni

### ✏️ **Modificare una Label**

**Dove**: File step specifico

```bash
# Trova il campo
grep -r "fields\['email'\]" templates/frontend/form-parts/

# Output: templates/frontend/form-parts/steps/step-details.php:48
```

```php
// Modifica
<span><?php echo esc_html($strings['fields']['email'] ?? 'Email'); ?></span>

// In
<span><?php echo esc_html($strings['fields']['email'] ?? 'Indirizzo Email'); ?></span>
```

---

### ➕ **Aggiungere un Placeholder**

```php
// PRIMA
<input class="fp-input" type="email" name="fp_resv_email">

// DOPO
<input 
    class="fp-input" 
    type="email" 
    name="fp_resv_email"
    placeholder="<?php echo esc_attr__('es. mario.rossi@example.com', 'fp-restaurant-reservations'); ?>"
>
```

---

### 📝 **Aggiungere Hint sotto un Campo**

```php
<input type="text" name="fp_resv_promo_code">

<!-- Aggiungi dopo l'input -->
<small class="fp-hint">
    <?php echo esc_html__('Inserisci il codice ricevuto via email', 'fp-restaurant-reservations'); ?>
</small>
```

---

### 🆕 **Aggiungere un Nuovo Campo**

**File**: `templates/frontend/form-parts/steps/step-details.php`

```php
<!-- Copia un campo esistente come template -->
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Nome Azienda (opzionale)', 'fp-restaurant-reservations'); ?></span>
    <input 
        class="fp-input" 
        type="text" 
        name="fp_resv_company"
        data-fp-resv-field="company"
        autocomplete="organization"
    >
    <small class="fp-hint">
        <?php echo esc_html__('Per cene aziendali', 'fp-restaurant-reservations'); ?>
    </small>
</label>
```

**Importante**:
- `name="fp_resv_*"` (con prefisso)
- `data-fp-resv-field="company"` (senza prefisso)
- Se required, aggiungi `required`

---

### ❌ **Rimuovere un Campo Opzionale**

```php
// Metodo 1: Commenta
<?php /*
<label class="fp-resv-field fp-field">
    <!-- Campo da nascondere -->
</label>
*/ ?>

// Metodo 2: Condizionale
<?php if (false) : ?>
    <label>...</label>
<?php endif; ?>
```

**⚠️ Non rimuovere campi required** (nome, email, telefono, consenso) senza aggiornare la validazione JS!

---

### 🔀 **Cambiare Ordine Campi**

Taglia e incolla i blocchi `<label>...</label>`:

```php
// PRIMA
<label>Campo A</label>
<label>Campo B</label>
<label>Campo C</label>

// DOPO (B, A, C)
<label>Campo B</label>
<label>Campo A</label>
<label>Campo C</label>
```

Non serve modificare altro!

---

### 🎨 **Cambiare Stile di un Campo**

Aggiungi classe custom:

```php
<label class="fp-resv-field fp-field mia-classe-custom">
    <span>Campo</span>
    <input class="fp-input">
</label>
```

Poi in CSS:

```css
/* In assets/css/form-thefork.css o custom CSS */
.mia-classe-custom input {
    border-color: #ff6b6b;
    background: #fff5f5;
}
```

---

### 📏 **Modificare Lunghezza Campo**

```php
<!-- Textarea: rows -->
<textarea rows="5"></textarea>  <!-- Prima: 3, Dopo: 5 -->

<!-- Input numero: min/max -->
<input type="number" min="0" max="10">  <!-- Prima: max="5" -->

<!-- Input testo: maxlength -->
<input type="text" maxlength="100">
```

---

### 🔢 **Cambiare Numero Persone Default**

**File**: `templates/frontend/form-parts/steps/step-party.php`

```php
// Riga ~7
$defaultPartySize = isset($config['defaults']['partySize']) ? (int) $config['defaults']['partySize'] : 2;

// Cambia a 4
$defaultPartySize = isset($config['defaults']['partySize']) ? (int) $config['defaults']['partySize'] : 4;
```

Oppure modifica la config backend (preferibile).

---

### 📅 **Modificare Data Minima**

**File**: `templates/frontend/form-parts/steps/step-date.php`

```php
// PRIMA - oggi
<input type="date" min="<?php echo esc_attr(date('Y-m-d')); ?>">

// DOPO - domani
<input type="date" min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>">

// DOPO - tra 3 giorni
<input type="date" min="<?php echo esc_attr(date('Y-m-d', strtotime('+3 days'))); ?>">
```

---

### ✅ **Rendere un Campo Required/Optional**

```php
<!-- Optional → Required -->
<input name="fp_resv_notes">
<input name="fp_resv_notes" required>  <!-- Aggiungi required -->

<!-- Required → Optional -->
<input name="fp_resv_email" required>
<input name="fp_resv_email">  <!-- Rimuovi required -->
```

**⚠️ Attenzione**: Se rendi optional un campo required di default, verifica che il backend lo gestisca.

---

### 🎭 **Campo Condizionale**

Mostra campo solo se una condizione è vera:

```php
<!-- Solo per utenti loggati -->
<?php if (is_user_logged_in()) : ?>
    <label>Campo VIP</label>
<?php endif; ?>

<!-- Solo se feature abilitata -->
<?php if (!empty($config['features']['gift_cards'])) : ?>
    <label>Codice Regalo</label>
<?php endif; ?>

<!-- Solo in date specifiche -->
<?php if (date('m-d') === '12-24') : ?>
    <label>Menu Natale</label>
<?php endif; ?>
```

---

### 🌐 **Tradurre un Testo**

```php
// Testo hardcoded → Traducibile
<span>Nome</span>

<span><?php echo esc_html__('Nome', 'fp-restaurant-reservations'); ?></span>

// Con variabile
<span><?php echo esc_html__('Benvenuto', 'fp-restaurant-reservations'); ?></span>

// Con printf (placeholder)
<span>
    <?php printf(
        esc_html__('Prenota per %s persone', 'fp-restaurant-reservations'),
        $partySize
    ); ?>
</span>
```

---

## 📍 Dove Trovare i File

```
templates/frontend/form-parts/steps/
├── step-service.php     → Pulsanti Pranzo/Cena
├── step-date.php        → Campo data
├── step-party.php       → Numero persone (+/-)
├── step-slots.php       → Orari disponibili
├── step-details.php     → Nome, Email, Telefono, Note, etc.
└── step-confirm.php     → Riepilogo finale
```

**Regola**: Se non sai dove trovare un campo, cerca con grep:

```bash
grep -r "field_name" templates/frontend/form-parts/
grep -r "email" templates/frontend/form-parts/
```

---

## 🔍 Trovare un Campo

### **Metodo 1: Cerca per nome HTML**

```bash
grep -r 'name="fp_resv_email"' templates/
```

### **Metodo 2: Cerca per label**

```bash
grep -r "Allergie" templates/frontend/form-parts/
```

### **Metodo 3: Cerca per data-attribute**

```bash
grep -r 'data-fp-resv-field="phone"' templates/
```

---

## ⚙️ Pattern Veloci

### **Campo Testo Standard**

```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Label', 'fp-restaurant-reservations'); ?></span>
    <input 
        class="fp-input" 
        type="text" 
        name="fp_resv_field_name"
        data-fp-resv-field="field_name"
    >
</label>
```

### **Campo Email**

```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Email', 'fp-restaurant-reservations'); ?></span>
    <input 
        class="fp-input" 
        type="email" 
        name="fp_resv_email"
        data-fp-resv-field="email"
        required
        autocomplete="email"
    >
    <small class="fp-error" data-fp-resv-error="email" hidden></small>
</label>
```

### **Campo Numero**

```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Età', 'fp-restaurant-reservations'); ?></span>
    <input 
        class="fp-input" 
        type="number" 
        name="fp_resv_age"
        data-fp-resv-field="age"
        min="18"
        max="120"
        inputmode="numeric"
    >
</label>
```

### **Campo Textarea**

```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Note', 'fp-restaurant-reservations'); ?></span>
    <textarea 
        class="fp-textarea" 
        name="fp_resv_notes"
        data-fp-resv-field="notes"
        rows="3"
        placeholder="<?php echo esc_attr__('Inserisci le tue note', 'fp-restaurant-reservations'); ?>"
    ></textarea>
</label>
```

### **Campo Select**

```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html__('Provincia', 'fp-restaurant-reservations'); ?></span>
    <select 
        class="fp-input" 
        name="fp_resv_province"
        data-fp-resv-field="province"
    >
        <option value=""><?php echo esc_html__('Seleziona', 'fp-restaurant-reservations'); ?></option>
        <option value="MI"><?php echo esc_html__('Milano', 'fp-restaurant-reservations'); ?></option>
        <option value="RM"><?php echo esc_html__('Roma', 'fp-restaurant-reservations'); ?></option>
    </select>
</label>
```

### **Campo Checkbox**

```php
<label class="fp-resv-field fp-field fp-resv-field--checkbox">
    <input 
        class="fp-checkbox" 
        type="checkbox" 
        name="fp_resv_newsletter" 
        value="1"
        data-fp-resv-field="newsletter"
    >
    <span><?php echo esc_html__('Iscriviti alla newsletter', 'fp-restaurant-reservations'); ?></span>
</label>
```

---

## ⚠️ Cose da NON Fare

❌ **Non modificare questi attributi:**
- `data-fp-resv-*` (usati da JavaScript)
- `name="fp_resv_*"` (prefix required)
- ID dei campi hidden (nonce, meal, time, etc.)

❌ **Non rimuovere classi funzionali:**
- `.fp-input`, `.fp-textarea`, `.fp-checkbox`
- `.fp-error`, `.fp-hint`
- `[data-fp-resv-field]`

❌ **Non cambiare questi campi senza sapere cosa fai:**
- Hidden fields (fp_resv_meal, fp_resv_time, etc.)
- Nonce field
- Data attributes su submit button

---

## ✅ Checklist Post-Modifica

Dopo ogni modifica, verifica:

1. [ ] Sintassi PHP corretta (`php -l file.php`)
2. [ ] Escape output (`esc_html`, `esc_attr`, `esc_url`)
3. [ ] Data attributes presenti su campi input
4. [ ] Form si visualizza (no errori PHP)
5. [ ] Validazione funziona
6. [ ] Submit funziona
7. [ ] Nessun errore console JavaScript

---

## 🆘 Quick Fix

### **Form non si visualizza**

```bash
# Controlla errori PHP
tail -f wp-content/debug.log

# Verifica sintassi
php -l templates/frontend/form.php
php -l templates/frontend/form-parts/steps/*.php
```

### **Campo non valida**

Verifica:
1. Attributo `required` presente?
2. Attributo `type` corretto (email, tel, number)?
3. `data-fp-resv-field="..."` presente?

### **CSS non si applica**

Verifica ordine specificità:
```css
/* Specifica il contesto */
.fp-resv-widget .fp-field input { }

/* Più specifico */
.fp-resv-widget .fp-resv-field--email .fp-input { }

/* Usa !important solo se inevitabile */
.mia-classe { color: red !important; }
```

---

## 📚 Risorse

- **Documentazione completa**: `FORM-ARCHITECTURE.md`
- **Dipendenze**: `FORM-DEPENDENCIES-MAP.md`
- **Piano refactoring**: `PIANO-REFACTORING-FORM.md`

---

**Domande?** Cerca prima con `grep`, poi chiedi! 🔍
