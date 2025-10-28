# 🚨 INIZIA QUI - Agenda Non Funziona

> **Situazione**: L'agenda continua a non funzionare  
> **Soluzione**: Segui questa guida passo-passo

---

## 🎯 Piano d'Azione Rapido (5 minuti)

```
┌─────────────────────────────────────────────────────┐
│  STEP 1: Fix più comune (1 minuto)                 │
│  → Rigenera Permalink                               │
└─────────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────┐
│  STEP 2: Controlla Console Browser (2 minuti)      │
│  → F12 → Console → Cerca errori                     │
└─────────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────┐
│  STEP 3: Esegui Diagnostica (2 minuti)             │
│  → php test-agenda-quick.php                        │
└─────────────────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────┐
│  STEP 4: Applica Fix Specifico                     │
│  → Leggi FIX-RAPIDI-AGENDA-2025-10-12.md           │
└─────────────────────────────────────────────────────┘
```

---

## 🚀 STEP 1: Fix Più Comune (INIZIA DA QUI!)

### Rigenera i Permalink di WordPress

**90% dei problemi si risolvono così!**

1. Apri **WordPress Admin**
2. Vai su **Impostazioni** → **Permalink**
3. Clicca **"Salva modifiche"** (senza cambiare niente)
4. Torna all'**Agenda**
5. Premi **Ctrl + Shift + R** (refresh forzato)

### ✅ Come verificare se ha funzionato:

Apri nel browser:
```
http://tuosito.com/wp-json/fp-resv/v1/agenda
```

**SE FUNZIONA**: Vedi un JSON con prenotazioni → **PROBLEMA RISOLTO** ✅

**SE NON FUNZIONA**: Vedi "404 Not Found" → Passa allo STEP 2

---

## 🔍 STEP 2: Console Browser (FONDAMENTALE!)

### Apri la Console:

1. Vai su **WordPress Admin** → **Prenotazioni** → **Agenda**
2. Premi **F12** (o **Ctrl+Shift+I** su Windows, **Cmd+Option+I** su Mac)
3. Clicca sul tab **"Console"**

### Cosa cercare:

#### ✅ TUTTO OK - Dovresti vedere:
```javascript
[Agenda] 🚀 Inizializzazione nuova agenda...
[Agenda] 📥 Caricamento dati...
[Agenda] ✅ Dati caricati: 5 prenotazioni
```

#### ❌ PROBLEMA - Cerchi questi errori:

| Errore | Significa | Fix |
|--------|-----------|-----|
| `fpResvAgendaSettings is not defined` | Config JS mancante | Verifica plugin attivo |
| `404 (Not Found)` su `/agenda` | Endpoint non registrato | Rigenera permalink |
| `403 (Forbidden)` su `/agenda` | Permessi insufficienti | Verifica ruolo admin |
| `Failed to fetch` | REST API bloccata | Controlla .htaccess |
| Nessun messaggio `[Agenda]` | JS non caricato | `npm run build` |

### 🧪 Test Manuale nella Console:

Copia e incolla questo nella console:
```javascript
console.log(window.fpResvAgendaSettings);
```

**Risultato atteso**:
```javascript
{ restRoot: "http://...", nonce: "abc123...", ... }
```

**Se vedi `undefined`**: Problema con PHP che passa configurazione → Fix #6 in FIX-RAPIDI

---

## 🩺 STEP 3: Esegui Diagnostica Automatica

### Opzione A - Terminale (veloce):

```bash
cd /percorso/a/wordpress
php test-agenda-quick.php
```

### Opzione B - Browser (completa):

1. Carica `DIAGNOSTICA-AGENDA-COMPLETA.php` nella root di WordPress
2. Apri: `http://tuosito.com/DIAGNOSTICA-AGENDA-COMPLETA.php`
3. Leggi il report completo

### Cosa fa:
- ✅ Verifica plugin attivo
- ✅ Controlla tabelle database
- ✅ Testa endpoint REST API
- ✅ Verifica file presenti
- ✅ Controlla permessi utente

---

## 🔧 STEP 4: Fix Specifici

Leggi il file **`FIX-RAPIDI-AGENDA-2025-10-12.md`** e applica il fix corrispondente all'errore trovato.

### I 10 fix più comuni:

1. **Rigenera Permalink** ← già fatto nello STEP 1
2. **Svuota Cache Browser** (Ctrl+Shift+R)
3. **Disabilita Plugin Cache** (WP Rocket, W3 Total Cache, etc.)
4. **Ricompila JavaScript** (`npm run build`)
5. **Verifica Permessi Utente** (deve essere Admin)
6. **Controlla File Presenti** (vendor/autoload.php)
7. **Crea Prenotazione Test** (database vuoto)
8. **Controlla .htaccess** (REST API bloccata)
9. **Verifica REST API** (apri /wp-json/)
10. **Riavvia PHP/Apache** (cache opcode)

---

## 📚 File di Supporto Creati

Ho creato questi file per aiutarti:

| File | Scopo | Quando usarlo |
|------|-------|---------------|
| `test-agenda-quick.php` | Test rapido terminale | Per verifica veloce |
| `DIAGNOSTICA-AGENDA-COMPLETA.php` | Diagnostica completa browser | Per analisi dettagliata |
| `GUIDA-DEBUG-CONSOLE-BROWSER.md` | Guida console F12 | Per capire errori JS |
| `FIX-RAPIDI-AGENDA-2025-10-12.md` | 10 soluzioni comuni | Dopo aver identificato il problema |
| `INIZIA-QUI-AGENDA-NON-FUNZIONA.md` | Questa guida | Punto di partenza |

---

## 🆘 Serve Ancora Aiuto?

Se dopo questi step l'agenda NON funziona ancora, **condividi con me**:

### 📋 Checklist informazioni necessarie:

- [ ] Screenshot della **Console browser (F12)** con errori
- [ ] Screenshot della **tab Network (F12 → Network)** con richiesta `/agenda`
- [ ] Output completo di **`test-agenda-quick.php`** o **`DIAGNOSTICA-AGENDA-COMPLETA.php`**
- [ ] Versione **WordPress** e **PHP** (visibile in WP Admin → Dashboard → Salute del sito)
- [ ] Lista **plugin attivi** (WP Admin → Plugin)
- [ ] Eventuale contenuto di **`wp-content/debug.log`** (se esiste)

### 💬 Con queste informazioni potrò:
- ✅ Identificare **esattamente** il problema
- ✅ Darti la **soluzione definitiva**
- ✅ Risolvere in **pochi minuti**

---

## ⏱️ Tempo Stimato per Risoluzione

| Scenario | Tempo |
|----------|-------|
| Permalink non rigenerati | **1 minuto** |
| Cache browser | **2 minuti** |
| Plugin cache | **3 minuti** |
| Ricompilazione asset | **5 minuti** |
| Problema complesso | **10-15 minuti** con diagnostica |

---

## 🎯 Risultato Atteso

Dopo aver seguito questa guida, l'agenda deve:

- ✅ Caricarsi senza errori
- ✅ Mostrare le prenotazioni esistenti
- ✅ Permettere di creare nuove prenotazioni
- ✅ Cambiare vista (Giorno/Settimana/Mese)
- ✅ Navigare tra le date
- ✅ Mostrare statistiche corrette

---

## 📞 Supporto

**Prima di chiedere aiuto**:
1. ✅ Hai rigenerato i permalink?
2. ✅ Hai controllato la console browser (F12)?
3. ✅ Hai eseguito la diagnostica?

**Se sì a tutti e tre**: Condividi le informazioni della checklist sopra! 👆

---

**Creato**: 2025-10-12  
**Versione Plugin**: 0.1.6+  
**Compatibilità**: WordPress 6.5+, PHP 8.1+

---

## 🚦 Semaforo Priorità

```
🔴 PRIORITÀ MASSIMA → Console Browser (STEP 2)
🟡 PRIORITÀ ALTA     → Rigenera Permalink (STEP 1)
🟢 PRIORITÀ MEDIA    → Diagnostica (STEP 3)
```

**Inizia dal rosso, scendi verso il verde!** 🎯

