# Configurazione dei Servizi (Meals)

## Formato dei Meals

I servizi (meals) possono essere configurati tramite il pannello amministrativo in **Impostazioni → FP Reservations → Generale**.

### Formato Legacy (Pipe-Separated)

Il formato legacy usa il carattere `|` per separare i campi:

```
key|label|hint|notice|price|badge|badge_icon|icon
```

#### Campi Disponibili

| Campo | Descrizione | Obbligatorio | Esempio |
|-------|-------------|--------------|---------|
| `key` | Identificatore univoco del servizio | ✅ Sì | `lunch`, `dinner`, `brunch` |
| `label` | Etichetta visualizzata nel form | ✅ Sì | `Pranzo`, `Cena`, `Brunch` |
| `hint` | Suggerimento/descrizione breve | ❌ No | `Disponibile dal lunedì al venerdì` |
| `notice` | Avviso importante | ❌ No | `Include acqua e caffè` |
| `price` | Prezzo per persona | ❌ No | `22.00`, `34.00` |
| `badge` | Etichetta badge | ❌ No | `Menu business`, `Best seller` |
| `badge_icon` | Icona badge | ❌ No | `star`, `fire` |
| `icon` | Emoji/icona del servizio | ❌ No | `🍽️`, `🌙`, `☕` |

#### Servizio Predefinito

Aggiungi un asterisco `*` prima della chiave per impostare un servizio come predefinito:

```
*lunch|Pranzo|...|...|22.00|Menu business||🍽️
```

### Esempi

#### Esempio Base

```
*lunch|Pranzo|Disponibile dal lunedì al venerdì.|Include acqua e caffè.|22.00|Menu business||🍽️
aperitivo|Aperitivo|Drink + tapas dalle 18:00.|Prenotazione di 90 minuti.|18.50|Nuovo||🥂
dinner|Cena|Percorso degustazione 4 portate.||34.00|Best seller||🌙
brunch|Brunch|Sabato e domenica dalle 11:30.|Bevande calde illimitate.|28.00|Weekend||☕
```

#### Esempio Semplificato (Solo Essenziali)

```
*lunch|Pranzo|||||🍽️
dinner|Cena|||||🌙
```

### Formato JSON (Alternativo)

È possibile configurare i meals anche in formato JSON:

```json
[
  {
    "key": "lunch",
    "label": "Pranzo",
    "hint": "Disponibile dal lunedì al venerdì.",
    "notice": "Include acqua e caffè.",
    "price": "22.00",
    "badge": "Menu business",
    "icon": "🍽️",
    "active": true
  },
  {
    "key": "dinner",
    "label": "Cena",
    "hint": "Percorso degustazione 4 portate.",
    "price": "34.00",
    "badge": "Best seller",
    "icon": "🌙"
  }
]
```

## Icone Consigliate

Ecco alcune emoji comuni per i diversi servizi:

| Servizio | Emoji Consigliate |
|----------|------------------|
| Pranzo | 🍽️ 🍴 🥗 🍝 |
| Cena | 🌙 🍷 🥘 🌆 |
| Aperitivo | 🥂 🍸 🍹 🍷 |
| Brunch | ☕ 🥐 🍳 🥞 |
| Colazione | ☕ 🥐 🍞 🥯 |
| Merenda | 🍰 🧁 ☕ 🫖 |

## Visualizzazione nel Form

Le icone vengono visualizzate nei pulsanti di selezione del servizio all'interno del form di prenotazione:

```html
<button class="fp-meal-pill" data-fp-resv-meal="lunch">
  <span class="fp-meal-pill__label">
    <span class="fp-meal-pill__icon">🍽️</span>
    Pranzo
  </span>
</button>
```

## CSS Personalizzato

Gli stili delle icone possono essere personalizzati tramite CSS:

```css
/* Dimensione icona */
.fp-meal-pill__icon {
  font-size: 1.5em;
}

/* Spaziatura tra icona e testo */
.fp-meal-pill__label {
  gap: 0.5rem;
}
```

## Compatibilità

- ✅ WordPress 6.5+
- ✅ Tutti i browser moderni (Chrome, Firefox, Safari, Edge)
- ✅ Dispositivi mobile e tablet
- ✅ Supporto emoji nativo del sistema operativo

## Changelog

### Versione 0.1.11 (2025-10-18)

- ✨ **NUOVO**: Aggiunto supporto per le icone nei meals
- 🔧 Aggiornato il template del form per renderizzare le icone
- 🔧 Aggiornato il parser MealPlan per supportare il campo `icon`
- 📝 Aggiornati gli esempi nel seed con le icone

---

**Nota**: Se hai già configurato i meals in precedenza, puoi aggiungerli manualmente modificando la configurazione nel pannello amministrativo.
