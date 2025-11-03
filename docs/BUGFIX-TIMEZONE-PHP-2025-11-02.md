# 🌍 BUGFIX - Timezone PHP Functions (Europe/Rome)

**Data:** 2 Novembre 2025  
**Criticità:** 🔴 **ALTA** - Orari sbagliati in output  
**Area:** PHP Backend - Date/Time Functions  
**Versione:** 0.9.0-rc6 (draft)

---

## ❌ **PROBLEMA IDENTIFICATO**

Il plugin utilizzava funzioni PHP native `date()` e `gmdate()` che **NON rispettano** il timezone di WordPress, causando orari sbagliati visualizzati nel backend e nei log.

### Funzioni problematiche trovate:

#### 1️⃣ **`gmdate()` - Sempre UTC**
- ❌ `gmdate('Y-m-d')` restituisce sempre la data in UTC
- ❌ Ignorava completamente il timezone 'Europe/Rome' configurato

#### 2️⃣ **`date()` - Timezone PHP di sistema**
- ❌ `date('Y-m-d')` usa il timezone del server PHP (non WordPress)
- ❌ Potrebbe essere diverso da 'Europe/Rome'

---

## 🔍 **FILE CORRETTI**

### ✅ FIX 1/7 - AdminREST.php
**File:** `src/Domain/Reservations/AdminREST.php`

```diff
- error_log('[FP Resv AdminREST] Timestamp: ' . date('Y-m-d H:i:s'));
+ error_log('[FP Resv AdminREST] Timestamp: ' . wp_date('Y-m-d H:i:s'));

- $date = gmdate('Y-m-d');  // ❌ UTC
+ $date = current_time('Y-m-d');  // ✅ Timezone WordPress

- $date = gmdate('Y-m-d');  // in handleAgendaV2()
+ $date = current_time('Y-m-d');

- $date = gmdate('Y-m-d');  // in mapAgendaReservation()
+ $date = current_time('Y-m-d');
```

**Occorrenze fixate:** 4  
**Impatto:** Log, statistiche agenda, mapping prenotazioni

---

### ✅ FIX 2/7 - Shortcodes.php
**File:** `src/Frontend/Shortcodes.php`

```diff
- $timestamp = date('Y-m-d H:i:s');
+ $timestamp = wp_date('Y-m-d H:i:s');

- $today = date('Y-m-d');
+ $today = current_time('Y-m-d');

- $testDate = date('Y-m-d');
+ $testDate = current_time('Y-m-d');
```

**Occorrenze fixate:** 3  
**Impatto:** Debug shortcode, statistiche prenotazioni, test endpoint REST

---

### ✅ FIX 3/7 - REST.php
**File:** `src/Domain/Reservations/REST.php`

```diff
- $from = $request->get_param('from') ?: date('Y-m-d');
- $to = $request->get_param('to') ?: date('Y-m-d', strtotime('+3 months'));
+ $from = $request->get_param('from') ?: current_time('Y-m-d');
+ $to = $request->get_param('to') ?: wp_date('Y-m-d', strtotime('+3 months'));

- $dateKey = date('Y-m-d', $timestamp);
- $dayName = strtolower(date('D', $timestamp));
+ $dateKey = wp_date('Y-m-d', $timestamp);
+ $dayName = strtolower(wp_date('D', $timestamp));
```

**Occorrenze fixate:** 6 (2 iniziali + 4 nel loop giorni disponibili)  
**Impatto:** Endpoint `/available-days`, calcolo disponibilità

---

### ✅ FIX 4/7 - Service.php
**File:** `src/Domain/Reservations/Service.php`

```diff
  private function sanitizePayload(array $payload): array
  {
      $defaults = [
-         'date' => gmdate('Y-m-d'),  // ❌ UTC
+         'date' => current_time('Y-m-d'),  // ✅ Timezone WordPress

- $sanitized['consent_timestamp'] = gmdate('Y-m-d H:i:s', (int) $consentMeta['updated_at']);
+ $sanitized['consent_timestamp'] = wp_date('Y-m-d H:i:s', (int) $consentMeta['updated_at']);
```

**Occorrenze fixate:** 2  
**Impatto:** Data default prenotazioni, timestamp consenso privacy

---

### ✅ FIX 5/7 - Repository.php
**File:** `src/Domain/Reservations/Repository.php`

```diff
- $minTimestamp = gmdate('Y-m-d H:i:s', time() - $withinSeconds);
+ $minTimestamp = wp_date('Y-m-d H:i:s', time() - $withinSeconds);

- $reservation->calendarSyncedAt = new DateTimeImmutable((string) $row['calendar_synced_at']);
+ $reservation->calendarSyncedAt = new DateTimeImmutable((string) $row['calendar_synced_at'], wp_timezone());
```

**Occorrenze fixate:** 3 (1 + 2 DateTimeImmutable)  
**Impatto:** Query duplicati, sincronizzazione Google Calendar

---

### ✅ FIX 6/7 - AdminREST.php (DateTimeImmutable)
**File:** `src/Domain/Reservations/AdminREST.php`

```diff
- $date = new DateTimeImmutable($resv['date']);
+ $date = new DateTimeImmutable($resv['date'], wp_timezone());
```

**Occorrenze fixate:** 1  
**Impatto:** Statistiche per giorno della settimana

---

### ✅ FIX 7/7 - Plugin.php (Versione)
**File:** `src/Core/Plugin.php`

```diff
- const VERSION = '0.9.0-rc1';
+ const VERSION = '0.9.0-rc5';
```

**Impatto:** Allineamento versione con file principale

---

## ✅ **GMDATE() CORRETTI - NESSUN FIX NECESSARIO**

Questi usi di `gmdate()` sono **corretti** perché devono essere UTC:

### 1. TemplateRenderer.php
```php
'emails.year' => esc_html((string) gmdate('Y')),
```
✅ **OK** - Solo l'anno per copyright email (uguale in UTC e Roma)

### 2. Diagnostics/Service.php
```php
'filename' => sprintf('fp-resv-%s-logs-%s.csv', $channel, gmdate('Ymd-His')),
```
✅ **OK** - Nome file export con timestamp UTC (best practice)

### 3. ICS.php
```php
$lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
```
✅ **OK** - Formato iCalendar RFC 5545 richiede UTC (suffisso `\Z`)

---

## 📊 **RIEPILOGO CORREZIONI**

| File | Tipo Fix | Occorrenze | Funzione |
|------|----------|-----------|----------|
| AdminREST.php | `date()` → `wp_date()` | 1 | Log timestamp |
| AdminREST.php | `gmdate()` → `current_time()` | 3 | Date default |
| Shortcodes.php | `date()` → `wp_date()` / `current_time()` | 3 | Debug/test |
| REST.php | `date()` → `wp_date()` / `current_time()` | 6 | API giorni disponibili |
| Service.php | `gmdate()` → `current_time()` / `wp_date()` | 2 | Defaults payload |
| Repository.php | `gmdate()` → `wp_date()` | 1 | Query duplicati |
| Repository.php | `DateTimeImmutable` senza tz | 2 | Google Calendar sync |
| AdminREST.php | `DateTimeImmutable` senza tz | 1 | Statistiche |
| Plugin.php | Versione | 1 | Sync versione |

**TOTALE:** **20 correzioni** + **3 verifiche OK**

---

## 🧪 **COME TESTARE**

### Test 1: Verifica Timezone WordPress
```php
// WP Admin > Impostazioni > Generali
Fuso Orario: Europe/Rome
```

### Test 2: Verifica Output Date
```php
// Aggiungi in functions.php temporaneamente
add_action('init', function() {
    error_log('=== TIMEZONE TEST ===');
    error_log('WP Timezone: ' . wp_timezone_string());
    error_log('current_time(Y-m-d H:i:s): ' . current_time('Y-m-d H:i:s'));
    error_log('wp_date(Y-m-d H:i:s): ' . wp_date('Y-m-d H:i:s'));
    error_log('gmdate(Y-m-d H:i:s): ' . gmdate('Y-m-d H:i:s'));
    error_log('date(Y-m-d H:i:s): ' . date('Y-m-d H:i:s'));
});
```

**Risultato atteso (se ora italiana è 14:30):**
```
WP Timezone: Europe/Rome
current_time(Y-m-d H:i:s): 2025-11-02 14:30:xx  ✅
wp_date(Y-m-d H:i:s): 2025-11-02 14:30:xx       ✅
gmdate(Y-m-d H:i:s): 2025-11-02 13:30:xx        ⚠️ UTC (1h indietro)
date(Y-m-d H:i:s): [dipende da php.ini]         ⚠️ Timezone PHP
```

### Test 3: Crea Prenotazione da Admin
1. Vai su Manager Prenotazioni
2. Crea nuova prenotazione manuale
3. Verifica che data/ora siano corrette nel database

### Test 4: Verifica Log
1. Controlla `wp-content/debug.log`
2. Cerca timestamp di AdminREST
3. Verifica che corrispondano all'ora italiana corrente

---

## ⚠️ **BEST PRACTICES - PROMEMORIA SVILUPPATORI**

### ❌ **MAI USARE**
```php
gmdate('Y-m-d')              // ❌ Sempre UTC, ignora timezone WP
date('Y-m-d')                // ❌ Usa timezone PHP di sistema
new DateTimeImmutable($str)  // ❌ Senza timezone esplicito
```

### ✅ **SEMPRE USARE**
```php
current_time('Y-m-d')        // ✅ Data corrente timezone WP
current_time('mysql')        // ✅ Datetime corrente timezone WP
wp_date('Y-m-d', $timestamp) // ✅ Formatta timestamp con timezone WP
new DateTimeImmutable($str, wp_timezone())  // ✅ Con timezone esplicito
```

### 📌 **ECCEZIONI - Quando gmdate() è OK**
```php
gmdate('Y')                  // ✅ Solo anno (uguale ovunque)
gmdate('Ymd-His')            // ✅ Nome file (UTC è best practice)
gmdate('Ymd\THis\Z')         // ✅ iCalendar DTSTAMP (RFC richiede UTC)
```

---

## 🎯 **COMPATIBILITÀ**

✅ **WordPress 6.5+**: Usa `wp_timezone()` e `wp_date()` (disponibili da WP 5.3)  
✅ **PHP 8.1+**: `DateTimeImmutable` usato correttamente  
✅ **Multilingua**: Formattazione rispetta impostazioni lingua  
✅ **DST**: Cambio ora legale/solare gestito automaticamente da WordPress  

---

## 📈 **IMPATTO**

### PRIMA (Bug)
- ❌ Log con orari UTC (1-2h differenza)
- ❌ Statistiche "oggi" sbagliate vicino a mezzanotte UTC
- ❌ Default date in UTC nelle API
- ❌ Google Calendar sync con timestamp sbagliato

### DOPO (Fix)
- ✅ Tutti gli orari in timezone Europe/Rome
- ✅ Log coerenti con ora italiana
- ✅ Statistiche corrette 24/7
- ✅ API con date corrette
- ✅ Google Calendar sync accurato

---

## ✅ **RISULTATO FINALE**

**Status:** ✅ Completato  
**Testing richiesto:** Consigliato (specialmente log e statistiche)  
**Breaking Changes:** ❌ Nessuno  
**Versione target:** 0.9.0-rc6

---

**Autore:** Francesco Passeri  
**Data Fix:** 2 Novembre 2025  
**Commit:** [da assegnare]

