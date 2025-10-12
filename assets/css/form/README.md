# 🎨 Sistema CSS Modulare - FP Restaurant Reservations

Sistema CSS completamente modulare e manutenibile per il form di prenotazione.

## 📁 Struttura

```
form/
├── main.css                    # File principale che importa tutto
├── _variables.css              # Variabili CSS e design tokens
├── _base.css                   # Reset e stili base
├── _layout.css                 # Sistema di layout (grid, flex, spacing)
├── _typography.css             # Tipografia e testo
├── _utilities.css              # Classi utility riutilizzabili
├── _animations.css             # Keyframes e animazioni
├── _responsive.css             # Media queries e breakpoints
└── components/                 # Componenti modulari
    ├── _buttons.css            # Bottoni e CTA
    ├── _inputs.css             # Input, select, textarea, checkbox
    ├── _pills.css              # Pill buttons (meal, slots)
    ├── _badges.css             # Badge di stato
    ├── _alerts.css             # Messaggi e feedback
    ├── _progress.css           # Barra di progresso
    ├── _steps.css              # Step del wizard
    ├── _meals.css              # Selettore pasti
    ├── _slots.css              # Selettore orari
    └── _summary.css            # Riepilogo finale
```

## 🚀 Come Usare

### Importare il sistema completo

```html
<link rel="stylesheet" href="assets/css/form/main.css">
```

### Importare solo parti specifiche (opzionale)

```css
/* Solo variabili e utilities */
@import 'form/_variables.css';
@import 'form/_utilities.css';

/* Solo componenti specifici */
@import 'form/components/_buttons.css';
@import 'form/components/_inputs.css';
```

## 🎯 Design Tokens (Variabili)

### Colori

```css
--fp-color-primary         /* Colore principale */
--fp-color-surface         /* Sfondo principale */
--fp-color-text            /* Testo principale */
--fp-color-success         /* Successo */
--fp-color-error           /* Errore */
--fp-color-warning         /* Warning */
```

### Spaziature

```css
--fp-space-xs    /* 8px */
--fp-space-sm    /* 12px */
--fp-space-md    /* 16px */
--fp-space-lg    /* 24px */
--fp-space-xl    /* 32px */
--fp-space-2xl   /* 48px */
--fp-space-3xl   /* 64px */
```

### Tipografia

```css
--fp-text-xs     /* 12px */
--fp-text-sm     /* 14px */
--fp-text-base   /* 16px */
--fp-text-lg     /* 18px */
--fp-text-xl     /* 20px */
--fp-text-2xl    /* 24px */
```

### Border Radius

```css
--fp-radius-sm   /* 6px */
--fp-radius-md   /* 8px */
--fp-radius-lg   /* 12px */
--fp-radius-xl   /* 16px */
--fp-radius-2xl  /* 20px */
--fp-radius-full /* 9999px - pills */
```

## 🧩 Componenti

### Bottoni

```html
<button class="fp-btn fp-btn--primary">Primary Button</button>
<button class="fp-btn fp-btn--secondary">Secondary Button</button>
<button class="fp-btn fp-btn--lg">Large Button</button>
```

### Input

```html
<div class="fp-field">
  <label>
    <span class="fp-field__label">Label</span>
    <input type="text" class="fp-input" />
  </label>
</div>
```

### Pill Buttons

```html
<button class="fp-pill" aria-pressed="false">
  <span class="fp-pill__label">Option</span>
</button>
```

### Alerts

```html
<div class="fp-alert fp-alert--success">
  <p>Operazione completata con successo!</p>
</div>
```

### Badge

```html
<span class="fp-badge fp-badge--success">Confermato</span>
```

## 🎨 Utility Classes

### Display

```css
.fp-block, .fp-inline, .fp-flex, .fp-grid, .fp-hidden
```

### Spacing

```css
.fp-mt-lg    /* margin-top large */
.fp-p-md     /* padding medium */
.fp-gap-sm   /* gap small */
```

### Typography

```css
.fp-text-lg       /* font size large */
.fp-font-bold     /* font weight bold */
.fp-text-center   /* text alignment */
```

### Colors

```css
.fp-text-primary   /* colore primario */
.fp-text-error     /* colore errore */
.fp-text-muted     /* testo muted */
```

## 📱 Breakpoints

```css
/* Mobile First */
@media (max-width: 640px)  { /* Mobile */ }
@media (min-width: 768px)  { /* Tablet */ }
@media (min-width: 1024px) { /* Desktop */ }
@media (min-width: 1280px) { /* Large Desktop */ }
```

## ⚡ Animazioni

### Built-in

```css
.fp-animate-fade-in
.fp-animate-slide-in-top
.fp-animate-scale-in
.fp-animate-spin
.fp-animate-pulse
.fp-animate-bounce
```

### Skeleton Loading

```html
<div class="fp-skeleton"></div>
<div class="fp-skeleton fp-skeleton--circle"></div>
<div class="fp-skeleton fp-skeleton--text"></div>
```

## 🛠️ Personalizzazione

### Override delle variabili

```css
:root {
  --fp-color-primary: #ff6b6b;
  --fp-radius-lg: 16px;
  --fp-font-sans: 'Inter', sans-serif;
}
```

### Estendere componenti

```css
.fp-btn--custom {
  background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
  color: white;
}
```

## 🎯 Best Practices

1. **Usa le variabili** invece di valori hardcoded
2. **Preferisci le utility classes** per modifiche rapide
3. **Mantieni la specificità bassa** per facilitare gli override
4. **Testa su mobile first** e aggiungi breakpoint quando necessario
5. **Usa le animazioni con moderazione** per utenti con `prefers-reduced-motion`

## 📦 Build & Deploy

Il sistema è pronto all'uso senza build tool. Per ottimizzare:

```bash
# Minifica con cssnano o postcss
npx postcss main.css -o main.min.css --use cssnano

# Oppure usa il build già configurato
npm run build:css
```

## 🐛 Debug

### Form non visibile?

Verifica che `main.css` sia caricato correttamente e che non ci siano conflitti con altri CSS del tema.

### Stili non applicati?

Controlla la specificità CSS. Gli stili del sistema usano specificità moderata per facilitare gli override.

### Animazioni troppo veloci/lente?

Modifica `--fp-transition-base`, `--fp-transition-fast`, `--fp-transition-slow` in `_variables.css`.

## 📄 Licenza

Parte del plugin FP Restaurant Reservations.

## 👥 Contribuire

Per modifiche o miglioramenti, aggiorna il file appropriato:
- Variabili globali → `_variables.css`
- Nuovo componente → crea `components/_nome.css`
- Utility → `_utilities.css`
- Responsive → `_responsive.css`

---

**Versione:** 2.0.0  
**Ultimo aggiornamento:** 2025-10-12

