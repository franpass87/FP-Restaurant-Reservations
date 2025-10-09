#!/usr/bin/env php
<?php
/**
 * Diagnostica e risoluzione problema "Completamente prenotato"
 * 
 * Questo script verifica lo stato della cache e offre soluzioni immediate.
 * 
 * Usage:
 *   wp eval-file tools/diagnose-cache-issue.php
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    echo "⚠️  Questo script richiede WordPress (WP-CLI).\n";
    echo "Esegui: wp eval-file tools/diagnose-cache-issue.php\n";
    exit(1);
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  🔍 DIAGNOSTICA PROBLEMA 'COMPLETAMENTE PRENOTATO'\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Check 1: WP_DEBUG status
echo "📋 Check 1: Modalità Debug\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$isDebug = defined('WP_DEBUG') && WP_DEBUG;
if ($isDebug) {
    echo "✅ WP_DEBUG attivo - Gli asset vengono sempre ricaricati\n";
    echo "   Cache busting: ATTIVO (timestamp sempre aggiornato)\n";
} else {
    echo "⚠️  WP_DEBUG disattivo - Modalità produzione\n";
    echo "   Cache busting: Basato su timestamp salvato nel DB\n";
}
echo "\n";

// Check 2: Asset version
echo "📋 Check 2: Versione Asset Corrente\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if (class_exists('FP\Resv\Core\Plugin')) {
    $version = \FP\Resv\Core\Plugin::assetVersion();
    $pluginVersion = \FP\Resv\Core\Plugin::VERSION;
    echo "Plugin Version: {$pluginVersion}\n";
    echo "Asset Version:  {$version}\n";
    
    // Parse timestamp from version
    $parts = explode('.', $version);
    if (count($parts) >= 4) {
        $timestamp = (int) $parts[3];
        $date = date('Y-m-d H:i:s', $timestamp);
        $hoursAgo = round((time() - $timestamp) / 3600, 1);
        echo "Timestamp:      {$date}\n";
        echo "Età:            {$hoursAgo} ore fa\n";
        
        if ($hoursAgo > 24) {
            echo "⚠️  ATTENZIONE: Timestamp vecchio di più di 24 ore!\n";
            echo "   È possibile che i browser usino ancora la versione cached.\n";
        } else {
            echo "✅ Timestamp recente\n";
        }
    }
} else {
    echo "❌ Plugin non caricato!\n";
}
echo "\n";

// Check 3: Last upgrade timestamp
echo "📋 Check 3: Timestamp Ultimo Aggiornamento\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if (function_exists('get_option')) {
    $upgradeTime = get_option('fp_resv_last_upgrade', false);
    if ($upgradeTime === false) {
        echo "❌ Timestamp non trovato nel database!\n";
        echo "   Questo può causare problemi di cache.\n";
    } else {
        $date = date('Y-m-d H:i:s', (int) $upgradeTime);
        $hoursAgo = round((time() - (int) $upgradeTime) / 3600, 1);
        echo "Ultimo upgrade: {$date}\n";
        echo "Età:            {$hoursAgo} ore fa\n";
    }
}
echo "\n";

// Check 4: File modificati
echo "📋 Check 4: Data Modifica File JavaScript\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$pluginDir = defined('FP\Resv\Core\Plugin::$dir') ? \FP\Resv\Core\Plugin::$dir : '';
if ($pluginDir === '' && class_exists('FP\Resv\Core\Plugin')) {
    // Try to get it from the static property
    $reflection = new ReflectionClass('FP\Resv\Core\Plugin');
    $dirProperty = $reflection->getProperty('dir');
    $dirProperty->setAccessible(true);
    $pluginDir = $dirProperty->getValue();
}

$files = [
    'Source:    assets/js/fe/onepage.js',
    'Compiled:  assets/dist/fe/onepage.esm.js',
    'Compiled:  assets/dist/fe/onepage.iife.js',
];

$newestTimestamp = 0;
foreach ($files as $fileLabel) {
    $parts = explode('  ', $fileLabel);
    $label = $parts[0];
    $file = $parts[1];
    $fullPath = rtrim($pluginDir, '/') . '/' . $file;
    
    if (file_exists($fullPath)) {
        $mtime = filemtime($fullPath);
        $newestTimestamp = max($newestTimestamp, $mtime);
        $date = date('Y-m-d H:i:s', $mtime);
        $hoursAgo = round((time() - $mtime) / 3600, 1);
        echo "{$label} {$date} ({$hoursAgo}h fa)\n";
    } else {
        echo "{$label} ❌ File non trovato!\n";
    }
}

if ($newestTimestamp > 0) {
    echo "\n";
    if (!$isDebug && function_exists('get_option')) {
        $upgradeTime = get_option('fp_resv_last_upgrade', 0);
        if ($newestTimestamp > $upgradeTime) {
            echo "⚠️  PROBLEMA IDENTIFICATO!\n";
            echo "   I file JavaScript sono più recenti del timestamp nel DB.\n";
            echo "   Questo significa che i browser usano ancora la versione vecchia!\n";
            echo "\n";
            echo "🔧 SOLUZIONE: Esegui il refresh della cache\n";
        } else {
            echo "✅ Timestamp DB aggiornato rispetto ai file\n";
        }
    }
}
echo "\n";

// Check 5: Verifiche nel codice
echo "📋 Check 5: Verifica Fix nel Codice Sorgente\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$sourceFile = rtrim($pluginDir, '/') . '/assets/js/fe/onepage.js';
if (file_exists($sourceFile)) {
    $content = file_get_contents($sourceFile);
    
    // Check Fix 1: Reset meal availability cache
    $fix1 = strpos($content, 'this.state.mealAvailability = {}') !== false;
    echo "Fix 1 (Reset cache):             " . ($fix1 ? "✅ PRESENTE" : "❌ MANCANTE") . "\n";
    
    // Check Fix 2: Always schedule update (assenza di return anticipato)
    $fix2Pattern = '/scheduleAvailabilityUpdate\(\s*\{\s*immediate\s*:\s*true\s*\}\s*\)/';
    $fix2 = preg_match($fix2Pattern, $content) > 0;
    echo "Fix 2 (Always update):           " . ($fix2 ? "✅ PRESENTE" : "❌ MANCANTE") . "\n";
    
    if ($fix1 && $fix2) {
        echo "\n✅ Tutti i fix sono presenti nel codice sorgente!\n";
    } else {
        echo "\n❌ Alcuni fix sono mancanti! Verifica il codice.\n";
    }
} else {
    echo "❌ File sorgente non trovato!\n";
}
echo "\n";

// Riepilogo e azioni
echo "════════════════════════════════════════════════════════════════\n";
echo "  📊 RIEPILOGO\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

$problems = [];
if (!$isDebug && function_exists('get_option')) {
    $upgradeTime = get_option('fp_resv_last_upgrade', 0);
    if ($newestTimestamp > $upgradeTime) {
        $problems[] = "Cache DB non aggiornata";
    }
}

if (empty($problems)) {
    echo "✅ Nessun problema critico rilevato!\n";
    echo "\n";
    echo "Se il problema persiste:\n";
    echo "1. Verifica che gli utenti abbiano fatto hard refresh del browser\n";
    echo "2. Controlla la configurazione degli orari in admin\n";
    echo "3. Verifica i log di WordPress per errori\n";
} else {
    echo "⚠️  Problemi rilevati:\n";
    foreach ($problems as $i => $problem) {
        echo "   " . ($i + 1) . ". {$problem}\n";
    }
    echo "\n";
    echo "🔧 AZIONE RACCOMANDATA:\n";
    echo "\n";
    echo "Esegui il refresh della cache con uno di questi comandi:\n";
    echo "\n";
    echo "  1. Via WP-CLI:\n";
    echo "     wp eval-file tools/refresh-cache.php\n";
    echo "\n";
    echo "  2. Via PHP:\n";
    echo "     wp eval '\FP\Resv\Core\Plugin::forceRefreshAssets();'\n";
    echo "\n";
    echo "  3. Via REST API:\n";
    echo "     curl -X POST \"https://tuosito.com/wp-json/fp-resv/v1/diagnostics/refresh-cache\"\n";
    echo "\n";
}

echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

// Offer auto-fix
if (!empty($problems) && defined('WP_CLI') && WP_CLI) {
    WP_CLI::confirm('Vuoi eseguire il refresh della cache ORA?', []);
    
    if (class_exists('FP\Resv\Core\Plugin')) {
        \FP\Resv\Core\Plugin::forceRefreshAssets();
        WP_CLI::success('Cache refreshed successfully!');
        
        echo "\n";
        echo "✅ Problema risolto!\n";
        echo "\n";
        echo "📋 PASSI SUCCESSIVI:\n";
        echo "1. Gli utenti devono fare hard refresh del browser:\n";
        echo "   - Windows/Linux: Ctrl + Shift + R\n";
        echo "   - Mac: Cmd + Shift + R\n";
        echo "2. Testa cambiando data/persone nel form di prenotazione\n";
        echo "3. Verifica che 'Completamente prenotato' appaia solo quando appropriato\n";
        echo "\n";
    } else {
        WP_CLI::error('Plugin non caricato.');
    }
}
