# Nuovo Ruolo: Reservations Viewer

## 📋 Panoramica

È stato creato un nuovo ruolo WordPress chiamato **Reservations Viewer** (`fp_reservations_viewer`) che permette agli utenti di accedere **SOLO al Manager delle Prenotazioni** senza poter visualizzare o modificare le impostazioni del plugin.

## 🎯 Differenze tra i Ruoli

### 1. **Administrator** (Amministratore)
- ✅ Accesso completo a WordPress
- ✅ Accesso a tutte le pagine del plugin
- ✅ Può modificare tutte le impostazioni
- ✅ Accesso al Manager delle prenotazioni

### 2. **Restaurant Manager** (`fp_restaurant_manager`)
- ✅ Accesso al Manager delle prenotazioni
- ✅ Accesso a tutte le pagine del plugin (Impostazioni, Chiusure, Sale & Tavoli, Report)
- ✅ Può modificare tutte le impostazioni del plugin
- ❌ NON ha accesso alle altre funzionalità di WordPress

### 3. **Reservations Viewer** (`fp_reservations_viewer`) - **NUOVO** ⭐
- ✅ Accesso **SOLO** al Manager delle prenotazioni
- ❌ NON può accedere alle Impostazioni
- ❌ NON può accedere a Chiusure
- ❌ NON può accedere a Sale & Tavoli
- ❌ NON può accedere a Report
- ❌ NON ha accesso alle altre funzionalità di WordPress

## 🔑 Capabilities (Permessi)

### Restaurant Manager
```php
- manage_fp_reservations (gestione completa)
- view_fp_reservations_manager (visualizzazione manager)
- read (accesso backend WordPress)
- upload_files (caricamento file)
```

### Reservations Viewer
```php
- view_fp_reservations_manager (visualizzazione manager)
- read (accesso backend WordPress)
```

## 🚀 Come Assegnare il Ruolo

### Metodo 1: Da WordPress Admin
1. Vai su **Utenti** → **Aggiungi Nuovo**
2. Compila i dati dell'utente
3. Nel campo **Ruolo**, seleziona **Reservations Viewer**
4. Clicca su **Aggiungi Nuovo Utente**

### Metodo 2: Modificare un Utente Esistente
1. Vai su **Utenti** → **Tutti gli Utenti**
2. Clicca su **Modifica** per l'utente desiderato
3. Cambia il **Ruolo** in **Reservations Viewer**
4. Clicca su **Aggiorna Utente**

### Metodo 3: Programmaticamente
```php
// Assegna il ruolo a un utente esistente
$user_id = 123; // ID dell'utente
$user = new WP_User($user_id);
$user->set_role('fp_reservations_viewer');

// Oppure durante la creazione di un nuovo utente
$user_id = wp_insert_user([
    'user_login' => 'mario.rossi',
    'user_email' => 'mario@example.com',
    'user_pass'  => wp_generate_password(),
    'role'       => 'fp_reservations_viewer',
]);
```

## 📱 Esperienza Utente

Quando un utente con il ruolo **Reservations Viewer** accede alla dashboard di WordPress, vedrà:

- **Menu laterale**: Solo la voce **"Prenotazioni"** (con l'icona del clipboard)
- **Contenuto**: Il Manager delle prenotazioni con tutte le sue funzionalità
- **Nessuna altra voce**: Non vedrà le altre pagine del plugin o di WordPress

## 🔧 Implementazione Tecnica

### File Modificati

1. **`src/Core/Roles.php`**
   - Aggiunta costante `VIEW_RESERVATIONS_MANAGER`
   - Aggiunta costante `RESERVATIONS_VIEWER`
   - Nuovo metodo `getReservationsViewerCapabilities()`
   - Aggiornato `create()` per creare il nuovo ruolo
   - Aggiornato `remove()` per rimuovere il nuovo ruolo
   - Aggiornato `addCapabilityToAdministrators()` per includere la nuova capability

2. **`src/Domain/Reservations/AdminController.php`**
   - Modificato `registerMenu()` per creare un menu principale per gli utenti Viewer
   - Gli utenti con accesso completo vedono il Manager come submenu
   - Gli utenti Viewer vedono il Manager come menu principale

3. **`src/Domain/Reservations/AdminREST.php`**
   - Aggiornato `checkPermissions()` per accettare anche `VIEW_RESERVATIONS_MANAGER`
   - Gli endpoint REST API ora sono accessibili anche ai Viewer

## 🔄 Attivazione del Ruolo

Il ruolo viene creato automaticamente quando:
- Il plugin viene attivato per la prima volta
- Il plugin viene riattivato
- Viene eseguito un aggiornamento del plugin

Se il ruolo non è visibile, puoi forzare la ricreazione disattivando e riattivando il plugin.

## ⚙️ Configurazione Avanzata

### Aggiungere altre Capabilities al Viewer

Se in futuro vorrai dare più permessi al ruolo Viewer, puoi modificare il metodo in `src/Core/Roles.php`:

```php
private static function getReservationsViewerCapabilities(): array
{
    return [
        self::VIEW_RESERVATIONS_MANAGER => true,
        'read' => true,
        // Aggiungi altre capabilities qui se necessario
        // 'upload_files' => true,
    ];
}
```

### Verificare le Capabilities di un Utente

```php
// Controlla se un utente ha accesso al manager
if (current_user_can('view_fp_reservations_manager')) {
    echo 'Utente ha accesso al manager';
}

// Controlla se un utente ha accesso completo
if (current_user_can('manage_fp_reservations')) {
    echo 'Utente ha accesso completo al plugin';
}
```

## 📊 Casi d'Uso

### Caso 1: Receptionist
Un receptionist del ristorante ha bisogno di vedere e gestire le prenotazioni, ma non deve poter modificare gli orari di apertura, i prezzi o altre impostazioni.

**Soluzione**: Assegna il ruolo **Reservations Viewer**

### Caso 2: Manager del Ristorante
Il manager ha bisogno di accesso completo per gestire prenotazioni, orari, chiusure e report.

**Soluzione**: Assegna il ruolo **Restaurant Manager**

### Caso 3: Proprietario
Il proprietario ha bisogno di accesso completo a WordPress e a tutti i plugin.

**Soluzione**: Mantieni il ruolo **Administrator**

## 🔒 Sicurezza

- Tutti gli endpoint REST API controllano correttamente le capabilities
- Gli utenti Viewer non possono accedere a URL diretti delle pagine che non hanno permesso
- Il sistema di WordPress blocca automaticamente l'accesso non autorizzato
- Le capability vengono controllate sia lato server che lato client

## 🐛 Troubleshooting

### Il ruolo non appare nella lista
1. Disattiva il plugin
2. Riattiva il plugin
3. Il ruolo dovrebbe apparire

### Un utente Viewer vede altre pagine
1. Verifica che il ruolo sia correttamente assegnato
2. Controlla che non abbia altri ruoli assegnati contemporaneamente
3. Verifica che il plugin sia aggiornato all'ultima versione

### Gli endpoint REST non funzionano
1. Verifica che l'utente sia loggato
2. Controlla i log del server per errori di permessi
3. Verifica che il nonce sia corretto

## 📝 Note

- Il ruolo viene creato con il nome tradotto "Reservations Viewer" (potrai tradurlo in italiano nelle traduzioni)
- Il ruolo è compatibile con tutti i plugin di gestione ruoli di WordPress
- Gli amministratori mantengono sempre l'accesso completo
- Il ruolo può essere modificato o esteso in futuro senza problemi

## 🌐 Traduzioni

Per tradurre il nome del ruolo in italiano, aggiungi queste righe ai file di traduzione:

```
msgid "Reservations Viewer"
msgstr "Visualizzatore Prenotazioni"
```

## ✅ Conclusione

Il nuovo ruolo **Reservations Viewer** permette di dare accesso limitato al Manager delle Prenotazioni, ideale per staff del ristorante che non ha bisogno di modificare le impostazioni del sistema.

