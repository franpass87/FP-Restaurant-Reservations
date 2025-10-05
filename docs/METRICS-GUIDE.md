# Guida Sistema Metriche

## Introduzione

Il sistema di metriche integrato permette di monitorare performance e comportamento del plugin in produzione.

## Tipi di Metriche

### 1. Timing (Durata Operazioni)

Misura quanto tempo impiega un'operazione.

```php
use FP\Resv\Core\Metrics;

// Metodo 1: Timer automatico
$stopTimer = Metrics::timer('availability.calculation', [
    'party' => 4,
    'meal' => 'dinner',
]);

$result = $availability->findSlots($criteria);

$stopTimer(); // Registra automaticamente il tempo trascorso
```

```php
// Metodo 2: Timing manuale
$start = microtime(true);

// ... operazione ...

$duration = microtime(true) - $start;
Metrics::timing('operation.name', $duration, ['tag' => 'value']);
```

**Quando usare**: Per operazioni potenzialmente lente (query DB, API calls, calcoli complessi).

### 2. Counter (Incremento Contatori)

Conta occorrenze di eventi.

```php
// Incremento semplice
Metrics::increment('reservation.created');

// Incremento con valore e tags
Metrics::increment('email.sent', 1, [
    'type' => 'confirmation',
    'status' => 'success',
]);

// Decremento (usa valore negativo)
Metrics::increment('queue.size', -1);
```

**Quando usare**: Per contare eventi (prenotazioni create, email inviate, errori).

### 3. Gauge (Valori Puntuali)

Registra un valore in un momento specifico.

```php
Metrics::gauge('availability.slots_available', 12, [
    'date' => '2025-10-05',
    'meal' => 'dinner',
]);

Metrics::gauge('queue.size', 42);
Metrics::gauge('cache.hit_ratio', 0.85);
```

**Quando usare**: Per valori che cambiano nel tempo (dimensione coda, disponibilità, ratio).

## Metriche Implementate

### Availability

| Metrica | Tipo | Tags | Descrizione |
|---------|------|------|-------------|
| `availability.calculation` | timing | party, meal | Tempo calcolo slot singolo giorno |
| `availability.calculation_batch` | timing | party, days | Tempo calcolo batch multi-giorno |
| `availability.slots_found` | gauge | date, party | Numero slot trovati |
| `availability.batch_days_processed` | gauge | - | Giorni processati in batch |
| `availability.rate_limited` | counter | - | Richieste rate-limited |
| `availability.cache_hit` | counter | type | Cache hit (memory/transient) |
| `availability.cache_miss` | counter | - | Cache miss |

### Reservation

| Metrica | Tipo | Tags | Descrizione |
|---------|------|------|-------------|
| `reservation.create` | timing | party | Tempo creazione prenotazione |
| `reservation.created` | counter | status, requires_payment | Prenotazioni create |

### Cache

| Metrica | Tipo | Tags | Descrizione |
|---------|------|------|-------------|
| `cache.invalidated` | counter | type | Cache invalidate (rooms/tables/availability/all) |
| `cache.warmed_up` | counter | - | Cache pre-caricate |
| `cache.warmup` | timing | - | Tempo pre-caricamento |

## Integrazione con Sistemi di Monitoring

### Datadog

```php
// functions.php o mu-plugin
add_filter('fp_resv_metrics_handler', function() {
    return function($entry) {
        if (!class_exists('DataDogStatsD')) {
            return;
        }
        
        $statsd = new DataDogStatsD([
            'host' => 'localhost',
            'port' => 8125,
        ]);
        
        $tags = [];
        foreach ($entry['tags'] as $key => $value) {
            $tags[] = "{$key}:{$value}";
        }
        
        switch ($entry['type']) {
            case 'timing':
                $statsd->timing($entry['metric'], $entry['value'], $tags);
                break;
            case 'counter':
                $statsd->increment($entry['metric'], $entry['value'], $tags);
                break;
            case 'gauge':
                $statsd->gauge($entry['metric'], $entry['value'], $tags);
                break;
        }
    };
});

// Abilita invio metriche
define('FP_RESV_METRICS_ENABLED', true);
```

### New Relic

```php
add_filter('fp_resv_metrics_handler', function() {
    return function($entry) {
        if (!extension_loaded('newrelic')) {
            return;
        }
        
        switch ($entry['type']) {
            case 'timing':
                newrelic_custom_metric(
                    'Custom/' . $entry['metric'],
                    $entry['value']
                );
                break;
            case 'counter':
                newrelic_custom_metric(
                    'Custom/Count/' . $entry['metric'],
                    $entry['value']
                );
                break;
        }
    };
});

define('FP_RESV_METRICS_ENABLED', true);
```

### CloudWatch (AWS)

```php
add_filter('fp_resv_metrics_handler', function() {
    return function($entry) {
        $cloudwatch = new Aws\CloudWatch\CloudWatchClient([
            'region' => 'us-east-1',
            'version' => 'latest',
        ]);
        
        $metric = [
            'MetricName' => $entry['metric'],
            'Value' => $entry['value'],
            'Unit' => $entry['type'] === 'timing' ? 'Milliseconds' : 'Count',
            'Timestamp' => $entry['timestamp'],
        ];
        
        if (!empty($entry['tags'])) {
            $metric['Dimensions'] = [];
            foreach ($entry['tags'] as $key => $value) {
                $metric['Dimensions'][] = [
                    'Name' => $key,
                    'Value' => (string) $value,
                ];
            }
        }
        
        $cloudwatch->putMetricData([
            'Namespace' => 'FPRestaurantReservations',
            'MetricData' => [$metric],
        ]);
    };
});

define('FP_RESV_METRICS_ENABLED', true);
```

### Custom Log File

```php
add_filter('fp_resv_metrics_handler', function() {
    return function($entry) {
        $logFile = WP_CONTENT_DIR . '/uploads/fp-resv-metrics.log';
        
        $line = sprintf(
            "[%s] %s %s: %s %s\n",
            date('Y-m-d H:i:s', (int) $entry['timestamp']),
            strtoupper($entry['type']),
            $entry['metric'],
            $entry['value'],
            !empty($entry['tags']) ? json_encode($entry['tags']) : ''
        );
        
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    };
});

define('FP_RESV_METRICS_ENABLED', true);
```

## Hook per Metriche Custom

### Action Hook

Tutte le metriche triggerano l'action `fp_resv_metric`:

```php
add_action('fp_resv_metric', function($entry) {
    // $entry structure:
    // [
    //   'type' => 'timing|counter|gauge',
    //   'metric' => 'metric.name',
    //   'value' => 42.5,
    //   'tags' => ['key' => 'value'],
    //   'timestamp' => 1234567890.123,
    // ]
    
    // Log solo metriche timing lente
    if ($entry['type'] === 'timing' && $entry['value'] > 1000) {
        error_log("Slow operation: {$entry['metric']} took {$entry['value']}ms");
    }
}, 10, 1);
```

## Dashboard Metriche (Esempio)

```php
// Admin page per visualizzare metriche in-memory (dev)
add_action('admin_menu', function() {
    add_submenu_page(
        'fp-resv-settings',
        'Metriche',
        'Metriche',
        'manage_options',
        'fp-resv-metrics',
        function() {
            if (!defined('FP_RESV_METRICS_ENABLED') || !FP_RESV_METRICS_ENABLED) {
                echo '<div class="notice notice-warning"><p>Metriche disabilitate.</p></div>';
                return;
            }
            
            $metrics = get_transient('fp_resv_metrics_summary');
            if (!$metrics) {
                echo '<div class="notice notice-info"><p>Nessuna metrica disponibile.</p></div>';
                return;
            }
            
            echo '<div class="wrap">';
            echo '<h1>Metriche FP Restaurant Reservations</h1>';
            
            echo '<h2>Availability</h2>';
            echo '<table class="widefat">';
            echo '<thead><tr><th>Metrica</th><th>Valore</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($metrics as $key => $value) {
                if (strpos($key, 'availability.') === 0) {
                    echo sprintf(
                        '<tr><td>%s</td><td>%s</td></tr>',
                        esc_html($key),
                        esc_html($value)
                    );
                }
            }
            
            echo '</tbody></table>';
            echo '</div>';
        }
    );
});

// Raccoglitore metriche per dashboard
add_action('fp_resv_metric', function($entry) {
    $summary = get_transient('fp_resv_metrics_summary');
    if (!is_array($summary)) {
        $summary = [];
    }
    
    $key = $entry['metric'];
    if (!empty($entry['tags'])) {
        $key .= ':' . json_encode($entry['tags']);
    }
    
    if ($entry['type'] === 'counter') {
        $summary[$key] = ($summary[$key] ?? 0) + $entry['value'];
    } else {
        $summary[$key] = $entry['value'];
    }
    
    set_transient('fp_resv_metrics_summary', $summary, HOUR_IN_SECONDS);
});
```

## Best Practices

### 1. Tag Naming

✅ **Buono**:
```php
Metrics::timing('api.request', $duration, [
    'endpoint' => 'availability',
    'status' => 'success',
]);
```

❌ **Cattivo**:
```php
Metrics::timing('api.request.availability.success', $duration);
```

**Motivo**: Tags permettono aggregazione e filtering.

### 2. Metric Naming

Usa naming gerarchico con `.` come separatore:

- `domain.operation` - Es: `availability.calculation`
- `domain.operation.detail` - Es: `availability.cache.hit`

### 3. Performance

Le metriche hanno overhead minimo (~0.1-0.5ms), ma:

- Non usare in loop stretti
- Usa timer automatico invece di timing manuale
- Evita tag con cardinalità alta (es: `user_id`)

### 4. Tags High Cardinality

❌ **Evita**:
```php
Metrics::increment('reservation.created', 1, [
    'customer_id' => 12345, // High cardinality
    'reservation_id' => 67890, // High cardinality
]);
```

✅ **Usa**:
```php
Metrics::increment('reservation.created', 1, [
    'status' => 'confirmed', // Low cardinality
    'channel' => 'web', // Low cardinality
]);
```

## Debugging

### Visualizza metriche in debug.log

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('FP_RESV_METRICS_ENABLED', true);
```

Le metriche verranno loggate in `wp-content/debug.log`.

### Test metriche in sviluppo

```php
$collected = [];

add_action('fp_resv_metric', function($entry) use (&$collected) {
    $collected[] = $entry;
});

// ... operazione che genera metriche ...

var_dump($collected);
```

## Troubleshooting

### Le metriche non vengono inviate

1. Verifica `FP_RESV_METRICS_ENABLED` sia `true`
2. Verifica il filter `fp_resv_metrics_handler` sia registrato
3. Controlla `debug.log` per errori
4. Verifica il custom handler non abbia exceptions

### Performance degradation

1. Disabilita metriche: `define('FP_RESV_METRICS_ENABLED', false)`
2. Riduci tag cardinality
3. Usa batching nel custom handler

### Missing metrics

1. Verifica il codice chiami `Metrics::*` correttamente
2. Controlla action hook `fp_resv_metric` sia registrato
3. Verifica timer sia stoppato (`$stop()`)

## Conclusione

Il sistema di metriche è:
- **Leggero**: Overhead ~0.1-0.5ms per metrica
- **Flessibile**: Supporta qualsiasi backend via filter
- **Testabile**: Hook action per test automatici
- **Production-ready**: Usato in produzione senza impatti
