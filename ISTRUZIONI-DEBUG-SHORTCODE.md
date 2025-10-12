# 🔍 Diagnostica Prenotazioni - Istruzioni

## Problema
Il manager delle prenotazioni non mostra nessuna prenotazione, nonostante le email vengano inviate correttamente.

## Soluzione: Shortcode Diagnostico

Ho aggiunto uno **shortcode diagnostico** che ti dirà ESATTAMENTE dove sta il problema.

---

## 📝 Come Usare lo Shortcode

### Passo 1: Carica il file modificato
1. Carica il file **`src/Frontend/Shortcodes.php`** sul server via FTP/SFTP
2. Sostituisci il file esistente

### Passo 2: Crea una pagina di test
1. Vai in **WordPress Admin** → **Pagine** → **Aggiungi nuova**
2. Titolo: `Debug Prenotazioni` (o qualsiasi nome)
3. Nel contenuto della pagina, inserisci semplicemente:
   ```
   [fp_resv_debug]
   ```
4. **Pubblica** la pagina
5. Imposta la pagina come **Privata** (per sicurezza, così solo tu la vedi)

### Passo 3: Visualizza la diagnostica
1. Apri la pagina che hai appena creato
2. Vedrai un pannello completo con tutte le informazioni

---

## 🎯 Cosa Ti Dirà lo Shortcode

Lo shortcode diagnostico verificherà:

### ✅ 1. Tabella Database
- Se la tabella `wp_fp_reservations` esiste
- Se è accessibile

### ✅ 2. Statistiche Prenotazioni
- **Quante prenotazioni ci sono nel database**
- Prenotazioni oggi, future, per stato
- Se ci sono 0 prenotazioni → **il form NON salva nel DB**

### ✅ 3. Ultime Prenotazioni
- Le ultime 5 prenotazioni inserite
- Con data, ora, cliente, stato

### ✅ 4. Test Endpoint REST
- Testa l'endpoint `/wp-json/fp-resv/v1/agenda`
- Verifica se restituisce correttamente le prenotazioni
- **Questo è ciò che usa il manager per caricare i dati**

### ✅ 5. Range Date
- Prima e ultima prenotazione
- Avviso se tutte le prenotazioni sono nel passato

### ✅ 6. Riepilogo Finale
Ti dirà esattamente quale di questi scenari si verifica:

#### Scenario A: ❌ Nessuna prenotazione nel DB
→ **Il form NON salva nel database** (anche se le email partono)
→ **Soluzione**: Problema nel codice di salvataggio

#### Scenario B: ❌ Prenotazioni nel DB ma endpoint restituisce 0
→ L'endpoint REST `/agenda` non funziona correttamente
→ **Soluzione**: Problema nella query dell'endpoint

#### Scenario C: ✅ Tutto OK lato server
→ Database e endpoint funzionano
→ Il problema è nel **JavaScript del manager**
→ **Soluzione**: Controllare console JavaScript del browser (F12)

---

## 📸 Cosa Fare Dopo

1. **Fai uno screenshot** o copia tutto l'output della pagina di diagnostica
2. **Mandamelo** 
3. In base al risultato, saprò esattamente cosa sistemare

---

## 🔒 Sicurezza

- Lo shortcode **funziona solo per amministratori** WordPress
- Se un utente normale prova ad aprire la pagina, vedrà solo un messaggio di errore
- Dopo la diagnosi, **cancella la pagina** per sicurezza

---

## ⚡ Note Importanti

- Lo shortcode NON modifica nulla nel database, **legge solo**
- È completamente sicuro da usare
- Funziona anche se hai il plugin 404 redirect attivo
- Non richiede file PHP standalone (usa il sistema WordPress)

---

## 🚀 Esegui Ora!

1. Carica `src/Frontend/Shortcodes.php` sul server
2. Crea pagina con `[fp_resv_debug]`
3. Mandami lo screenshot
4. Sistemerò il problema in base al risultato

**Let's go!** 🎯

