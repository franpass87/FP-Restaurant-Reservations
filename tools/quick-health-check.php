<?php
/**
 * Quick Health Check - Verifica sintassi senza WordPress
 * 
 * Questo script verifica la sintassi dei file modificati
 * senza richiedere il caricamento completo di WordPress.
 */

declare(strict_types=1);

echo "=== QUICK HEALTH CHECK - FP RESTAURANT RESERVATIONS ===\n\n";

$baseDir = dirname(__DIR__);

// 1. Verifica versione plugin allineata
echo "1️⃣ VERSIONE PLUGIN\n";
$mainFile = $baseDir . '/fp-restaurant-reservations.php';
$pluginFile = $baseDir . '/src/Core/Plugin.php';

if (file_exists($mainFile) && file_exists($pluginFile)) {
    $mainContent = file_get_contents($mainFile);
    $pluginContent = file_get_contents($pluginFile);
    
    if (preg_match('/ \* Version: (.+)/', $mainContent, $mainMatches)) {
        $mainVersion = trim($mainMatches[1]);
        echo "   File principale: $mainVersion\n";
    }
    
    if (preg_match('/const VERSION = \'(.+)\';/', $pluginContent, $pluginMatches)) {
        $pluginVersion = trim($pluginMatches[1]);
        echo "   Plugin.php: $pluginVersion\n";
    }
    
    if (isset($mainVersion) && isset($pluginVersion) && $mainVersion === $pluginVersion) {
        echo "   ✅ Versioni allineate\n";
    } else {
        echo "   ⚠️  Versioni NON allineate!\n";
    }
} else {
    echo "   ❌ File non trovati\n";
}
echo "\n";

// 2. Verifica sintassi PHP
echo "2️⃣ SINTASSI PHP\n";
$filesToCheck = [
    'fp-restaurant-reservations.php',
    'src/Core/Plugin.php',
    'src/Domain/Reservations/AdminREST.php',
    'src/Domain/Reservations/REST.php',
    'src/Domain/Reservations/Service.php',
    'src/Domain/Reservations/Repository.php',
    'src/Frontend/Shortcodes.php',
    'src/Domain/Reservations/Availability.php',
];

$allOk = true;
foreach ($filesToCheck as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $output = [];
        $return = 0;
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $return);
        
        if ($return === 0) {
            echo "   ✅ " . basename($file) . "\n";
        } else {
            echo "   ❌ " . basename($file) . ": " . implode("\n", $output) . "\n";
            $allOk = false;
        }
    } else {
        echo "   ⚠️  $file non trovato\n";
    }
}
echo "\n";

// 3. Verifica fix timezone applicati
echo "3️⃣ FIX TIMEZONE\n";
$fixedFiles = [
    'src/Domain/Reservations/AdminREST.php' => ['gmdate' => 0, 'wp_date|current_time' => '>=3'],
    'src/Frontend/Shortcodes.php' => ['gmdate' => 0, 'wp_date|current_time' => '>=3'],
    'src/Domain/Reservations/REST.php' => ['date\(' => 0, 'wp_date|current_time' => '>=6'],
    'src/Domain/Reservations/Service.php' => ['gmdate' => 0, 'wp_date|current_time' => '>=2'],
    'src/Domain/Reservations/Repository.php' => ['gmdate' => 0, 'wp_date' => '>=1'],
];

foreach ($fixedFiles as $file => $expectations) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $passed = true;
        
        foreach ($expectations as $pattern => $expected) {
            if (str_contains($pattern, '|')) {
                // Pattern multipli (OR)
                $patterns = explode('|', $pattern);
                $count = 0;
                foreach ($patterns as $p) {
                    $count += substr_count($content, $p . '(');
                }
            } else {
                $count = substr_count($content, $pattern . '(');
            }
            
            if (is_string($expected) && str_starts_with($expected, '>=')) {
                $min = (int) substr($expected, 2);
                if ($count < $min) {
                    $passed = false;
                }
            } else {
                $expectedCount = is_int($expected) ? $expected : (int) $expected;
                if ($count != $expectedCount) {
                    $passed = false;
                }
            }
        }
        
        if ($passed) {
            echo "   ✅ " . basename($file) . "\n";
        } else {
            echo "   ⚠️  " . basename($file) . " (verificare manualmente)\n";
        }
    }
}
echo "\n";

// 4. Verifica Composer
echo "4️⃣ COMPOSER\n";
$composerJson = $baseDir . '/composer.json';
$vendorAutoload = $baseDir . '/vendor/autoload.php';

if (file_exists($composerJson)) {
    $json = json_decode(file_get_contents($composerJson), true);
    if ($json && isset($json['autoload']['psr-4'])) {
        echo "   ✅ composer.json valido\n";
        echo "   PSR-4: " . implode(', ', array_keys($json['autoload']['psr-4'])) . "\n";
    } else {
        echo "   ⚠️  composer.json non valido\n";
        $allOk = false;
    }
} else {
    echo "   ❌ composer.json non trovato\n";
    $allOk = false;
}

if (file_exists($vendorAutoload)) {
    echo "   ✅ vendor/autoload.php presente\n";
} else {
    echo "   ❌ vendor/autoload.php mancante (esegui: composer install)\n";
    $allOk = false;
}
echo "\n";

// 5. Verifica struttura directory
echo "5️⃣ STRUTTURA DIRECTORY\n";
$requiredDirs = [
    'src/Core',
    'src/Domain/Reservations',
    'src/Frontend',
    'assets/css',
    'assets/js/fe',
    'assets/js/admin',
    'templates/frontend',
    'templates/emails',
];

foreach ($requiredDirs as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    if (is_dir($fullPath)) {
        $fileCount = count(glob($fullPath . '/*.php')) + count(glob($fullPath . '/*.js'));
        echo "   ✅ $dir ($fileCount file)\n";
    } else {
        echo "   ❌ $dir non trovata\n";
        $allOk = false;
    }
}
echo "\n";

// 6. Riepilogo
echo "=" . str_repeat("=", 50) . "\n";
if ($allOk) {
    echo "✅ TUTTI I CHECK SUPERATI!\n";
    echo "\nIl plugin sembra essere in buone condizioni.\n";
    echo "Per un test completo, carica il plugin in WordPress.\n";
} else {
    echo "⚠️  ALCUNI CHECK HANNO FALLITO\n";
    echo "\nControlla gli errori sopra riportati.\n";
}
echo "=" . str_repeat("=", 50) . "\n";

