# Quick Debug: Prenotazioni Non Visualizzate

## Problema
Le prenotazioni esistono nel database ma l'API ritorna array vuoto.

## Possibili Cause

### 1. **Date Diverse** (PROBABILE)
L'agenda richiede prenotazioni per `2025-10-12` ma le prenotazioni sono per date diverse.

### 2. **Problema Query SQL**
La query con BETWEEN non funziona correttamente.

### 3. **Problema Timezone**
Le date vengono salvate in un timezone e lette in un altro.

## Test Immediati

### Test 1: Verifica Database
```bash
cd /path/to/wordpress
php wp-content/plugins/fp-restaurant-reservations/test-check-reservations.php
```

Questo mostrerà:
- Quante prenotazioni esistono
- Per quali date sono le prenotazioni
- Se la query SQL funziona

### Test 2: Test Data Specifica
```bash
php wp-content/plugins/fp-restaurant-reservations/test-agenda-date-range.php
```

Questo verifica specificamente la data `2025-10-12`.

## Soluzione Rapida

Se le prenotazioni sono per date diverse (es. 2024-10-12 invece di 2025-10-12):

### Opzione A: Cambia Data nell'Agenda
1. Apri l'agenda
2. Usa il datepicker per selezionare la data corretta
3. Le prenotazioni dovrebbero apparire

### Opzione B: Aggiorna le Date nel Database
Se le date sono sbagliate nel DB:
```sql
-- Esempio: sposta prenotazioni di un anno
UPDATE wp_fp_reservations 
SET date = DATE_ADD(date, INTERVAL 1 YEAR)
WHERE date < '2025-01-01';
```

## Output Atteso

### Se funziona:
```
[FP Resv Repository] Numero righe trovate: 5
[Agenda] ✓ Caricate 5 prenotazioni con successo
[Agenda] Rendering vista: day con 5 prenotazioni
```

### Se non funziona (attuale):
```
[FP Resv Repository] Numero righe trovate: 0
[Agenda] ⚠ Risposta API vuota, assumo nessuna prenotazione
[Agenda] ✓ Caricate 0 prenotazioni con successo
```
