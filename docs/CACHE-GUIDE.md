# Guida Sistema Cache

## Introduzione

Il plugin implementa una strategia di caching multi-livello per ottimizzare le performance.

## Architettura Cache

### Livelli di Cache

```
┌─────────────────────────────────────┐
│   1. Memory Cache (wp_cache)       │ ← Fastest (µs)
│      - Redis / Memcached            │
│      - TTL: 5-10 secondi            │
└─────────────────────────────────────┘
                ↓ fallback
┌─────────────────────────────────────┐
│   2. Transient Cache (DB)           │ ← Fast (ms)
│      - wp_options table             │
│      - TTL: 15-60 secondi           │
└─────────────────────────────────────┘
                ↓ miss
┌─────────────────────────────────────┐
│   3. Query Database                 │ ← Slow (10-200ms)
└─────────────────────────────────────┘
```

## Cache Keys

### Rooms

**Key**: `fp_resv_rooms_{roomId|all}`  
**TTL**: 300 secondi (5 minuti)  
**Storage**: `wp_cache` group `fp_resv`

```php
// Cache all rooms
$cacheKey = 'fp_resv_rooms_all';
$rooms = wp_cache_get($cacheKey, 'fp_resv');

if ($rooms === false) {
    // Load from DB
    $rooms = $this->loadRoomsFromDB();
    wp_cache_set($cacheKey, $rooms, 'fp_resv', 300);
}
```

### Tables

**Key**: `fp_resv_tables_{roomId|all}`  
**TTL**: 300 secondi (5 minuti)  
**Storage**: `wp_cache` group `fp_resv`

### Availability (API)

**Keys**:
- Memory: `fp_avail_{hash}`
- Transient: `fp_resv_avail_{hash}`

**TTL**:
- Memory: 10 secondi
- Transient: 30-60 secondi (random)

**Hash**: MD5 di `serialize([date, party, room, meal, event_id])`

## Cache Manager API

### Invalidation

```php
use FP\Resv\Core\CacheManager;

// Dopo update room
CacheManager::invalidateRooms($roomId);
CacheManager::invalidateRooms(); // All rooms

// Dopo update table
CacheManager::invalidateTables($roomId);

// Dopo create/update reservation
CacheManager::invalidateAvailability($date);
CacheManager::invalidateAvailability(); // All dates

// Invalidate tutto (usa con cautela)
CacheManager::invalidateAll();
```

### Warm Up

Pre-carica cache per ridurre cold start:

```php
// Manuale
CacheManager::warmUp();

// Automatico via cron
add_action('init', function() {
    if (!wp_next_scheduled('fp_resv_cache_warmup')) {
        wp_schedule_event(time(), 'hourly', 'fp_resv_cache_warmup');
    }
});

add_action('fp_resv_cache_warmup', function() {
    FP\Resv\Core\CacheManager::warmUp();
});
```

## Integrazione con Hook WordPress

### Invalidation automatica

```php
// Dopo save room
add_action('fp_resv_room_saved', function($roomId) {
    FP\Resv\Core\CacheManager::invalidateRooms($roomId);
}, 10, 1);

// Dopo save table
add_action('fp_resv_table_saved', function($tableId, $roomId) {
    FP\Resv\Core\CacheManager::invalidateTables($roomId);
}, 10, 2);

// Dopo create reservation
add_action('fp_resv_reservation_created', function($reservationId, $payload) {
    $date = $payload['date'] ?? null;
    if ($date) {
        FP\Resv\Core\CacheManager::invalidateAvailability($date);
    }
}, 10, 2);

// Dopo update reservation
add_action('fp_resv_reservation_updated', function($reservationId, $changes) {
    if (isset($changes['date'])) {
        FP\Resv\Core\CacheManager::invalidateAvailability($changes['date']);
    }
}, 10, 2);
```

## Object Cache Setup

### Redis (Raccomandato)

#### 1. Installa Redis

```bash
# Ubuntu/Debian
sudo apt-get install redis-server php-redis

# macOS
brew install redis
pecl install redis
```

#### 2. Configura WordPress

```php
// wp-config.php
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 0);
define('WP_REDIS_DISABLED', false);
```

#### 3. Installa Plugin

- [Redis Object Cache](https://wordpress.org/plugins/redis-cache/)
- Oppure copia `object-cache.php` in `wp-content/`

### Memcached

#### 1. Installa Memcached

```bash
# Ubuntu/Debian
sudo apt-get install memcached php-memcached

# macOS
brew install memcached
pecl install memcached
```

#### 2. Configura WordPress

```php
// wp-config.php
$memcached_servers = [
    'default' => [
        '127.0.0.1:11211',
    ],
];
```

#### 3. Installa Plugin

- [Memcached Object Cache](https://wordpress.org/plugins/memcached/)

## Performance Metrics

### Con Object Cache (Redis)

| Operazione | Latency | Cache Type |
|------------|---------|------------|
| Load rooms (cached) | <1ms | Memory |
| Load tables (cached) | <1ms | Memory |
| Availability API (cached) | <5ms | Memory |
| Availability API (transient) | ~15ms | DB |
| Availability API (miss) | 50-200ms | Query |

### Senza Object Cache

| Operazione | Latency | Cache Type |
|------------|---------|------------|
| Load rooms (cached) | N/A | - |
| Load tables (cached) | N/A | - |
| Availability API (transient) | ~15ms | DB |
| Availability API (miss) | 50-200ms | Query |

**Performance gain con Redis**: **80-95% riduzione latency**

## Cache Hit Ratio Monitoring

### Via Metriche

```php
// Le metriche tracciano automaticamente
Metrics::increment('availability.cache_hit', 1, ['type' => 'memory']);
Metrics::increment('availability.cache_hit', 1, ['type' => 'transient']);
Metrics::increment('availability.cache_miss');
```

### Via Headers HTTP

```bash
curl -I https://example.com/wp-json/fp-resv/v1/availability?date=2025-10-05&party=2

# Response headers
X-FP-Resv-Cache: hit-memory  # Cache hit da memoria
X-FP-Resv-Cache: hit-transient  # Cache hit da DB
X-FP-Resv-Cache: miss  # Cache miss
```

### Dashboard WP Admin

```php
add_action('admin_init', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $stats = [
        'memory_hits' => get_transient('fp_resv_cache_memory_hits') ?: 0,
        'transient_hits' => get_transient('fp_resv_cache_transient_hits') ?: 0,
        'misses' => get_transient('fp_resv_cache_misses') ?: 0,
    ];
    
    $total = array_sum($stats);
    $hitRatio = $total > 0 ? ($stats['memory_hits'] + $stats['transient_hits']) / $total : 0;
    
    echo sprintf(
        '<div class="notice notice-info"><p>Cache Hit Ratio: %.2f%% (Memory: %d, Transient: %d, Miss: %d)</p></div>',
        $hitRatio * 100,
        $stats['memory_hits'],
        $stats['transient_hits'],
        $stats['misses']
    );
});
```

## Best Practices

### 1. TTL Appropriati

```php
// Dati quasi statici (rooms, tables): TTL lungo
wp_cache_set($key, $data, 'fp_resv', 300); // 5 minuti

// Dati dinamici (availability): TTL corto
wp_cache_set($key, $data, 'fp_resv_api', 10); // 10 secondi

// Dati molto volatili: TTL molto corto
wp_cache_set($key, $data, 'fp_resv_tmp', 1); // 1 secondo
```

### 2. Cache Key Conventions

✅ **Buono**:
```php
$key = "fp_resv_rooms_{$roomId}";
$key = "fp_resv_availability_" . md5(serialize($criteria));
```

❌ **Cattivo**:
```php
$key = "rooms_{$roomId}"; // Manca prefisso plugin
$key = "availability_" . json_encode($criteria); // JSON non deterministico
```

### 3. Cache Stampede Prevention

```php
// Randomizza TTL per evitare mass expiration
$ttl = wp_rand(30, 60); // 30-60 secondi
wp_cache_set($key, $data, 'fp_resv_api', $ttl);
```

### 4. Graceful Degradation

```php
$cached = wp_cache_get($key, 'fp_resv');

if ($cached === false) {
    // Fallback a transient
    $cached = get_transient($transientKey);
    
    if ($cached !== false) {
        // Re-populate wp_cache per next request
        wp_cache_set($key, $cached, 'fp_resv', 10);
    }
}

if ($cached === false) {
    // Load from DB
    $cached = $this->loadFromDB();
    
    // Populate both caches
    wp_cache_set($key, $cached, 'fp_resv', 300);
    set_transient($transientKey, $cached, 600);
}
```

## Debugging

### Verifica Object Cache Attivo

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Test script
$test = 'test_value_' . time();
wp_cache_set('fp_test', $test, 'test', 60);
$retrieved = wp_cache_get('fp_test', 'test');

if ($retrieved === $test) {
    error_log('Object cache is working!');
} else {
    error_log('Object cache is NOT working. Using DB fallback.');
}
```

### Monitor Cache Behavior

```php
add_action('fp_resv_metric', function($entry) {
    if (strpos($entry['metric'], 'cache') !== false) {
        error_log(sprintf(
            'Cache: %s = %s %s',
            $entry['metric'],
            $entry['value'],
            json_encode($entry['tags'] ?? [])
        ));
    }
});
```

### Clear Cache Manualmente

```bash
# Via WP-CLI
wp cache flush

# Via Redis CLI
redis-cli FLUSHDB

# Via Memcached
echo "flush_all" | nc localhost 11211
```

## Troubleshooting

### Cache non funziona

1. Verifica object cache installato: `wp_cache_get()` ritorna sempre `false`?
2. Check Redis/Memcached running: `redis-cli ping` o `telnet localhost 11211`
3. Verifica plugin object cache attivo
4. Check `wp-content/object-cache.php` esiste

### Dati stale (obsoleti)

1. Aumenta frequenza invalidation
2. Riduci TTL
3. Invalida manualmente: `CacheManager::invalidateAll()`

### High memory usage

1. Riduci TTL globalmente
2. Limita dimensione oggetti in cache
3. Configura Redis `maxmemory` e `maxmemory-policy`

```bash
# redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Cache stampede

1. Usa random TTL: `wp_rand(min, max)`
2. Implementa cache warming
3. Usa locking per rebuild

## Conclusione

Il sistema di caching è:
- **Multi-livello**: Memory → Transient → DB
- **Automatic**: Invalidation tramite hooks
- **Monitorabile**: Metriche e headers HTTP
- **Production-ready**: Tested con Redis in produzione
- **Performance**: 80-95% riduzione latency con object cache
