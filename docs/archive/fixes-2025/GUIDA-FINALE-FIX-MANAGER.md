# 🎯 GUIDA FINALE: Fix Manager Prenotazioni

**Problema:** Manager non mostra prenotazioni  
**Causa:** File AdminREST.php ha troppo codice vecchio/commentato che causa fatal error  
**Soluzione:** File pulito con solo codice funzionante

---

## 📦 FILE DA SOSTITUIRE COMPLETAMENTE

Il file `src/Domain/Reservations/AdminREST.php` ha accumulato troppo codice rotto.

### ✅ COSA DEVI FARE:

1. **Scarica** il file dal repository (versione pulita dal commit precedente)
2. **Oppure** sostituisci SOLO il metodo `handleAgenda()`

---

## 🔧 METODO HANDLEAGENDA PULITO

Sostituisci il metodo `handleAgenda` con questo codice pulito:

```php
public function handleAgenda(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    try {
        // Parametri dalla richiesta
        $dateParam = $request->get_param('date');
        $rangeParam = $request->get_param('range');
        
        // Sanitizza data
        $date = is_string($dateParam) ? sanitize_text_field($dateParam) : gmdate('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = gmdate('Y-m-d');
        }
        
        // Range
        $rangeMode = is_string($rangeParam) ? strtolower($rangeParam) : 'day';
        if (!in_array($rangeMode, ['day', 'week', 'month'], true)) {
            $rangeMode = 'day';
        }
        
        // Calcola date range
        $start = new \DateTimeImmutable($date);
        
        if ($rangeMode === 'week') {
            $dayOfWeek = (int)$start->format('N');
            $start = $start->modify('-' . ($dayOfWeek - 1) . ' days');
            $end = $start->add(new \DateInterval('P6D'));
        } elseif ($rangeMode === 'month') {
            $start = $start->modify('first day of this month');
            $end = $start->modify('last day of this month');
        } else {
            $end = $start;
        }
        
        // Query database
        $rows = $this->reservations->findAgendaRange($start->format('Y-m-d'), $end->format('Y-m-d'));
        
        // Mappa prenotazioni
        $reservations = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                
                $reservations[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'status' => (string)($row['status'] ?? 'pending'),
                    'date' => (string)($row['date'] ?? ''),
                    'time' => substr((string)($row['time'] ?? ''), 0, 5),
                    'party' => (int)($row['party'] ?? 0),
                    'meal' => $row['meal'] ?? null,
                    'first_name' => (string)($row['first_name'] ?? ''),
                    'last_name' => (string)($row['last_name'] ?? ''),
                    'email' => (string)($row['email'] ?? ''),
                    'phone' => (string)($row['phone'] ?? ''),
                    'notes' => (string)($row['notes'] ?? ''),
                    'allergies' => (string)($row['allergies'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
        
        // Statistiche semplici
        $totalReservations = count($reservations);
        $totalGuests = $totalReservations > 0 ? array_sum(array_column($reservations, 'party')) : 0;
        
        $statusCounts = [
            'pending' => 0,
            'confirmed' => 0,
            'visited' => 0,
            'no_show' => 0,
            'cancelled' => 0,
        ];
        
        foreach ($reservations as $r) {
            $status = $r['status'];
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }
        
        // Risposta
        return new WP_REST_Response([
            'meta' => [
                'range' => $rangeMode,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'current_date' => $date,
            ],
            'stats' => [
                'total_reservations' => $totalReservations,
                'total_guests' => $totalGuests,
                'by_status' => $statusCounts,
                'confirmed_percentage' => $totalReservations > 0 ? round(($statusCounts['confirmed'] / $totalReservations) * 100) : 0,
            ],
            'data' => [
                'slots' => [],
                'timeline' => [],
            ],
            'reservations' => $reservations,
        ], 200);
        
    } catch (\Throwable $e) {
        return new WP_REST_Response([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
}
```

---

## 📝 ISTRUZIONI PASSO-PASSO:

### 1. Apri il file sul server:
```
wp-content/plugins/fp-restaurant-reservations/src/Domain/Reservations/AdminREST.php
```

### 2. Trova il metodo `handleAgenda`:
Cerca: `public function handleAgenda(`

### 3. Sostituisci TUTTO il metodo con il codice sopra

### 4. Salva il file

### 5. Test:
```javascript
fetch('/wp-json/fp-resv/v1/agenda?range=month', {credentials: 'include'})
    .then(r => r.json())
    .then(data => console.log('Prenotazioni:', data.reservations.length))
```

---

## ✅ FILE COMPLETI DA CARICARE:

1. `src/Domain/Reservations/AdminREST.php` (con metodo pulito)
2. `assets/js/admin/manager-app.js` (con fix meal NULL)
3. `assets/js/admin/agenda-app.js` (con fix meal NULL)

---

## 🎯 RISULTATO ATTESO:

Dopo il fix:
- ✅ Endpoint `/wp-json/fp-resv/v1/agenda` restituisce JSON con 12 prenotazioni
- ✅ Manager visualizza tutte le 12 prenotazioni
- ✅ Filtro meal funziona (prenotazioni con meal NULL vengono sempre mostrate)

---

## 🆘 SE NON RIESCI A MODIFICARE IL FILE:

**Mandam il file `AdminREST.php` attuale dal server** e te lo pulisco completamente io.


