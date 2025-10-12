# 📖 Guida alla Migrazione - Sistema CSS Modulare

Questa guida ti aiuta a comprendere le differenze tra il vecchio sistema CSS monolitico e il nuovo sistema modulare.

## 🎯 Panoramica

**Prima (Monolitico):**
- Un singolo file CSS di 2400+ righe
- Difficile manutenzione
- Difficile trovare e modificare stili specifici
- Nessuna separazione delle responsabilità

**Dopo (Modulare):**
- Sistema organizzato in 20+ file tematici
- Facile manutenzione
- Componenti riutilizzabili
- Separazione chiara delle responsabilità

## 🔄 Cosa è Cambiato

### Nessuna Breaking Change!

✅ **Tutte le classi CSS esistenti funzionano ancora**  
✅ **I template PHP non richiedono modifiche**  
✅ **Gli shortcode continuano a funzionare**  
✅ **Il backup del vecchio CSS è in `form.css.backup`**

### Novità Aggiunte

✨ **Design Tokens**: Variabili CSS centralizzate  
✨ **Utility Classes**: Classi helper per modifiche rapide  
✨ **Animazioni Predefinite**: Sistema di animazioni riutilizzabili  
✨ **Migliore Responsive**: Breakpoint più granulari  

## 📚 Mapping delle Classi

Tutte le classi esistenti sono ancora supportate. Ecco alcune equivalenze con le nuove utility:

### Before → After

```css
/* Vecchio approccio */
.custom-element {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 12px;
}

/* Nuovo approccio con utilities */
<div class="fp-flex fp-items-center fp-gap-md fp-p-lg fp-rounded-lg">
```

## 🛠️ Come Personalizzare

### 1. Modificare i Colori

**Prima:**
```css
/* Dovevi cercare nel file CSS e cambiare ogni occorrenza */
```

**Ora:**
```css
/* form/_variables.css */
:root {
  --fp-color-primary: #ff6b6b;
  --fp-color-success: #51cf66;
}
```

### 2. Modificare Spaziature

**Prima:**
```css
.fp-resv-step {
  padding: 2.1rem;
  gap: 1.5rem;
}
```

**Ora:**
```css
/* form/_variables.css */
:root {
  --fp-space-lg: 2rem;
  --fp-space-xl: 2.5rem;
}
```

### 3. Aggiungere un Nuovo Componente

**Prima:**
```css
/* Aggiungevi alla fine del file form.css */
```

**Ora:**
```css
/* 1. Crea form/components/_nuovo-componente.css */
.fp-nuovo-componente {
  /* ... stili ... */
}

/* 2. Importa in form/main.css */
@import './components/_nuovo-componente.css';
```

## 🎨 Esempi Pratici

### Esempio 1: Personalizzare il Bottone Primario

```css
/* form.css - sezione CUSTOM OVERRIDES */

.fp-btn--primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  font-weight: 700;
}
```

### Esempio 2: Creare un Tema Scuro

```css
/* form.css - sezione CUSTOM OVERRIDES */

.fp-resv-widget--dark {
  --fp-color-surface: #1e293b;
  --fp-color-text: #f8fafc;
  --fp-color-border: rgba(255, 255, 255, 0.1);
}
```

### Esempio 3: Modificare il Border Radius Globale

```css
/* form/_variables.css */

:root {
  --fp-radius-sm: 4px;
  --fp-radius-md: 8px;
  --fp-radius-lg: 16px;
  --fp-radius-xl: 24px;
  --fp-radius-2xl: 32px;
}
```

## 📱 Responsive Design

### Breakpoints

```css
/* Mobile First */
/* Default: < 640px */

@media (min-width: 640px) {
  /* Tablet piccolo */
}

@media (min-width: 768px) {
  /* Tablet */
}

@media (min-width: 1024px) {
  /* Desktop */
}

@media (min-width: 1280px) {
  /* Large Desktop */
}
```

### Utility Responsive

```html
<!-- Visibile solo su mobile -->
<div class="fp-block fp-md:hidden">Mobile only</div>

<!-- Visibile solo su desktop -->
<div class="fp-hidden fp-md:block">Desktop only</div>
```

## 🐛 Troubleshooting

### Problema: Stili non applicati

**Soluzione 1:** Verifica che `form.css` importi correttamente `form/main.css`

```css
/* form.css dovrebbe contenere: */
@import './form/main.css';
```

**Soluzione 2:** Svuota la cache del browser e del plugin di caching

**Soluzione 3:** Verifica che non ci siano errori nella console del browser

### Problema: Conflitti con il tema

**Soluzione:** Aumenta la specificità

```css
/* form.css - CUSTOM OVERRIDES */
.fp-resv-widget .fp-btn {
  /* I tuoi override */
}
```

### Problema: Voglio tornare al vecchio sistema

**Soluzione:**

```bash
# 1. Rimuovi l'import in form.css
# 2. Ripristina il backup
cp assets/css/form.css.backup assets/css/form.css
```

## 📊 Confronto Prestazioni

| Metrica | Vecchio | Nuovo |
|---------|---------|-------|
| Linee di codice | 2400+ | 2500+ (ma modulare!) |
| File | 1 | 20+ |
| Manutenibilità | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| Riutilizzabilità | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| Time to find | Lento | Veloce |
| Dimensione finale | ~65KB | ~67KB* |

*La dimensione aumenta leggermente ma con migliore compressione gzip il risultato finale è simile.

## 🎓 Best Practices

### ✅ DO

- Usa le variabili CSS invece di valori hardcoded
- Preferisci le utility classes per modifiche rapide
- Mantieni gli override in `form.css` per facilità di manutenzione
- Aggiungi commenti descrittivi per modifiche custom
- Testa su dispositivi reali

### ❌ DON'T

- Non modificare direttamente i file in `form/` (usa gli override)
- Non duplicare stili esistenti
- Non usare `!important` se non necessario
- Non dimenticare di testare il responsive

## 📝 Checklist Migrazione

- [ ] Backup del vecchio `form.css` creato
- [ ] Nuovo sistema importato correttamente
- [ ] Testato su browser principali (Chrome, Firefox, Safari)
- [ ] Testato su dispositivi mobile
- [ ] Verificato che tutti i form funzionino
- [ ] Cache svuotata
- [ ] Documentazione letta

## 🆘 Supporto

Se hai problemi o domande:

1. Leggi la documentazione: `form/README.md`
2. Controlla le variabili: `form/_variables.css`
3. Consulta gli esempi in questa guida
4. Verifica il backup: `form.css.backup`

---

**Versione:** 2.0.0  
**Data:** 2025-10-12  
**Autore:** Francesco

