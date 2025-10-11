# Diagnosi Warning WonderPush

## Contesto
Il warning di inizializzazione di WonderPush appare nella console del browser con questo stack trace:

```
t.warn @ VM49238 wonderpush.min.js:1
k @ VM49238 wonderpush.min.js:1
k.init @ VM49238 wonderpush.min.js:1
apply @ VM49238 wonderpush.min.js:1
D.ready @ VM49238 wonderpush.min.js:1
```

## Analisi

### Cosa è WonderPush
WonderPush è un servizio di notifiche push per siti web. Permette di inviare notifiche push ai visitatori anche quando non sono sul sito.

### Origine del Warning
Il codice `wonderpush.min.js` viene caricato come `VM49238`, il che indica che è uno script dinamico caricato tramite:
- **Google Tag Manager** (più probabile)
- Un plugin WordPress di terze parti
- Il tema WordPress
- Uno script personalizzato nel sito

### Nel Plugin FP Restaurant Reservations
Ho verificato che **WonderPush non è integrato direttamente** in questo plugin. Tuttavia, il plugin carica:
- Google Analytics 4 (GA4)
- Google Ads
- Meta Pixel (Facebook)
- Microsoft Clarity

📍 File: `src/Domain/Tracking/Manager.php:128`

## Come Diagnosticare

### 1. Verifica Google Tag Manager
WonderPush potrebbe essere configurato come tag in GTM:

1. Apri la console del browser (F12)
2. Vai alla tab "Network"
3. Filtra per "wonderpush"
4. Verifica il "Initiator" per vedere cosa carica lo script

Se vedi "gtag" o "googletagmanager", allora è configurato in GTM.

### 2. Verifica Plugin WordPress Attivi
```bash
# Cerca nei plugin WordPress installati
find wp-content/plugins -name "*wonderpush*" -type d
```

O nell'admin WordPress:
1. Vai a **Plugin** → **Plugin installati**
2. Cerca "WonderPush" o "Push Notification"

### 3. Verifica il Tema
```bash
# Cerca nel tema attivo
grep -ri "wonderpush" wp-content/themes/[nome-tema-attivo]/
```

### 4. Verifica Header/Footer Personalizzati
1. WordPress Admin → **Aspetto** → **Personalizza**
2. Cerca sezioni come "Script personalizzati" o "Header/Footer"
3. Verifica in **Impostazioni** → **Generali** se ci sono campi per script personalizzati

## Possibili Soluzioni

### Se WonderPush è in Google Tag Manager

1. Accedi al tuo account Google Tag Manager
2. Verifica che il tag WonderPush sia configurato correttamente:
   - **Controlla il Triggering**: Assicurati che si attivi solo sulle pagine dove è necessario
   - **Verifica le variabili**: Controlla che tutte le variabili richieste siano definite
   - **Controlla la configurazione**: Verifica che l'ID progetto WonderPush sia corretto

3. Se non usi WonderPush, **disabilita o rimuovi il tag**

### Se WonderPush è un Plugin

1. Vai a **Plugin** → **Plugin installati**
2. Trova il plugin WonderPush
3. Verifica le sue impostazioni in **Impostazioni** → **WonderPush**
4. Assicurati che:
   - L'**App ID** sia configurato
   - Le **chiavi API** siano valide
   - Il **Service Worker** sia installato correttamente

### Se WonderPush è nel Tema

1. Contatta lo sviluppatore del tema
2. Verifica la documentazione del tema per la configurazione delle notifiche push
3. Se non usi questa funzionalità, chiedi come disabilitarla

## Impatto del Warning

Il warning **non blocca** il funzionamento del sito o del plugin di prenotazioni, ma indica che:
- WonderPush sta cercando di inizializzarsi
- Manca qualche configurazione o dipendenza
- Potrebbe non funzionare correttamente (se è intenzionale)

## Cosa Fare

### Se Usi WonderPush
Configura correttamente il servizio seguendo la documentazione ufficiale:
https://docs.wonderpush.com/docs/web-getting-started

### Se NON Usi WonderPush
Rimuovi lo script identificando la fonte come descritto sopra.

## Note Tecniche

### Relazione con il Plugin FP Restaurant Reservations
Il plugin carica Google Tag Manager se configurato:
```php
// src/Domain/Tracking/Manager.php:128
printf('<script async src="%s"></script>', 
    esc_url_raw('https://www.googletagmanager.com/gtag/js?id=' . $gtagId));
```

Se WonderPush è configurato in GTM, verrà caricato attraverso questo meccanismo.

### Come Verificare nel Browser
```javascript
// Nella console del browser, verifica se WonderPush è presente:
console.log(window.WonderPush);

// Se è definito, controlla la configurazione:
console.log(window._wonderpushInitOptions);
```

## Risoluzione Rapida

Se vuoi semplicemente **silenziare il warning** senza risolvere la causa:

1. Identifica la fonte dello script
2. Rimuovi il caricamento se non è necessario
3. Oppure completa la configurazione se intendi usarlo

---

**Data analisi**: 2025-10-11
**Plugin**: FP Restaurant Reservations v0.1.10
**Conclusione**: WonderPush non è parte del plugin. Verificare Google Tag Manager, altri plugin o il tema WordPress.
