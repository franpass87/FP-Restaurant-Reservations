# 📖 Guida per lo Staff - FP Restaurant Reservations

**Guida completa per la gestione quotidiana delle prenotazioni**

---

## 👋 Benvenuto

Questa guida ti aiuterà a utilizzare il sistema di prenotazioni **FP Restaurant Reservations** per gestire in modo semplice ed efficace le prenotazioni del ristorante.

---

## 🎯 Indice Rapido

1. [Accedere al Sistema](#-accedere-al-sistema)
2. [Visualizzare le Prenotazioni](#-visualizzare-le-prenotazioni)
3. [Gestire le Prenotazioni](#-gestire-le-prenotazioni)
4. [Gestire Tavoli e Sale](#-gestire-tavoli-e-sale)
5. [Impostare Chiusure](#-impostare-chiusure)
6. [Visualizzare Report](#-visualizzare-report)
7. [Domande Frequenti](#-domande-frequenti)
8. [Contatti](#-contatti)

---

## 🔐 Accedere al Sistema

### 1. Login WordPress

1. Vai all'indirizzo del sito seguito da `/wp-admin` (es: `www.tuoristorante.it/wp-admin`)
2. Inserisci **username** e **password** forniti dall'amministratore
3. Clicca su **Accedi**

### 2. Menu del Plugin

Una volta loggato, troverai il menu **FP Reservations** nella barra laterale sinistra con le seguenti voci:

- 📅 **Agenda** - Calendario con tutte le prenotazioni
- 🍽️ **Sale & Tavoli** - Gestione tavoli e disposizione sale
- 🔒 **Chiusure** - Giorni e orari di chiusura
- 📊 **Report & Analytics** - Statistiche e analisi
- 🔧 **Diagnostica** - Log e risoluzione problemi
- ⚙️ **Impostazioni** - Configurazioni generali

---

## 📅 Visualizzare le Prenotazioni

### Agenda Principale

1. Clicca su **FP Reservations → Agenda** nel menu
2. Vedrai un calendario mensile con tutte le prenotazioni

#### Navigazione Calendario

- **Frecce ← →** per cambiare mese
- **Oggi** per tornare al giorno corrente
- **Clic su una data** per vedere i dettagli delle prenotazioni del giorno

#### Colori e Legenda

Le prenotazioni sono evidenziate con colori diversi in base allo stato:

| Colore | Stato | Significato |
|--------|-------|-------------|
| 🟢 Verde | Confermata | Prenotazione confermata dal cliente |
| 🟡 Giallo | In attesa | In attesa di conferma |
| 🔴 Rosso | Cancellata | Prenotazione annullata |
| 🔵 Blu | Completata | Servizio già erogato |

#### Dettagli Prenotazione

Cliccando su una prenotazione vedrai:

- **Nome e cognome** del cliente
- **Numero di telefono** e **email**
- **Numero di ospiti**
- **Orario** richiesto
- **Turno** (pranzo/cena)
- **Note speciali** (allergie, intolleranze, occasioni)
- **Stato pagamento** (se configurato Stripe)
- **Tavolo assegnato** (se già assegnato)

---

## ✏️ Gestire le Prenotazioni

### Azioni Rapide

Per ogni prenotazione puoi eseguire diverse azioni:

#### 1. Confermare una Prenotazione

1. Apri i dettagli della prenotazione
2. Clicca sul pulsante **Conferma**
3. Il cliente riceverà automaticamente un'email di conferma

#### 2. Assegnare un Tavolo

1. Apri i dettagli della prenotazione
2. Nella sezione **Tavolo** seleziona dal menu a tendina
3. Clicca **Salva**
4. Il tavolo sarà evidenziato nell'agenda

#### 3. Modificare una Prenotazione

1. Apri i dettagli della prenotazione
2. Modifica i campi necessari:
   - Numero ospiti
   - Orario
   - Tavolo
   - Note
3. Clicca **Salva modifiche**

> ⚠️ **Attenzione**: Se modifichi orario o numero ospiti significativamente, avvisa il cliente!

#### 4. Cancellare una Prenotazione

1. Apri i dettagli della prenotazione
2. Clicca **Cancella prenotazione**
3. Conferma l'operazione
4. (Opzionale) Indica il motivo della cancellazione
5. Il cliente riceverà un'email di notifica

#### 5. Aggiungere Note

Puoi aggiungere note interne (non visibili al cliente):

1. Apri i dettagli della prenotazione
2. Nella sezione **Note Staff** scrivi la nota
3. Clicca **Salva**

Esempi di note utili:
- "Cliente VIP - tavolo preferito vicino alla finestra"
- "Compleanno - preparare dolce"
- "Cliente con carrozzina - tavolo accessibile"

### Funzione Drag & Drop

L'agenda supporta il **drag & drop** per spostare rapidamente le prenotazioni:

1. Clicca e tieni premuto su una prenotazione
2. Trascina verso un altro orario o giorno
3. Rilascia per confermare lo spostamento
4. Verrà inviata automaticamente un'email al cliente con il nuovo orario

---

## 🍽️ Gestire Tavoli e Sale

### Visualizzare Tavoli

1. Vai su **FP Reservations → Sale & Tavoli**
2. Vedrai la planimetria del ristorante con tutti i tavoli

### Aggiungere un Nuovo Tavolo

1. Clicca su **+ Aggiungi Tavolo**
2. Compila i campi:
   - **Nome tavolo** (es: "Tavolo 1", "Tavolo Terrazza 3")
   - **Capacità** (numero massimo di persone)
   - **Sala** (principale, terrazza, veranda, ecc.)
   - **Posizione** (opzionale: finestra, angolo, centro sala)
3. Clicca **Salva**

### Modificare un Tavolo

1. Clicca sul tavolo nella planimetria
2. Modifica i dati necessari
3. Clicca **Salva modifiche**

### Combinare Tavoli (Merge)

Per prenotazioni di gruppi numerosi:

1. Seleziona i tavoli da combinare (tieni premuto **Ctrl/Cmd** e clicca)
2. Clicca **Unisci tavoli**
3. Dai un nome alla combinazione (es: "Tavoli 1+2")
4. La capacità totale sarà la somma dei tavoli

### Dividere Tavoli (Split)

Per separare tavoli precedentemente uniti:

1. Clicca sul tavolo combinato
2. Clicca **Dividi tavoli**
3. Conferma l'operazione

### Sale e Zone

Puoi organizzare i tavoli in sale diverse:

- **Sala principale**
- **Sala privata**
- **Terrazza**
- **Veranda**
- **Dehors**

Questo aiuta a:
- Organizzare meglio lo spazio
- Assegnare tavoli in base alle preferenze clienti
- Gestire aperture parziali (es: solo sala interna d'inverno)

---

## 🔒 Impostare Chiusure

### Tipi di Chiusura

Il sistema gestisce diversi tipi di chiusure:

1. **Chiusure Ricorrenti** - Ripetute ogni settimana (es: chiuso il lunedì)
2. **Chiusure Speciali** - Giorni festivi o eventi speciali
3. **Riduzioni Capacità** - Apertura parziale con meno coperti

### Aggiungere una Chiusura Ricorrente

1. Vai su **FP Reservations → Chiusure**
2. Tab **Chiusure Ricorrenti**
3. Clicca **+ Aggiungi**
4. Seleziona:
   - **Giorno della settimana** (es: Lunedì)
   - **Turno** (pranzo, cena, entrambi)
5. Clicca **Salva**

### Aggiungere una Chiusura Speciale

1. Tab **Chiusure Speciali**
2. Clicca **+ Aggiungi**
3. Compila:
   - **Data inizio** e **Data fine**
   - **Motivo** (es: "Ferie estive", "25 dicembre - Natale")
   - **Turni** interessati
4. Clicca **Salva**

> 💡 **Consiglio**: Imposta le chiusure con anticipo (almeno 2 settimane) per evitare che i clienti tentino di prenotare.

### Riduzione Temporanea Capacità

Se devi ridurre temporaneamente i coperti:

1. Tab **Riduzioni Capacità**
2. Clicca **+ Aggiungi**
3. Compila:
   - **Periodo** (da - a)
   - **Percentuale riduzione** o **Numero esatto coperti**
   - **Motivo** (es: "Lavori di ristrutturazione parziale")
4. Clicca **Salva**

### Anteprima Chiusure

Il sistema mostra un'**anteprima calendario** con tutte le chiusure evidenziate:

- 🔴 **Rosso** = Chiusura totale
- 🟡 **Giallo** = Riduzione capacità
- ⚪ **Bianco** = Apertura normale

---

## 📊 Visualizzare Report

### Accedere ai Report

1. Vai su **FP Reservations → Report & Analytics**
2. Scegli il **periodo** da analizzare (settimana, mese, trimestre, anno)

### Metriche Principali

Il dashboard mostra:

#### 1. Statistiche Generali

- **Totale prenotazioni** nel periodo
- **Tasso di occupazione** (% coperti utilizzati)
- **Ospiti totali** serviti
- **Tasso di cancellazione** (%)

#### 2. Grafico Prenotazioni nel Tempo

Un grafico a linee mostra l'andamento giornaliero delle prenotazioni

#### 3. Canali di Acquisizione (Grafico a Torta)

Da dove arrivano le prenotazioni:
- **Diretto** - Dal sito web
- **Google** - Da ricerca Google
- **Social** - Facebook, Instagram, ecc.
- **Email** - Da newsletter
- **Altro** - Altri canali

#### 4. Turni Preferiti

Statistiche su quali turni sono più richiesti:
- Pranzo vs Cena
- Orari più richiesti
- Giorni della settimana più frequentati

#### 5. Tavoli Più Richiesti

Quali tavoli vengono prenotati più spesso (utile per capire le preferenze)

### Export Dati

Puoi esportare i report in formato **CSV** o **PDF**:

1. Clicca **Esporta**
2. Scegli il formato
3. Scarica il file

> 💡 **Utile per**: Analisi contabilità, presentazioni, riunioni management

---

## ❓ Domande Frequenti

### Come faccio a vedere le prenotazioni di oggi?

1. Vai su **FP Reservations → Agenda**
2. Clicca sul pulsante **Oggi**
3. Oppure usa il filtro **Vista Giornaliera**

### Posso vedere solo le prenotazioni non confermate?

Sì! Usa i filtri nell'agenda:

1. Clicca su **Filtri**
2. Seleziona **Stato: In attesa**
3. Clicca **Applica**

### Come stampo la lista prenotazioni del giorno?

1. Vista giornaliera dell'agenda
2. Clicca su **Stampa** (icona stampante)
3. Scegli **Lista compatta** per il servizio
4. Stampa o salva come PDF

### Un cliente vuole modificare la prenotazione, cosa faccio?

1. Cerca la prenotazione per nome o data
2. Apri i dettagli
3. Modifica i campi necessari
4. Salva
5. Il cliente riceverà automaticamente email con i nuovi dettagli

### Come gestisco una richiesta di prenotazione per un gruppo numeroso?

1. Controlla la disponibilità di tavoli combinabili
2. Vai su **Sale & Tavoli**
3. Seleziona i tavoli da unire
4. Clicca **Unisci tavoli**
5. Crea la prenotazione assegnando il tavolo combinato

### Non riesco a trovare una prenotazione, cosa faccio?

1. Usa la **Ricerca** in alto nell'agenda
2. Cerca per:
   - Nome cliente
   - Email
   - Telefono
   - Data
3. Controlla anche nelle prenotazioni **Cancellate** (usa filtro stato)

### Come funzionano le email automatiche?

Il sistema invia automaticamente email al cliente quando:

- ✅ Crea una nuova prenotazione
- ✅ Confermi la prenotazione
- ✅ Modifichi orario/tavolo/dettagli
- ✅ Cancelli la prenotazione
- ✅ 24h prima della prenotazione (promemoria)

Puoi personalizzare i testi in **Impostazioni → Notifiche**

### Cosa faccio se un cliente non si presenta (no-show)?

1. Trova la prenotazione nell'agenda
2. Apri i dettagli
3. Cambia lo stato in **No-show**
4. (Opzionale) Aggiungi una nota
5. Questo aiuta a tracciare i clienti inaffidabili

### Come gestisco le liste d'attesa?

1. Quando un turno è completo, il sistema offre automaticamente la lista d'attesa
2. Vai su **FP Reservations → Agenda**
3. Clicca su **Lista d'attesa**
4. Vedi tutte le richieste in attesa
5. Se si libera un posto:
   - Seleziona il cliente dalla lista
   - Clicca **Conferma posto**
   - Il cliente riceverà notifica automatica

---

## 🔧 Risoluzione Problemi Comuni

### Il form sul sito non mostra gli orari disponibili

**Possibili cause:**
1. Non sono stati configurati gli orari di apertura
2. C'è una chiusura speciale attiva
3. La capacità massima è stata raggiunta

**Soluzione:**
1. Vai su **Impostazioni → Generali**
2. Verifica **Orari di apertura** siano corretti
3. Controlla **Chiusure** per vedere se c'è una chiusura attiva
4. Verifica **Capacità massima** non sia troppo bassa

### Non ricevo le email di notifica

**Soluzione:**
1. Vai su **Impostazioni → Notifiche**
2. Verifica che **Email ristorante** sia corretta
3. Controlla la cartella **Spam**
4. Vai su **Diagnostica → Log Email**
5. Verifica se ci sono errori nell'invio

### Non riesco a modificare una prenotazione

**Possibili cause:**
1. La prenotazione è già passata
2. Non hai i permessi necessari

**Soluzione:**
1. Verifica di essere loggato con account corretto
2. Contatta l'amministratore per verificare i permessi
3. Per prenotazioni passate, usa **Vista Storico**

---

## 💡 Consigli per un Utilizzo Efficace

### Routine Quotidiana Consigliata

#### Mattina (30 minuti prima apertura)

1. ✅ Controlla prenotazioni del pranzo
2. ✅ Verifica tavoli assegnati
3. ✅ Leggi note speciali clienti
4. ✅ Stampa lista prenotazioni
5. ✅ Comunica al personale sala eventuali esigenze speciali

#### Pomeriggio (1 ora prima cena)

1. ✅ Controlla prenotazioni della cena
2. ✅ Verifica se ci sono modifiche dell'ultimo minuto
3. ✅ Assegna tavoli se non fatto
4. ✅ Stampa lista aggiornata
5. ✅ Verifica lista d'attesa per eventuali posti liberati

#### Fine Servizio

1. ✅ Segna come **Completate** le prenotazioni concluse
2. ✅ Segna eventuali **No-show**
3. ✅ Aggiungi note su clienti (VIP, problemi, preferenze)
4. ✅ Conferma prenotazioni del giorno dopo se necessario

### Best Practices

#### 📌 Assegnazione Tavoli

- Assegna i tavoli con almeno **2 ore di anticipo**
- Considera le **preferenze** dei clienti abituali
- Per gruppi numerosi, scegli tavoli **vicini** o **combinabili**
- Lascia tavoli strategici liberi per **walk-in** (clienti senza prenotazione)

#### 📌 Comunicazione con i Clienti

- **Rispondi rapidamente** alle richieste di modifica
- Per **eventi speciali** (compleanni, anniversari) aggiungi nota e avvisa cucina/sala
- Se devi **cancellare**, chiama sempre il cliente oltre all'email
- Per clienti **VIP** o **abituali**, aggiungi note sulle preferenze

#### 📌 Gestione Overbooking

In caso di overbooking accidentale:

1. Contatta **subito** i clienti coinvolti
2. Proponi **orari alternativi** vicini
3. Offri un **incentivo** (aperitivo, dessert omaggio)
4. Aggiungi alla **lista priorità** per prossime prenotazioni
5. Documenta l'accaduto nelle **note** per evitare ripetizioni

---

## 📞 Contatti

### Supporto Tecnico

Per problemi tecnici o domande sull'utilizzo del sistema:

- **Email**: info@francescopasseri.com
- **GitHub**: https://github.com/franpass87/FP-Restaurant-Reservations

### Amministratore del Sistema

Per modifiche alle impostazioni o gestione permessi utenti, contatta l'**amministratore WordPress** del ristorante.

---

## 📚 Risorse Aggiuntive

### Documentazione Tecnica

Se hai bisogno di documentazione più approfondita:

- **[README.md](README.md)** - Panoramica generale del plugin
- **[QUICK-START.md](QUICK-START.md)** - Guida installazione
- **[docs/README.md](docs/README.md)** - Documentazione tecnica completa

### Video Tutorial

_(Da implementare: link a video tutorial quando disponibili)_

---

## ✅ Checklist Operativa

### Setup Iniziale
- [ ] Account WordPress creato
- [ ] Password salvata in modo sicuro
- [ ] Prima esplorazione dell'agenda
- [ ] Familiarità con menu e funzioni base
- [ ] Test creazione/modifica prenotazione

### Operatività Quotidiana
- [ ] Controllo prenotazioni giornaliere (mattina)
- [ ] Assegnazione tavoli
- [ ] Verifica note speciali
- [ ] Conferma prenotazioni in sospeso
- [ ] Aggiornamento stati a fine servizio
- [ ] Gestione no-show e cancellazioni

### Manutenzione Settimanale
- [ ] Controllo prenotazioni settimana successiva
- [ ] Verifica chiusure speciali
- [ ] Review report e statistiche
- [ ] Pulizia prenotazioni vecchie/cancellate (se necessario)

---

**🎉 Buon lavoro con FP Restaurant Reservations!**

Questa guida è pensata per rendere la gestione delle prenotazioni semplice ed efficiente. Per qualsiasi dubbio, non esitare a contattare il supporto.

---

**Ultimo aggiornamento**: 2025-10-10  
**Versione plugin**: 0.1.9  
**Status**: ✅ Production Ready
