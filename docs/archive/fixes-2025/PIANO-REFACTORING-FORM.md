# Piano di Refactoring Form Frontend

**Data**: 2025-10-19  
**Obiettivo**: Rendere il form facilmente modificabile, mantenibile e testabile  
**File target**: `templates/frontend/form.php` (712 righe → ~200 righe)

---

## 🎯 Obiettivi Principali

1. **Modularizzazione**: Dividere il monolite in componenti riutilizzabili
2. **Semplificazione HTML**: Ridurre nidificazione (max 3-4 livelli)
3. **Gestione CSS corretta**: Eliminare iniezione JavaScript
4. **Manutenibilità**: Rendere facile aggiungere/modificare campi
5. **Documentazione**: Guide chiare per modifiche future

---

## 📋 Piano in 7 Fasi

### **FASE 1: Analisi e Preparazione** (30 min)

#### Task 1.1: Mappare le dipendenze
- [ ] Analizzare interazioni tra `form.php` ↔ JavaScript (`form-state.js`, `form-validation.js`, `form-navigation.js`)
- [ ] Identificare tutti i `data-fp-resv-*` attributes usati dal JS
- [ ] Documentare quali classi CSS sono usate per funzionalità (non solo stile)

#### Task 1.2: Setup struttura
```bash
mkdir -p templates/frontend/form-parts
mkdir -p templates/frontend/form-parts/steps
mkdir -p templates/frontend/form-parts/components
```

**Struttura target:**
```
templates/frontend/
├── form.php (principale, ridotto)
├── form-parts/
│   ├── header.php
│   ├── progress.php
│   ├── alerts.php
│   ├── components/
│   │   ├── field-input.php
│   │   ├── field-textarea.php
│   │   ├── field-select.php
│   │   ├── field-checkbox.php
│   │   └── field-phone.php
│   └── steps/
│       ├── step-service.php
│       ├── step-date.php
│       ├── step-party.php
│       ├── step-slots.php
│       ├── step-details.php
│       └── step-confirm.php
```

---

### **FASE 2: Modularizzazione Steps** (2-3 ore)

Estrarre ogni step in un file separato, uno alla volta, testando dopo ogni estrazione.

#### Task 2.1: Estrarre step "service" (meals)
```php
// templates/frontend/form-parts/steps/step-service.php
<?php
/**
 * Step: Service Selection (Meals)
 * @var array $context
 * @var array $meals
 * @var string $formId
 */
?>
<section class="fp-meals" data-fp-resv-meals>
    <!-- contenuto dello step service -->
</section>
```

**Nel form.php principale:**
```php
case 'service':
    include __DIR__ . '/form-parts/steps/step-service.php';
    break;
```

#### Task 2.2-2.6: Estrarre gli altri steps
Ripetere lo stesso pattern per:
- `step-date.php`
- `step-party.php`
- `step-slots.php`
- `step-details.php`
- `step-confirm.php`

**Vantaggi:**
- Ogni step è un file di max 100 righe
- Facile trovare e modificare
- Possibilità di riutilizzare in altri contesti
- Testing isolato

---

### **FASE 3: Semplificazione HTML** (2 ore)

Ridurre la nidificazione eccessiva mantenendo funzionalità e accessibilità.

#### Task 3.1: Refactor step "slots"

**PRIMA (troppo nidificato):**
```html
<div class="fp-resv-slots">
  <aside class="fp-resv-slots__legend-container">
    <ul class="fp-meals__legend fp-resv-slots__legend">
      <!-- 3 <li> nidificati -->
    </ul>
  </aside>
  <div class="fp-resv-slots__feedback">
    <p class="fp-resv-slots__status"></p>
    <p class="fp-resv-slots__indicator"></p>
  </div>
  <div class="fp-resv-slots__container">
    <ul class="fp-resv-slots__list"></ul>
  </div>
  <div class="fp-resv-slots__messages">
    <!-- messaggi -->
  </div>
</div>
```

**DOPO (semplificato):**
```html
<div class="fp-slots" data-fp-resv-slots>
  <ul class="fp-slots__legend" data-fp-slots-legend hidden>
    <!-- legenda piatta -->
  </ul>
  <p class="fp-slots__status" data-fp-slots-status></p>
  <ul class="fp-slots__list" data-fp-slots-list></ul>
  <p class="fp-slots__empty" data-fp-slots-empty hidden></p>
</div>
```

Riduzione: da 4-5 livelli a 2 livelli di nidificazione.

#### Task 3.2: Semplificare step "details"
- Rimuovere wrapper non necessari
- Usare CSS Grid invece di wrapper per layout
- Consolidare campi simili

---

### **FASE 4: Gestione CSS Corretta** (1 ora)

Eliminare l'iniezione JavaScript del CSS.

#### Task 4.1: Creare funzione PHP per CSS inline

```php
// src/Frontend/Assets.php o simile
public function render_inline_styles($css, $handle = 'fp-resv-inline') {
    if (empty($css)) {
        return;
    }
    
    // Sanitize CSS
    $css = wp_strip_all_tags($css);
    
    // Output usando wp_add_inline_style se disponibile
    if (wp_style_is('fp-restaurant-reservations', 'enqueued')) {
        wp_add_inline_style('fp-restaurant-reservations', $css);
    } else {
        // Fallback: output diretto con protezione
        echo '<style id="' . esc_attr($handle) . '" type="text/css">' . "\n";
        echo $css . "\n";
        echo '</style>' . "\n";
    }
}
```

#### Task 4.2: Sostituire nel template

**PRIMA:**
```php
<script>
(function() {
    var css = '<?php echo $escapedCss; ?>';
    // ...inject via JS
})();
</script>
```

**DOPO:**
```php
<?php
if ($styleCss !== '') {
    $assets = new \FP\Restaurant\Reservations\Frontend\Assets();
    $assets->render_inline_styles($styleCss, $styleId);
}
?>
```

---

### **FASE 5: Helper Functions per Campi** (2 ore)

Creare helper per evitare duplicazione di markup.

#### Task 5.1: Creare file helper

```php
// templates/frontend/form-parts/components/field-helpers.php

function fp_render_input_field($args) {
    $defaults = [
        'type' => 'text',
        'name' => '',
        'label' => '',
        'required' => false,
        'autocomplete' => '',
        'hint' => '',
        'class' => '',
        'data_attr' => '',
    ];
    
    $args = wp_parse_args($args, $defaults);
    ?>
    <label class="fp-field <?php echo esc_attr($args['class']); ?>">
        <span><?php echo esc_html($args['label']); ?></span>
        <input 
            class="fp-input" 
            type="<?php echo esc_attr($args['type']); ?>"
            name="<?php echo esc_attr($args['name']); ?>"
            <?php echo $args['required'] ? 'required' : ''; ?>
            <?php echo $args['autocomplete'] ? 'autocomplete="' . esc_attr($args['autocomplete']) . '"' : ''; ?>
            <?php echo $args['data_attr']; ?>
        >
        <?php if ($args['hint']): ?>
            <small class="fp-hint"><?php echo esc_html($args['hint']); ?></small>
        <?php endif; ?>
        <small class="fp-error" data-fp-error="<?php echo esc_attr($args['name']); ?>" hidden></small>
    </label>
    <?php
}
```

#### Task 5.2: Usare helper negli step

**PRIMA (nel template):**
```php
<label class="fp-resv-field fp-field">
    <span><?php echo esc_html($strings['fields']['first_name'] ?? ''); ?></span>
    <input class="fp-input" type="text" name="fp_resv_first_name" data-fp-resv-field="first_name" required autocomplete="given-name">
    <small class="fp-error" data-fp-resv-error="first_name" aria-live="polite" hidden></small>
    <?php if (!empty($hints['first_name'] ?? '')) : ?>
        <small class="fp-hint"><?php echo esc_html($hints['first_name']); ?></small>
    <?php endif; ?>
</label>
```

**DOPO:**
```php
<?php fp_render_input_field([
    'name' => 'fp_resv_first_name',
    'label' => $strings['fields']['first_name'] ?? '',
    'required' => true,
    'autocomplete' => 'given-name',
    'hint' => $hints['first_name'] ?? '',
    'data_attr' => 'data-fp-resv-field="first_name"',
]); ?>
```

Riduzione: da 10 righe a 7 righe, più leggibile.

---

### **FASE 6: Documentazione** (1 ora)

#### Task 6.1: Creare FORM-ARCHITECTURE.md

```markdown
# Architettura Form Frontend

## Struttura File
[mappa completa dei file e delle loro responsabilità]

## Dipendenze
- form.php → JavaScript: data-fp-resv-* attributes
- form.php → CSS: classi fp-*, fp-resv-*
- JavaScript → Backend: endpoint /reservations

## Modificare un Campo
1. Trovare lo step in `form-parts/steps/`
2. Modificare markup
3. Se necessario, aggiornare validation in `form-validation.js`

## Aggiungere un Nuovo Campo
[guida step-by-step]
```

#### Task 6.2: Creare FORM-QUICK-EDIT.md

```markdown
# Guida Rapida: Modifiche al Form

## Casi Comuni

### Aggiungere un campo di testo
[codice esempio]

### Modificare l'ordine dei campi
[istruzioni]

### Cambiare label o placeholder
[dove trovare le stringhe]

### Aggiungere validazione custom
[esempio]
```

---

### **FASE 7: Testing Completo** (1 ora)

#### Task 7.1: Testing funzionale
- [ ] Form si visualizza correttamente
- [ ] Navigazione tra step funziona
- [ ] Validazione campi funziona
- [ ] Selezione slot orari funziona
- [ ] Invio prenotazione funziona
- [ ] Messaggi di successo/errore appaiono

#### Task 7.2: Testing compatibilità
- [ ] Form funziona in pagina WordPress normale
- [ ] Form funziona in WPBakery builder
- [ ] CSS non viene escapato
- [ ] JavaScript trova tutti i data attributes
- [ ] Nessun errore console

#### Task 7.3: Testing responsive
- [ ] Desktop (1920px)
- [ ] Tablet (768px)
- [ ] Mobile (375px)

---

## 📊 Metriche di Successo

| Metrica | Prima | Dopo | Obiettivo |
|---------|-------|------|-----------|
| Righe form.php | 712 | ~200 | ✅ -70% |
| Max nidificazione HTML | 7 livelli | 3 livelli | ✅ |
| Tempo per trovare un campo | ~3-5 min | ~30 sec | ✅ |
| Tempo per aggiungere campo | ~15-20 min | ~5 min | ✅ |
| File CSS inline via JS | Sì | No | ✅ |

---

## 🚀 Ordine di Esecuzione

1. **Fase 1** → Setup (non rompe nulla)
2. **Fase 2** → Modularizzazione (testare dopo ogni step)
3. **Fase 3** → Semplificazione (testare frontend)
4. **Fase 4** → Fix CSS (testare con WPBakery)
5. **Fase 5** → Helper (opzionale, ma utile)
6. **Fase 6** → Documentazione (per il futuro)
7. **Fase 7** → Testing finale

---

## ⚠️ Attenzioni Speciali

### Non Rompere
- Data attributes usati da JavaScript (`data-fp-resv-*`)
- Classi CSS funzionali (non solo estetiche)
- Ordine degli step (controllato da backend)
- Nonce e security fields

### Testare Sempre
- Dopo ogni fase
- Su ambiente di staging prima di production
- Con dati reali (non solo dummy)

---

## 🎯 Risultato Finale

**Form principale ridotto:**
```php
<?php
// templates/frontend/form.php (~ 200 righe)
include __DIR__ . '/form-parts/header.php';
include __DIR__ . '/form-parts/progress.php';
include __DIR__ . '/form-parts/alerts.php';

foreach ($steps as $index => $step) {
    $stepFile = __DIR__ . '/form-parts/steps/step-' . $step['key'] . '.php';
    if (file_exists($stepFile)) {
        include $stepFile;
    }
}

include __DIR__ . '/form-parts/footer.php';
?>
```

**Manutenzione semplificata:**
- Vuoi modificare i campi contatti? → `step-details.php`
- Vuoi cambiare gli slot orari? → `step-slots.php`
- Vuoi aggiungere un campo? → Usa helper o copia/incolla da campo esistente

---

## 📝 Note Finali

Questo refactoring NON cambia funzionalità, solo struttura.  
L'utente finale non vedrà differenze.  
Lo sviluppatore vedrà codice 10x più mantenibile.

**Tempo stimato totale**: 8-10 ore  
**Beneficio**: Risparmio di ore in future modifiche
