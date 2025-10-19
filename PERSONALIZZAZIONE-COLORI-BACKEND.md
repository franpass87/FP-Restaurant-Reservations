# 🎨 Personalizzazione Colori dal Backend

## ✅ Sistema Completamente Funzionante!

Il form frontend è stato **completamente ricostruito** in stile The Fork e ora hai accesso completo alla personalizzazione dei colori dal pannello admin di WordPress!

## 📍 Dove Trovare le Impostazioni

1. Accedi al pannello admin di WordPress
2. Vai su **FP Reservations** → **🎨 Colori Form**
3. Troverai una pagina completa con:
   - **Color pickers** interattivi
   - **Preset rapidi** (Bianco e Nero, Grigio Scuro, Blu Navy, Verde Bosco)
   - **Anteprima LIVE** in tempo reale

## 🎯 Cosa Puoi Personalizzare

### Colori Principali
- **Colore Primario**: Usato per bottoni principali, link e accenti
- **Colore Hover**: Colore al passaggio del mouse sui bottoni

### Colori Sfondo
- **Sfondo Principale**: Background del form
- **Sfondo Alternativo**: Background per card e sezioni

### Colori Testo
- **Testo Principale**: Colore del testo principale
- **Testo Secondario**: Colore per testi meno importanti

### Colori Bordi
- **Bordo Principale**: Colore dei bordi di input e card

### Colori Bottoni
- **Sfondo Bottone**: Colore di background per i bottoni
- **Testo Bottone**: Colore del testo sui bottoni

## 🚀 Come Funziona

1. **Seleziona un preset** per iniziare rapidamente (opzionale)
2. **Modifica i colori** usando i color picker
3. **Vedi l'anteprima live** nel pannello a destra
4. **Salva** quando sei soddisfatto
5. I colori vengono applicati **automaticamente** al form frontend!

## 🎨 Preset Disponibili

### 1. Bianco e Nero (Default)
```
Primario: #000000
Sfondo: #ffffff
Bottoni: #000000 su bianco
```

### 2. Grigio Scuro
```
Primario: #2d2d2d
Sfondo: #ffffff
Bottoni: #2d2d2d su bianco
```

### 3. Blu Navy
```
Primario: #1a237e
Sfondo: #ffffff
Bottoni: #1a237e su bianco
```

### 4. Verde Bosco
```
Primario: #1b5e20
Sfondo: #ffffff
Bottoni: #1b5e20 su bianco
```

## 💡 Funzionalità Avanzate

- ✅ **Anteprima in Tempo Reale**: Vedi le modifiche istantaneamente
- ✅ **Input Manuale Hex**: Puoi inserire i codici colore direttamente
- ✅ **Ripristina Default**: Torna ai colori originali con un click
- ✅ **Variabili CSS Automatiche**: I colori generano automaticamente varianti (hover, light, rgb)
- ✅ **Persistenza**: I colori salvati vengono mantenuti dopo aggiornamenti

## 🔧 Tecnologie Utilizzate

- **Backend**: PHP con sanitizzazione WordPress
- **Frontend**: JavaScript vanilla per l'anteprima live
- **Storage**: WordPress Options API
- **CSS**: Variabili CSS generate dinamicamente

## 📝 Note Tecniche

### Variabili CSS Generate Automaticamente

Quando salvi i colori, il sistema genera automaticamente:

```css
:root {
  --fp-color-primary: #000000;
  --fp-color-primary-hover: #1a1a1a;
  --fp-color-primary-light: rgba(0, 0, 0, 0.05);
  --fp-color-primary-rgb: 0, 0, 0;
  --fp-color-surface: #ffffff;
  --fp-color-surface-alt: #fafafa;
  --fp-color-text: #000000;
  --fp-color-text-muted: #666666;
  --fp-color-border: #e0e0e0;
  --fp-resv-button-bg: #000000;
  --fp-resv-button-text: #ffffff;
  --fp-gradient-primary: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
}
```

### Come i Colori Vengono Applicati

1. I colori vengono salvati nel database WordPress (`wp_options`)
2. La classe `FormColors` li recupera e genera CSS dinamico
3. Il CSS viene iniettato nel template del form
4. Il form usa queste variabili CSS per lo styling

### Compatibilità

- ✅ **WordPress**: 6.5+
- ✅ **PHP**: 8.1+
- ✅ **Browser**: Tutti i browser moderni con supporto CSS Variables
- ✅ **Cache**: Compatibile con plugin di cache (hash automatico)

## 🎯 Best Practices

### Contrasto
- Mantieni sempre un contrasto sufficiente tra testo e sfondo (minimo 4.5:1)
- Il sistema non controlla automaticamente il contrasto, fai attenzione!

### Coerenza
- Usa al massimo 2-3 colori principali
- Mantieni coerenza con il brand del ristorante
- Evita colori troppo vivaci che potrebbero affaticare la vista

### Testing
- Testa sempre i colori su dispositivi reali
- Verifica la leggibilità su schermi diversi
- Controlla in modalità chiara e scura (se abilitata)

## 🆘 Risoluzione Problemi

### I colori non si applicano
1. Svuota la cache del browser (Ctrl+Shift+Delete)
2. Svuota la cache di WordPress (se usi un plugin di cache)
3. Verifica che i colori siano stati salvati correttamente

### L'anteprima non funziona
1. Verifica che JavaScript sia abilitato
2. Controlla la console del browser per errori
3. Ricarica la pagina

### Voglio tornare ai default
1. Clicca su **"Ripristina Default"**
2. Conferma l'operazione
3. I colori torneranno al preset Bianco e Nero

## 📚 Risorse Aggiuntive

### File Correlati
- **Backend**: `src/Domain/Settings/FormColors.php`
- **View**: `src/Admin/Views/form-colors.php`
- **JavaScript**: `assets/js/admin/form-colors.js`
- **CSS Form**: `assets/css/form-thefork.css`

### Documentazione Completa
Vedi anche: `assets/css/form/PERSONALIZZAZIONE-COLORI.md`

---

**Versione**: 4.0.0  
**Ultimo Aggiornamento**: 19 Ottobre 2025  
**Stato**: ✅ Completamente Funzionante
