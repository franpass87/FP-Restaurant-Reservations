# Guida Migrazione alle Nuove Funzionalità

## Introduzione

Questa guida spiega come migrare gradualmente alle nuove funzionalità introdotte senza breaking changes.

## Timeline Migrazione Suggerita

```
┌──────────────────────────────────┐
│ Fase 1: Setup Object Cache      │  Settimana 1
│ - Installa Redis/Memcached       │
│ - Test su staging                │
└──────────────────────────────────┘
            ↓
┌──────────────────────────────────┐
│ Fase 2: Abilita Metriche         │  Settimana 2
│ - Setup monitoring handler       │
│ - Monitor baseline performance   │
└──────────────────────────────────┘
            ↓
┌──────────────────────────────────┐
│ Fase 3: Async Email (Opzionale) │  Settimana 3
│ - Install Action Scheduler       │
│ - Test email delivery            │
└──────────────────────────────────┘
            ↓
┌──────────────────────────────────┐
│ Fase 4: Monitoring & Tuning      │  Ongoing
│ - Analizza metriche              │
│ - Ottimizza TTL cache            │
│ - Tune rate limits               │
└──────────────────────────────────┘
```

## Fase 1: Object Cache Setup (Settimana 1)

### Prerequisiti

- Accesso server con permessi root/sudo
- Backup completo database e file
- Ambiente staging per test

### Step 1.1: Installazione Redis (Raccomandato)

#### Ubuntu/Debian

```bash
# Installa Redis
sudo apt-get update
sudo apt-get install redis-server

# Installa PHP Redis extension
sudo apt-get install php-redis

# Verifica installazione
redis-cli ping  # Deve rispondere: PONG
```

#### macOS (Development)

```bash
# Via Homebrew
brew install redis

# Avvia Redis
brew services start redis

# Installa PHP extension
pecl install redis
```

### Step 1.2: Configurazione WordPress

```php
// wp-config.php
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 0);  // Use database 0
define('WP_REDIS_DISABLED', false);

// Opzionale: password
// define('WP_REDIS_PASSWORD', 'your-password');

// Opzionale: timeout
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
```

### Step 1.3: Installa Plugin Object Cache

Opzione A: **Redis Object Cache** (Raccomandato)

```bash
# Via WP-CLI
wp plugin install redis-cache --activate

# Abilita object cache
wp redis enable

# Verifica status
wp redis status
```

Opzione B: **Copia manuale**

```bash
# Scarica object-cache.php
wget https://raw.githubusercontent.com/rhubarbgroup/redis-cache/master/includes/object-cache.php

# Copia in wp-content
cp object-cache.php wp-content/object-cache.php
```

### Step 1.4: Verifica Funzionamento

```php
// Aggiungi in functions.php temporaneamente
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $test = 'test_' . time();
    wp_cache_set('test_key', $test, 'test', 60);
    $retrieved = wp_cache_get('test_key', 'test');
    
    $status = $retrieved === $test ? 'SUCCESS' : 'FAILED';
    $class = $retrieved === $test ? 'success' : 'error';
    
    echo sprintf(
        '<div class="notice notice-%s"><p>Object Cache: <strong>%s</strong></p></div>',
        $class,
        $status
    );
});
```

### Step 1.5: Warm Up Cache

```bash
# Via WP-CLI
wp eval 'FP\Resv\Core\CacheManager::warmUp();'
```

### Step 1.6: Monitoring

```bash
# Monitor Redis
redis-cli INFO stats

# Keys attuali
redis-cli KEYS 'wp:*'

# Memory usage
redis-cli INFO memory
```

## Fase 2: Metriche Setup (Settimana 2)

### Step 2.1: Scegli Sistema Monitoring

Opzioni:
- **Datadog** - Enterprise, full featured
- **New Relic** - APM completo
- **CloudWatch** - Se su AWS
- **Custom Log** - Per budget limitati

### Step 2.2: Setup Handler (Esempio Datadog)

```php
// wp-content/mu-plugins/metrics-handler.php
add_filter('fp_resv_metrics_handler', function() {
    // Verifica Datadog disponibile
    if (!class_exists('DataDogStatsD')) {
        return null;
    }
    
    $statsd = new DataDogStatsD([
        'host' => 'localhost',
        'port' => 8125,
        'datadog_host' => 'https://app.datadoghq.com',
        'api_key' => getenv('DATADOG_API_KEY'),
    ]);
    
    return function($entry) use ($statsd) {
        $tags = [];
        foreach ($entry['tags'] ?? [] as $key => $value) {
            $tags[] = "{$key}:{$value}";
        }
        
        try {
            switch ($entry['type']) {
                case 'timing':
                    $statsd->timing($entry['metric'], $entry['value'], 1.0, $tags);
                    break;
                case 'counter':
                    $statsd->increment($entry['metric'], 1.0, $tags, (int) $entry['value']);
                    break;
                case 'gauge':
                    $statsd->gauge($entry['metric'], $entry['value'], 1.0, $tags);
                    break;
            }
        } catch (Exception $e) {
            error_log("Metrics error: " . $e->getMessage());
        }
    };
});

// Abilita metriche
define('FP_RESV_METRICS_ENABLED', true);
```

### Step 2.3: Verifica Metriche

```php
// Test temporaneo in functions.php
add_action('init', function() {
    if (isset($_GET['test_metrics']) && current_user_can('manage_options')) {
        FP\Resv\Core\Metrics::increment('test.metric', 1, ['test' => 'true']);
        FP\Resv\Core\Metrics::timing('test.timing', 123.45);
        FP\Resv\Core\Metrics::gauge('test.gauge', 42);
        
        wp_die('Metrics sent. Check your dashboard.');
    }
});

// Test: https://yoursite.com/?test_metrics=1
```

### Step 2.4: Dashboard Setup

Crea dashboard con:
- `availability.calculation` - Latency P50, P95, P99
- `availability.cache_hit` vs `cache_miss` - Hit ratio %
- `reservation.created` - Count per hour/day
- Alarms per:
  - Latency > 500ms
  - Cache hit ratio < 50%
  - Rate limited > 100/hour

## Fase 3: Async Email (Opzionale, Settimana 3)

### Step 3.1: Verifica Action Scheduler

```bash
# Verifica WooCommerce o altro plugin con Action Scheduler
wp plugin list | grep -i woocommerce

# Oppure installa standalone
wp plugin install action-scheduler --activate
```

### Step 3.2: Test Async Email

```php
// Test manuale
add_action('init', function() {
    if (isset($_GET['test_async_email']) && current_user_can('manage_options')) {
        $asyncMailer = FP\Resv\Core\ServiceContainer::getInstance()
            ->get('async.mailer');
        
        $asyncMailer->queueCustomerEmail([
            'to' => 'test@example.com',
            'subject' => 'Test Email Async',
            'message' => 'Questa è una email di test',
            'headers' => [],
            'attachments' => [],
            'meta' => ['test' => true],
        ]);
        
        wp_die('Email queued. Check Action Scheduler.');
    }
});

// Test: https://yoursite.com/?test_async_email=1
```

### Step 3.3: Monitor Queue

```bash
# Via WP-CLI
wp action-scheduler list --status=pending --per-page=10

# Check failed jobs
wp action-scheduler list --status=failed
```

### Step 3.4: Tune Concurrency (Opzionale)

```php
// wp-config.php

// Aumenta concurrent jobs (default: 5)
define('ACTION_SCHEDULER_CONCURRENT_BATCHES', 10);

// Queue batch size (default: 25)
define('ACTION_SCHEDULER_BATCH_SIZE', 50);
```

## Fase 4: Monitoring & Tuning (Ongoing)

### KPI da Monitorare

| Metrica | Target | Alert |
|---------|--------|-------|
| Availability API latency (P95) | <50ms | >200ms |
| Cache hit ratio | >80% | <50% |
| Reservation creation time (P95) | <1s | >3s |
| Email delivery rate | >99% | <95% |
| Rate limited requests | <1% | >5% |

### Tuning Cache TTL

```php
// Inizia con valori conservativi
// wp-content/mu-plugins/cache-tuning.php

add_filter('fp_resv_cache_ttl_rooms', function($ttl) {
    return 600; // 10 minuti invece di 5
});

add_filter('fp_resv_cache_ttl_tables', function($ttl) {
    return 600; // 10 minuti invece di 5
});

add_filter('fp_resv_cache_ttl_availability', function($ttl) {
    return 30; // 30 secondi invece di 10
});
```

### Tune Rate Limits

```php
// Personalizza per il tuo traffico
add_filter('fp_resv_rate_limit_availability', function($config) {
    return [
        'limit' => 60,  // Richieste
        'seconds' => 60, // Tempo
    ];
}, 10, 1);
```

### Ottimizzazione Redis

```ini
# redis.conf

# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence (opzionale)
save 900 1
save 300 10
save 60 10000

# Networking
timeout 0
tcp-keepalive 300

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log
```

## Rollback Plan

### Se Cache Causa Problemi

```bash
# Disabilita object cache
wp redis disable

# Oppure rinomina file
mv wp-content/object-cache.php wp-content/object-cache.php.bak

# Flush cache
wp cache flush
```

```php
// Oppure via wp-config.php
define('WP_REDIS_DISABLED', true);
```

### Se Metriche Causano Overhead

```php
// wp-config.php
define('FP_RESV_METRICS_ENABLED', false);
```

### Se Async Email Ha Problemi

```php
// wp-config.php
define('FP_RESV_DISABLE_ASYNC_EMAIL', true);

// Oppure disabilita Action Scheduler
wp plugin deactivate action-scheduler
```

## Checklist Post-Migrazione

- [ ] Object cache funzionante (verificato con `wp redis status`)
- [ ] Cache hit ratio >70% (monitorato)
- [ ] Metriche visibili in dashboard
- [ ] Nessun errore in `debug.log`
- [ ] Email async deliverate (check Action Scheduler)
- [ ] Performance baseline registrato
- [ ] Alert configurati
- [ ] Team formato su nuovi tool

## Troubleshooting Comune

### Cache non funziona

```bash
# Check Redis running
systemctl status redis
redis-cli ping

# Check PHP extension
php -m | grep redis

# Check WordPress
wp redis status

# Check permissions
ls -la wp-content/object-cache.php
```

### Metriche non appaiono

```bash
# Check constant
wp eval 'var_dump(defined("FP_RESV_METRICS_ENABLED"));'

# Check handler
wp eval 'var_dump(has_filter("fp_resv_metrics_handler"));'

# Test manuale
wp eval 'FP\Resv\Core\Metrics::increment("test.metric");'
```

### Email non inviate

```bash
# Check Action Scheduler
wp action-scheduler list --hook=fp_resv_send_customer_email

# Check log
tail -f wp-content/debug.log | grep -i 'mail\|email'

# Force process
wp action-scheduler run
```

## Supporto

Per assistenza:
1. Check `debug.log` per errori
2. Verifica metriche in dashboard
3. Review documentazione specifica:
   - [CACHE-GUIDE.md](./CACHE-GUIDE.md)
   - [METRICS-GUIDE.md](./METRICS-GUIDE.md)
   - [EXAMPLES.md](./EXAMPLES.md)

## Conclusione

La migrazione è:
- **Incrementale**: Fase per fase
- **Sicura**: Rollback sempre disponibile
- **Monitorata**: Metriche ad ogni step
- **Documentata**: Guide dettagliate

**Tempo stimato totale**: 3-4 settimane (1 settimana per fase)  
**Effort**: 1-2 giorni per fase (setup + test + monitoring)
