# 🎨 Come Personalizzare i Colori

## 📍 Dove Trovare le Impostazioni Colori

Ora che il plugin usa il sistema CSS modulare, i colori **non si modificano più dalle impostazioni admin**, ma direttamente nel file CSS:

```
📁 assets/css/form/_variables.css
```

## 🎯 Schema Attuale: Minimalista Bianco e Nero

### Colori Principali

```css
--fp-color-primary: #000000;           /* Nero - bottoni principali */
--fp-color-primary-hover: #1a1a1a;     /* Grigio scuro - hover bottoni */
--fp-color-surface: #ffffff;            /* Bianco - sfondo principale */
--fp-color-surface-alt: #fafafa;        /* Grigio chiarissimo - sfondo alternativo */
--fp-color-text: #000000;               /* Nero - testo principale */
--fp-color-text-muted: #666666;         /* Grigio - testo secondario */
--fp-color-border: #e0e0e0;             /* Grigio chiaro - bordi */
```

## ✏️ Come Modificare i Colori

### Opzione 1: Modifica Diretta (Consigliata)

Apri il file `assets/css/form/_variables.css` e modifica le variabili:

```css
:root {
  /* Cambia questi valori */
  --fp-color-primary: #000000;      /* Il tuo colore principale */
  --fp-color-surface: #ffffff;       /* Il tuo colore sfondo */
  --fp-color-text: #000000;          /* Il tuo colore testo */
}
```

### Opzione 2: Override Rapido

Aggiungi in `assets/css/form.css` nella sezione **CUSTOM OVERRIDES**:

```css
/* === CUSTOM OVERRIDES === */

:root {
  /* I tuoi colori personalizzati */
  --fp-color-primary: #ff0000;      /* Esempio: rosso */
  --fp-color-surface: #f5f5f5;      /* Esempio: grigio chiaro */
}
```

## 🎨 Esempi di Schemi Colore

### Schema 1: Nero Puro (Attuale)
```css
--fp-color-primary: #000000;
--fp-color-surface: #ffffff;
--fp-color-text: #000000;
```

### Schema 2: Grigio Antracite
```css
--fp-color-primary: #2d2d2d;
--fp-color-surface: #ffffff;
--fp-color-text: #1a1a1a;
```

### Schema 3: Marrone Elegante
```css
--fp-color-primary: #3e2723;
--fp-color-surface: #fafafa;
--fp-color-text: #212121;
```

### Schema 4: Blu Navy (se cambi idea)
```css
--fp-color-primary: #1a237e;
--fp-color-surface: #ffffff;
--fp-color-text: #212121;
```

### Schema 5: Verde Scuro
```css
--fp-color-primary: #1b5e20;
--fp-color-surface: #ffffff;
--fp-color-text: #212121;
```

## 🔧 Variabili Principali da Conoscere

### Colori Bottoni
```css
--fp-color-primary          /* Colore bottone principale */
--fp-color-primary-hover    /* Colore hover bottone */
```

### Colori Sfondi
```css
--fp-color-surface          /* Sfondo principale */
--fp-color-surface-alt      /* Sfondo alternativo (card, sezioni) */
```

### Colori Testi
```css
--fp-color-text             /* Testo principale */
--fp-color-text-muted       /* Testo secondario/hints */
--fp-color-text-light       /* Testo molto chiaro */
```

### Colori Bordi
```css
--fp-color-border           /* Bordi input/card */
--fp-color-border-light     /* Bordi leggeri */
--fp-color-divider          /* Linee divisorie */
```

### Colori Stati
```css
--fp-color-success          /* Verde (o grigio se minimalista) */
--fp-color-error            /* Rosso errori */
--fp-color-warning          /* Giallo warning */
--fp-color-info             /* Blu info */
```

## 📊 Palette Consigliata (Bianco e Nero)

```
#000000  ████  Nero - Bottoni, testi importanti
#1a1a1a  ████  Grigio scurissimo - Hover
#2d2d2d  ████  Grigio scuro - Alternative
#404040  ████  Grigio medio scuro
#666666  ████  Grigio medio - Testi secondari
#999999  ████  Grigio chiaro - Testi muted
#cccccc  ████  Grigio molto chiaro
#e0e0e0  ████  Grigio chiarissimo - Bordi
#f0f0f0  ████  Grigio ultra chiaro - Separatori
#fafafa  ████  Quasi bianco - Sfondo alternativo
#ffffff  ████  Bianco - Sfondo principale
```

## 💡 Consigli

### ✅ DO
- Usa sempre contrasto sufficiente (min 4.5:1 per testo)
- Testa su dispositivi reali
- Mantieni coerenza tra i colori
- Usa al massimo 3-4 colori principali

### ❌ DON'T
- Non usare troppi colori diversi
- Non usare colori troppo simili
- Non sacrificare leggibilità per estetica
- Non dimenticare gli stati hover/focus

## 🧪 Come Testare i Colori

1. Modifica `_variables.css`
2. Salva il file
3. Svuota cache browser (Ctrl+Shift+Delete)
4. Ricarica pagina (Ctrl+F5)
5. Verifica il risultato

## 🔄 Ripristinare Colori Default

Se vuoi tornare ai colori blu originali, usa:

```css
--fp-color-primary: #2563eb;
--fp-color-primary-hover: #1d4ed8;
```

## 📞 Supporto

Se hai dubbi su come modificare i colori:
1. Apri `assets/css/form/_variables.css`
2. Cerca la variabile che vuoi modificare
3. Cambia il valore esadecimale del colore
4. Salva e testa

---

**File da modificare:** `assets/css/form/_variables.css`  
**Schema attuale:** Minimalista Bianco e Nero  
**Ultimo aggiornamento:** 12 Ottobre 2025

