# 🚀 Quick Start: Ruolo Reservations Viewer

**Tempo di setup:** 2 minuti ⏱️

## 📝 Cos'è?

Il ruolo **Reservations Viewer** permette a receptionist, staff o collaboratori di accedere **SOLO al Manager delle Prenotazioni** senza poter modificare le impostazioni del plugin o accedere ad altre aree di WordPress.

## ⚡ Setup in 3 Passi

### Passo 1️⃣: Verifica che il Ruolo Esista

Il ruolo viene creato automaticamente all'attivazione del plugin. Per verificare:

1. Vai su **Utenti** → **Aggiungi Nuovo**
2. Scorri fino al campo **Ruolo**
3. Cerca "**Reservations Viewer**" nella lista

✅ **Lo vedi?** Passa al Passo 2  
❌ **Non lo vedi?** Disattiva e riattiva il plugin

### Passo 2️⃣: Crea un Utente

**Opzione A - Interfaccia WordPress (Consigliato)**

1. Vai su **Utenti** → **Aggiungi Nuovo**
2. Compila:
   - Username: `mario.rossi`
   - Email: `mario@tuodominio.it`
   - Nome: `Mario`
   - Cognome: `Rossi`
   - **Ruolo:** `Reservations Viewer` ⭐
3. Clicca **Aggiungi Nuovo Utente**
4. WordPress invierà le credenziali via email

**Opzione B - Script Automatico (Veloce)**

1. Carica il file `create-viewer-user.php` nella root del plugin
2. Apri il file nel browser: `tuosito.it/wp-content/plugins/fp-restaurant-reservations/create-viewer-user.php`
3. L'utente verrà creato automaticamente con:
   - Username: `test_viewer`
   - Password: `ViewerTest123!`

### Passo 3️⃣: Testa l'Accesso

1. **Logout** dal tuo account admin
2. **Login** con le credenziali del nuovo utente
3. **Verifica** che vedi:
   - ✅ Menu "Prenotazioni" nella sidebar
   - ✅ Il Manager funziona
   - ❌ Nessun'altra voce di menu

## 🎯 Differenze Rapide tra i Ruoli

| Funzionalità | Administrator | Restaurant Manager | Reservations Viewer |
|--------------|--------------|-------------------|-------------------|
| **Manager Prenotazioni** | ✅ | ✅ | ✅ |
| **Impostazioni Plugin** | ✅ | ✅ | ❌ |
| **Chiusure & Orari** | ✅ | ✅ | ❌ |
| **Sale & Tavoli** | ✅ | ✅ | ❌ |
| **Report & Analytics** | ✅ | ✅ | ❌ |
| **Altre aree WordPress** | ✅ | ❌ | ❌ |

## 💡 Casi d'Uso Comuni

### 🏨 Receptionist
**Problema:** La receptionist deve gestire le prenotazioni ma non deve toccare le impostazioni.  
**Soluzione:** Ruolo `Reservations Viewer`

### 👨‍🍳 Staff Cucina
**Problema:** Lo chef vuole vedere le prenotazioni del giorno per organizzare il servizio.  
**Soluzione:** Ruolo `Reservations Viewer` (sola lettura)

### 🏢 Manager del Ristorante
**Problema:** Il manager deve gestire tutto: prenotazioni, orari, chiusure.  
**Soluzione:** Ruolo `Restaurant Manager`

### 👔 Proprietario
**Problema:** Il proprietario deve avere accesso completo a tutto WordPress.  
**Soluzione:** Ruolo `Administrator`

## 🔒 Sicurezza

### Cosa PUÒ fare un Reservations Viewer?
- ✅ Vedere tutte le prenotazioni
- ✅ Creare nuove prenotazioni manuali
- ✅ Modificare prenotazioni esistenti
- ✅ Cambiare stati (confermato, visitato, no-show, etc.)
- ✅ Vedere statistiche nel Manager
- ✅ Usare i filtri e la ricerca

### Cosa NON PUÒ fare?
- ❌ Modificare orari di apertura
- ❌ Modificare turni/meal plans
- ❌ Accedere ai report
- ❌ Vedere diagnostica e log
- ❌ Modificare impostazioni email/pagamenti
- ❌ Gestire altri utenti WordPress
- ❌ Installare plugin o temi

## 🛠️ Troubleshooting Rapido

### "Il ruolo non appare nella lista"
```
Soluzione: Disattiva e riattiva il plugin
```

### "L'utente vede altre voci di menu"
```
Controlla che:
1. Il ruolo assegnato sia esattamente "Reservations Viewer"
2. L'utente non abbia anche altri ruoli
3. Il plugin sia aggiornato
```

### "Errore 403 nel Manager"
```
Controlla:
1. L'utente è loggato correttamente
2. Il ruolo è assegnato
3. Prova a riattivare il plugin
```

### "L'utente vede il Manager ma è vuoto"
```
Questo è normale se non ci sono prenotazioni.
Crea una prenotazione di test per verificare che appaia.
```

## 🔄 Cambiare Ruolo di un Utente

### Da Viewer a Manager
1. Vai su **Utenti** → **Tutti gli Utenti**
2. Click su **Modifica** per l'utente
3. Cambia **Ruolo** in `Restaurant Manager`
4. Click su **Aggiorna Utente**

### Da Manager a Viewer
1. Vai su **Utenti** → **Tutti gli Utenti**
2. Click su **Modifica** per l'utente
3. Cambia **Ruolo** in `Reservations Viewer`
4. Click su **Aggiorna Utente**

## 📱 Test Veloce

**Vuoi testare subito senza creare un utente vero?**

1. Usa lo script `create-viewer-user.php`
2. Crea un utente di test
3. Fai i tuoi test
4. Elimina l'utente da **Utenti** → **Tutti gli Utenti**

## 💬 Comunicare le Credenziali allo Staff

Quando crei un nuovo utente Viewer per il tuo staff:

```
Ciao [Nome],

Ti ho creato un accesso al sistema di prenotazioni:

🌐 URL: tuosito.it/wp-admin
👤 Username: [username]
🔑 Password: [password]

Con questo account puoi:
✅ Vedere tutte le prenotazioni
✅ Creare nuove prenotazioni
✅ Modificare prenotazioni esistenti
✅ Cambiare lo stato delle prenotazioni

Per qualsiasi problema, contattami!

Saluti,
[Il tuo nome]
```

## 📚 Documentazione Completa

Per informazioni dettagliate, casi d'uso avanzati e configurazione:

📖 **[Guida Completa](NUOVO-RUOLO-RESERVATIONS-VIEWER.md)**

## ✅ Checklist Finale

Prima di dare l'accesso al tuo staff, verifica:

- [ ] Il ruolo "Reservations Viewer" esiste
- [ ] Hai creato l'utente con il ruolo corretto
- [ ] Hai testato l'accesso con quell'utente
- [ ] L'utente vede SOLO il menu "Prenotazioni"
- [ ] Il Manager funziona correttamente
- [ ] Hai comunicato le credenziali in modo sicuro
- [ ] Hai documentato chi ha accesso e perché

## 🎉 Fatto!

Il tuo staff può ora gestire le prenotazioni in sicurezza senza rischi di modificare configurazioni critiche!

---

**Serve aiuto?** Consulta la [guida completa](NUOVO-RUOLO-RESERVATIONS-VIEWER.md) o il [changelog](CHANGELOG-RESERVATIONS-VIEWER-ROLE.md).

