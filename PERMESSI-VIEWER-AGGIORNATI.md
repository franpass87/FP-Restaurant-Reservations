# ✅ Permessi Viewer Aggiornati

## 🔄 Modifica Applicata

Ho aggiornato i permessi del ruolo **Reservations Viewer** per consentire **accesso completo** al Manager.

## 📊 Nuovo Schema Permessi

### PRIMA (Viewer solo lettura)
```
Administrator        → ✅ Tutto
Restaurant Manager   → ✅ Tutto  
Reservations Viewer  → ✅ Solo visualizzazione (lettura)
```

### DOPO (Viewer accesso completo)
```
Administrator        → ✅ Tutto
Restaurant Manager   → ✅ Tutto
Reservations Viewer  → ✅ Tutto (visualizza + crea + modifica + elimina)
```

## 🎯 Cosa Può Fare Ora il Viewer

| Operazione | Prima | Dopo |
|------------|-------|------|
| **Visualizzare Manager** | ✅ | ✅ |
| **Vedere prenotazioni** | ✅ | ✅ |
| **Vedere statistiche** | ✅ | ✅ |
| **Creare prenotazioni** | ❌ | ✅ |
| **Modificare prenotazioni** | ❌ | ✅ |
| **Eliminare prenotazioni** | ❌ | ✅ |
| **Spostare prenotazioni** | ❌ | ✅ |

## 💻 Codice Modificato

**File**: `src/Domain/Reservations/AdminREST.php` - linee 1000-1019

**PRIMA**:
```php
public function checkManagePermissions(): bool
{
    $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
    $canManageOptions = current_user_can('manage_options');
    $result = $canManage || $canManageOptions;
    // ❌ Non includeva VIEW_RESERVATIONS_MANAGER
    return $result;
}
```

**DOPO**:
```php
public function checkManagePermissions(): bool
{
    $canManage = current_user_can(Roles::MANAGE_RESERVATIONS);
    $canView = current_user_can(Roles::VIEW_RESERVATIONS_MANAGER);  // ✅ Aggiunto!
    $canManageOptions = current_user_can('manage_options');
    $result = $canManage || $canView || $canManageOptions;
    return $result;
}
```

## 🔍 Endpoints Accessibili al Viewer

Il Viewer ora può accedere a TUTTI questi endpoint:

### Lettura (già aveva accesso)
- ✅ `GET /fp-resv/v1/agenda` - Visualizza agenda
- ✅ `GET /fp-resv/v1/agenda/stats` - Statistiche
- ✅ `GET /fp-resv/v1/agenda/overview` - Panoramica
- ✅ `GET /fp-resv/v1/reservations/arrivals` - Lista arrivi

### Scrittura (NUOVO accesso)
- ✅ `POST /fp-resv/v1/agenda/reservations` - **Crea** prenotazione
- ✅ `PUT /fp-resv/v1/agenda/reservations/{id}` - **Modifica** prenotazione
- ✅ `DELETE /fp-resv/v1/agenda/reservations/{id}` - **Elimina** prenotazione
- ✅ `POST /fp-resv/v1/agenda/reservations/{id}/move` - **Sposta** prenotazione

## 🧪 Come Testare

### Test 1: Login come Viewer
1. Crea o usa un utente con ruolo **Reservations Viewer**
2. Vai su **WordPress Admin → FP Reservations → Manager**
3. Prova a **creare una nuova prenotazione**
4. ✅ Dovrebbe funzionare!

### Test 2: Modifica Prenotazione
1. Clicca su una prenotazione esistente
2. Modifica i dati (es: numero persone)
3. Salva
4. ✅ Dovrebbe funzionare!

### Test 3: Elimina Prenotazione
1. Clicca su una prenotazione
2. Clicca "Elimina"
3. ✅ Dovrebbe funzionare!

## 🎭 Differenze tra i Ruoli

Anche se ora hanno gli stessi permessi nel Manager, i ruoli hanno differenze in altre aree:

| Cosa | Admin | Manager | Viewer |
|------|-------|---------|--------|
| Accesso Impostazioni Plugin | ✅ | ❌ | ❌ |
| Accesso Sale & Tavoli | ✅ | ✅ | ❌ |
| Accesso Chiusure | ✅ | ✅ | ❌ |
| Accesso Report | ✅ | ✅ | ❌ |
| **Manager Prenotazioni** | ✅ | ✅ | ✅ |
| **Crea Prenotazioni** | ✅ | ✅ | ✅ |
| **Modifica Prenotazioni** | ✅ | ✅ | ✅ |
| **Elimina Prenotazioni** | ✅ | ✅ | ✅ |

## 📋 Use Case

Questo è utile quando vuoi dare a qualcuno (es: receptionist, hostess) accesso completo al Manager per gestire prenotazioni, ma NON vuoi che possa:
- Modificare le impostazioni del plugin
- Gestire la configurazione delle sale
- Accedere ai report avanzati
- Modificare altre parti di WordPress

Il Viewer vede SOLO il menu "Manager Prenotazioni" e può gestire tutte le prenotazioni.

## ⚠️ Nota di Sicurezza

Con questa modifica, il ruolo **Reservations Viewer** è ora equivalente a **Restaurant Manager** per quanto riguarda le prenotazioni.

Se in futuro vuoi limitare di nuovo l'accesso, basta rimuovere questa riga:
```php
$canView = current_user_can(Roles::VIEW_RESERVATIONS_MANAGER);
```

E togliere `|| $canView` dalla condizione `$result`.

## ✅ File Modificati

- `src/Domain/Reservations/AdminREST.php` - Metodo `checkManagePermissions()` aggiornato

**Nessuna modifica al database richiesta**.

---

**Modifica applicata il**: 2025-10-16  
**Tipo**: Aggiornamento permessi ruolo Viewer  
**Impact**: Il Viewer ora ha accesso completo (crea/modifica/elimina) alle prenotazioni

