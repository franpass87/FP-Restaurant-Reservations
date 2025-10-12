# 🌙 Dark Mode - Rimosso

## ℹ️ Informazione

La **dark mode automatica** è stata **rimossa** su richiesta dell'utente.

### Cosa è Stato Rimosso

❌ Media query `@media (prefers-color-scheme: dark)`  
❌ Variabili CSS per tema scuro automatico  
❌ Switch automatico tra light/dark  

### Perché?

L'utente ha richiesto esplicitamente di **non avere la dark mode**, quindi è stata completamente rimossa dal sistema CSS.

### Il Form Rimane in Light Mode

✅ Il form utilizzerà **sempre** il tema chiaro  
✅ Nessun cambio automatico in base alle preferenze del sistema  
✅ Controllo completo sul design  

### Se Vuoi Aggiungere Dark Mode in Futuro

Se in futuro volessi aggiungere una dark mode **manuale** (con toggle controllato dall'utente), puoi farlo aggiungendo:

```css
/* form.css - sezione CUSTOM OVERRIDES */

/* Classe dark mode opzionale controllata manualmente */
.fp-resv-widget--dark-mode {
  --fp-color-surface: #1e293b;
  --fp-color-surface-alt: #0f172a;
  --fp-color-text: #f8fafc;
  --fp-color-text-muted: #94a3b8;
  --fp-color-border: rgba(255, 255, 255, 0.1);
}
```

Poi nel template:
```html
<!-- Aggiungi la classe dark-mode quando necessario -->
<div class="fp-resv-widget fp-resv-widget--dark-mode">
```

**Nota:** Questa sarebbe una dark mode **controllata manualmente**, non automatica.

---

**Stato Attuale:** ✅ Solo Light Mode  
**Data Rimozione:** 12 Ottobre 2025

