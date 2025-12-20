<?php
/**
 * Plugin Name: FP Restaurant Reservations
 * Plugin URI: https://francescopasseri.com/projects/fp-restaurant-reservations
 * Description: Prenotazioni ristorante con eventi, calendario drag&drop, Brevo + Google Calendar, tracking GA4/Ads/Meta/Clarity e stile personalizzabile.
 * Version: 0.9.0-rc10.3
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-restaurant-reservations
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$minPhp = '8.1';
if (version_compare(PHP_VERSION, $minPhp, '<')) {
    $message = sprintf(
        /* translators: 1: Minimum supported PHP version, 2: Detected PHP version. */
        'FP Restaurant Reservations richiede PHP %1$s o superiore. Questo sito esegue PHP %2$s.',
        $minPhp,
        PHP_VERSION
    );

    if (function_exists('add_action')) {
        add_action('admin_notices', function () use ($message) {
            if (!function_exists('esc_html')) {
                echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
                return;
            }

            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
    }

    if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }

    if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
        \WP_CLI::warning($message);
    }

    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[FP Restaurant Reservations] ' . $message);
    }

    return;
}

// Load autoloader - REQUIRED for plugin to work
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    // Tentativo di installazione automatica delle dipendenze
    $installAttempted = false;
    $installSuccess = false;
    
    // Verifica se composer.json esiste
    $composerJson = __DIR__ . '/composer.json';
    if (is_readable($composerJson)) {
        $installAttempted = true;
        $installSuccess = fp_resv_install_composer_dependencies(__DIR__);
    }
    
    // Se l'installazione automatica è fallita o non è stata tentata, mostra errore
    if (!$installSuccess) {
        $message = sprintf(
            /* translators: %s: Path to autoload.php file. */
            'FP Restaurant Reservations richiede che le dipendenze Composer siano installate. File mancante: %s.',
            $autoload
        );

        if (function_exists('add_action')) {
            add_action('admin_notices', function () use ($message, $autoload, $installAttempted) {
                $notice = '<div class="notice notice-error"><p><strong>FP Restaurant Reservations - Errore Critico</strong></p>';
                $notice .= '<p>' . esc_html($message) . '</p>';
                $notice .= '<p><code>' . esc_html($autoload) . '</code></p>';
                
                if ($installAttempted) {
                    $notice .= '<p><strong>⚠️ Installazione automatica fallita.</strong></p>';
                }
                
                $notice .= '<p><strong>Soluzione:</strong> Apri un terminale nella directory del plugin e esegui: <code>composer install</code></p>';
                $notice .= '<p>Assicurati che Composer sia installato sul server e che la directory del plugin abbia permessi di scrittura.</p>';
                $notice .= '</div>';
                echo $notice;
            });
        }

        if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::error($message);
        }

        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[FP Restaurant Reservations] ' . $message);
        }

        return;
    }
    
    // Se l'installazione è riuscita, verifica di nuovo che autoload.php esista
    if (!is_readable($autoload)) {
        if (function_exists('add_action')) {
            add_action('admin_notices', function () use ($autoload) {
                $notice = '<div class="notice notice-warning"><p><strong>FP Restaurant Reservations</strong></p>';
                $notice .= '<p>Le dipendenze sono state installate, ma il file autoload.php non è ancora disponibile. Ricarica la pagina.</p>';
                $notice .= '</div>';
                echo $notice;
            });
        }
        return;
    }
}

require $autoload;

/**
 * Installa automaticamente le dipendenze Composer
 * 
 * @param string $pluginDir Directory del plugin
 * @return bool True se l'installazione è riuscita, false altrimenti
 */
function fp_resv_install_composer_dependencies(string $pluginDir): bool
{
    // Evita esecuzioni multiple simultanee usando un lock file
    $lockFile = $pluginDir . '/.composer-install.lock';
    if (file_exists($lockFile)) {
        // Verifica se il lock è vecchio (più di 10 minuti = installazione bloccata)
        $lockTime = filemtime($lockFile);
        if (time() - $lockTime > 600) {
            @unlink($lockFile);
        } else {
            // Installazione già in corso
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[FP Restaurant Reservations] Installazione Composer già in corso');
            }
            return false;
        }
    }
    
    // Crea lock file
    @file_put_contents($lockFile, time());
    register_shutdown_function(function () use ($lockFile) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    });
    
    // Verifica permessi di scrittura
    if (!is_writable($pluginDir)) {
        @unlink($lockFile);
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[FP Restaurant Reservations] Directory plugin non scrivibile: ' . $pluginDir);
        }
        return false;
    }
    
    // Cerca Composer (prima composer.phar locale, poi comando globale)
    $composer = null;
    
    // 1. Prova composer.phar nella directory del plugin
    $localComposer = $pluginDir . '/composer.phar';
    if (file_exists($localComposer) && is_executable($localComposer)) {
        $composer = escapeshellarg($localComposer);
    }
    
    // 2. Prova composer.phar nella directory parent
    if ($composer === null) {
        $parentComposer = dirname($pluginDir) . '/composer.phar';
        if (file_exists($parentComposer) && is_executable($parentComposer)) {
            $composer = escapeshellarg($parentComposer);
        }
    }
    
    // 3. Prova comando globale 'composer'
    if ($composer === null) {
        // Su Windows usa 'where', su Unix usa 'which'
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $whichComposer = @shell_exec('where composer 2>nul');
        } else {
            $whichComposer = @shell_exec('which composer 2>/dev/null');
        }
        if ($whichComposer && trim($whichComposer)) {
            $composer = 'composer';
        }
    }
    
    // 4. Prova 'php composer.phar' se composer.phar esiste ma non è eseguibile
    if ($composer === null) {
        $localComposer = $pluginDir . '/composer.phar';
        if (file_exists($localComposer)) {
            $composer = 'php ' . escapeshellarg($localComposer);
        }
    }
    
    if ($composer === null) {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[FP Restaurant Reservations] Composer non trovato. Installa Composer o aggiungi composer.phar nella directory del plugin.');
        }
        return false;
    }
    
    // Esegui composer install
    // Su Windows usa &&, su Unix usa && (funziona su entrambi)
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWindows) {
        // Su Windows, cambia directory usando chdir in PHP invece di cd nel comando
        $command = sprintf(
            '%s install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1',
            $composer
        );
    } else {
        $command = sprintf(
            'cd %s && %s install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1',
            escapeshellarg($pluginDir),
            $composer
        );
    }
    
    $returnVar = -1; // Inizializza a -1 per distinguere "non eseguito" da "eseguito con successo"
    $output = '';
    $executed = false;
    
    // Prova prima con proc_open (più sicuro)
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', ini_get('disable_functions')), true)) {
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        
        $process = @proc_open($command, $descriptorspec, $pipes, $pluginDir);
        
        if (is_resource($process)) {
            $executed = true;
            // Imposta timeout
            if (isset($pipes[1])) {
                stream_set_timeout($pipes[1], 300);
            }
            if (isset($pipes[2])) {
                stream_set_timeout($pipes[2], 300);
            }
            
            // Leggi output
            $stdout = '';
            $stderr = '';
            if (isset($pipes[1])) {
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
            }
            if (isset($pipes[2])) {
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
            }
            if (isset($pipes[0])) {
                fclose($pipes[0]);
            }
            
            $output = $stdout . ($stderr ? "\n" . $stderr : '');
            
            // Ottieni status
            $returnVar = proc_close($process);
            
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[FP Restaurant Reservations] Composer install output: ' . $output);
            }
        }
    }
    
    // Fallback: usa exec o shell_exec se proc_open non è disponibile o non ha funzionato
    if (!$executed) {
        // Cambia directory per exec/shell_exec (soprattutto importante su Windows)
        $oldCwd = getcwd();
        @chdir($pluginDir);
        
        if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')), true)) {
            $executed = true;
            @exec($command, $outputLines, $returnVar);
            $output = implode("\n", $outputLines);
        } elseif (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')), true)) {
            $executed = true;
            $output = @shell_exec($command);
            $returnVar = ($output !== null && $output !== '') ? 0 : 1;
        } else {
            // Ripristina directory prima di uscire
            @chdir($oldCwd);
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[FP Restaurant Reservations] Nessuna funzione di esecuzione disponibile (proc_open, exec, shell_exec sono disabilitate)');
            }
            // Rimuovi lock file prima di uscire
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }
            return false;
        }
        
        // Ripristina directory originale
        @chdir($oldCwd);
        
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[FP Restaurant Reservations] Composer install output (fallback): ' . $output);
        }
    }
    
    // Se non è stato eseguito nessun comando, fallisci
    if (!$executed) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        return false;
    }
    
    // Rimuovi lock file
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    
    // Verifica che autoload.php sia stato creato
    $autoloadPath = $pluginDir . '/vendor/autoload.php';
    $success = ($returnVar === 0 && is_readable($autoloadPath));
    
    if ($success) {
        if (function_exists('add_action')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo '<strong>FP Restaurant Reservations:</strong> ';
                echo esc_html__('Dipendenze Composer installate con successo!', 'fp-restaurant-reservations');
                echo '</p></div>';
            });
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[FP Restaurant Reservations] Composer install fallito. Return code: ' . $returnVar);
        }
    }
    
    return $success;
}

// Inizializza sistema di auto-aggiornamento da GitHub
if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/franpass87/FP-Restaurant-Reservations/',
        __FILE__,
        'fp-restaurant-reservations'
    );
    
    // Usa le GitHub Releases per gli aggiornamenti
    $updateChecker->getVcsApi()->enableReleaseAssets();
}

// Bootstrap plugin using new architecture
// Keep BootstrapGuard for error handling during transition
require_once __DIR__ . '/src/Core/BootstrapGuard.php';

$pluginFile = __FILE__;

FP\Resv\Core\BootstrapGuard::run($pluginFile, static function () use ($pluginFile): void {
    // Use new Bootstrap architecture
    require_once __DIR__ . '/src/Kernel/Bootstrap.php';
    
    $boot = static function () use ($pluginFile): void {
        FP\Resv\Kernel\Bootstrap::boot($pluginFile);
    };

    // Call on plugins_loaded instead of wp_loaded to ensure compatibility with legacy system
    // This ensures ServiceRegistry and AdminPages are registered at the right time
    if (\did_action('plugins_loaded')) {
        $boot();
    } else {
        \add_action('plugins_loaded', $boot, 20); // Priority 20 to run after most plugins
    }
});

// Register activation/deactivation hooks
register_activation_hook(__FILE__, static function () use ($pluginFile): void {
    require_once __DIR__ . '/src/Kernel/Lifecycle.php';
    FP\Resv\Kernel\Lifecycle::activate($pluginFile);
});

register_deactivation_hook(__FILE__, static function (): void {
    require_once __DIR__ . '/src/Kernel/Lifecycle.php';
    FP\Resv\Kernel\Lifecycle::deactivate();
});
