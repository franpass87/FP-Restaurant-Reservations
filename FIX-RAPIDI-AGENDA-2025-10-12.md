# ⚡ Fix Rapidi Agenda - 2025-10-12

## Prova queste soluzioni nell'ordine. Dopo ognuna, ricarica la pagina e verifica.

---

## 1️⃣ Rigenera Permalink (99% dei casi)

**Cosa risolve**: Endpoint REST API non registrato (errore 404)

**Come fare**:
1. WordPress Admin → **Impostazioni** → **Permalink**
2. Clicca **"Salva modifiche"** (senza cambiare nulla)
3. Ricarica pagina agenda (Ctrl+Shift+R)

**Verifica**: Apri `http://tuosito.com/wp-json/fp-resv/v1/agenda`
- ✅ Se vedi JSON: **FUNZIONA**
- ❌ Se vedi 404: passa al fix 2

---

## 2️⃣ Svuota Cache Browser

**Cosa risolve**: JavaScript vecchio in cache

**Come fare**:
- **Chrome/Edge**: `Ctrl + Shift + R` (Windows) o `Cmd + Shift + R` (Mac)
- **Firefox**: `Ctrl + Shift + Delete` → Seleziona cache → Svuota
- **Safari**: `Cmd + Option + E`

**Verifica**: Console deve mostrare `[Agenda] 🚀 Inizializzazione...`

---

## 3️⃣ Disabilita Plugin Cache

**Cosa risolve**: Cache WordPress blocca API

**Come fare**:
1. WordPress Admin → **Plugin**
2. Cerca plugin di cache (WP Rocket, W3 Total Cache, etc.)
3. **Disabilita** temporaneamente
4. **Svuota cache** se c'è l'opzione

**Verifica**: Ricarica agenda

---

## 4️⃣ Ricompila Asset JavaScript

**Cosa risolve**: File JavaScript mancanti o corrotti

**Come fare**:
```bash
cd wp-content/plugins/fp-restaurant-reservations
npm install
npm run build
```

**Oppure** se composer non funziona:
```bash
composer install --no-dev
npm install
npm run build
```

**Verifica**: File `assets/js/admin/agenda-app.js` deve esistere

---

## 5️⃣ Verifica Permessi Utente

**Cosa risolve**: Errore 403 Forbidden

**Come fare**:
1. WordPress Admin → **Utenti** → **Il tuo profilo**
2. Verifica ruolo: deve essere **"Administrator"**
3. Oppure avere capability `manage_fp_reservations`

**Verifica rapida**: Esegui in console browser:
```javascript
console.log('Admin:', wp.data.select('core').getCurrentUser());
```

---

## 6️⃣ Controlla File Presenti

**Cosa risolve**: File mancanti

**Verifica che esistano**:
- ✅ `vendor/autoload.php`
- ✅ `assets/js/admin/agenda-app.js`
- ✅ `assets/css/admin-agenda.css`
- ✅ `src/Admin/Views/agenda.php`
- ✅ `src/Domain/Reservations/AdminREST.php`

**Se manca autoload.php**:
```bash
cd wp-content/plugins/fp-restaurant-reservations
composer install --no-dev --optimize-autoloader
```

---

## 7️⃣ Crea Prenotazione di Test

**Cosa risolve**: Database vuoto mostra "Nessuna prenotazione"

**SQL da eseguire** (phpMyAdmin o wp-cli):
```sql
INSERT INTO wp_fp_reservations (date, time, party, status, customer_id, created_at, updated_at)
VALUES 
('2025-10-12', '19:30:00', 4, 'confirmed', NULL, NOW(), NOW()),
('2025-10-12', '20:00:00', 2, 'confirmed', NULL, NOW(), NOW());
```

**Verifica**: Agenda deve mostrare 2 prenotazioni

---

## 8️⃣ Controlla .htaccess

**Cosa risolve**: REST API bloccata da regole .htaccess

**File**: `/.htaccess` nella root di WordPress

**Verifica che NON ci siano** queste righe:
```apache
# MALE - blocca REST API
RewriteRule ^wp-json/(.*)$ - [F,L]
```

**Deve esserci** qualcosa tipo:
```apache
# BENE
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

---

## 9️⃣ Verifica REST API Generale

**Cosa risolve**: REST API completamente disabilitata

**Test**:
1. Apri: `http://tuosito.com/wp-json/`
2. Dovresti vedere JSON con info WordPress

**Se vedi 404**: REST API disabilitata nel tema/plugin
```php
// Cerca nei file del tema/plugin questa riga CATTIVA:
add_filter('rest_authentication_errors', '__return_false');
```

---

## 🔟 Riavvia PHP-FPM / Apache

**Cosa risolve**: Cache opcode PHP

**Apache**:
```bash
sudo service apache2 restart
```

**Nginx + PHP-FPM**:
```bash
sudo service php8.1-fpm restart
sudo service nginx restart
```

**XAMPP/WAMP**: Riavvia dal pannello di controllo

---

## 🆘 Se NULLA Funziona

### Esegui diagnostica completa:

```bash
# Da terminale
php test-agenda-quick.php
```

**Oppure** da browser:
```
http://tuosito.com/DIAGNOSTICA-AGENDA-COMPLETA.php
```

### E condividi:

1. ✅ Output diagnostica completa
2. ✅ Screenshot Console browser (F12)
3. ✅ Screenshot Network tab (F12 → Network → cerca "agenda")
4. ✅ Versione WordPress e PHP
5. ✅ Eventuali errori in `wp-content/debug.log`

---

## 📋 Checklist Verifica Finale

Dopo ogni fix, verifica TUTTI questi punti:

- [ ] Console mostra `[Agenda] 🚀 Inizializzazione...`
- [ ] Console mostra `[Agenda] ✅ Dati caricati: X prenotazioni`
- [ ] Nessun errore rosso in console
- [ ] Endpoint `http://tuosito.com/wp-json/fp-resv/v1/agenda` restituisce JSON
- [ ] Pulsanti "Giorno", "Settimana", "Mese" cambiano vista
- [ ] Date picker funziona
- [ ] Pulsante "Nuova prenotazione" apre modale

---

## 🎯 Priorità dei Fix

**Prova nell'ordine**:
1. Rigenera Permalink (1 minuto) ← **INIZIA DA QUI**
2. Svuota Cache (1 minuto)
3. Console Browser (5 minuti) ← **FONDAMENTALE**
4. Diagnostica Completa (2 minuti)

**Gli altri fix sono necessari solo se i primi 4 non hanno risolto.**

---

**Ultimo aggiornamento**: 2025-10-12
**Versione plugin**: 0.1.6+
**Compatibilità**: WordPress 6.5+, PHP 8.1+

