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

/**
 * Scarica composer.phar automaticamente
 * 
 * @param string $targetPath Path dove salvare composer.phar
 * @return bool True se il download è riuscito, false altrimenti
 */
function fp_resv_download_composer_phar(string $targetPath): bool
{
    $composerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
    
    // Prova prima con cURL (più affidabile)
    if (function_exists('curl_init')) {
        $ch = curl_init($composerUrl);
        if ($ch === false) {
            return false;
        }
        
        $fp = @fopen($targetPath, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return false;
        }
        
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'FP-Restaurant-Reservations-Plugin/1.0');
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        fclose($fp);
        
        if ($result === false || $httpCode !== 200) {
            @unlink($targetPath);
            return false;
        }
        
        if (filesize($targetPath) < 1024 * 1024) {
            @unlink($targetPath);
            return false;
        }
        
        return true;
    }
    
    // Fallback: usa file_get_contents
    if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'user_agent' => 'FP-Restaurant-Reservations-Plugin/1.0',
                'follow_location' => true,
            ],
        ]);
        
        $content = @file_get_contents($composerUrl, false, $context);
        
        if ($content === false || strlen($content) < 1024 * 1024) {
            return false;
        }
        
        $written = @file_put_contents($targetPath, $content);
        return $written !== false && $written > 0;
    }
    
    return false;
}

/**
 * Installa automaticamente le dipendenze Composer
 * 
 * @param string $pluginDir Directory del plugin
 * @return bool True se l'installazione è riuscita, false altrimenti
 */
function fp_resv_install_composer_dependencies(string $pluginDir): bool
{
    $errorDetails = [];
    $lockFile = $pluginDir . '/.composer-install.lock';
    
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        if (time() - $lockTime > 600) {
            @unlink($lockFile);
        } else {
            return false;
        }
    }
    
    if (@file_put_contents($lockFile, time()) === false) {
        return false;
    }
    register_shutdown_function(function () use ($lockFile) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    });
    
    if (!is_writable($pluginDir)) {
        @unlink($lockFile);
        return false;
    }
    
    $composer = null;
    $localComposer = $pluginDir . '/composer.phar';
    
    if (file_exists($localComposer) && is_executable($localComposer)) {
        $composer = escapeshellarg($localComposer);
    } elseif (file_exists($localComposer)) {
        $composer = 'php ' . escapeshellarg($localComposer);
    } else {
        $parentComposer = dirname($pluginDir) . '/composer.phar';
        if (file_exists($parentComposer) && is_executable($parentComposer)) {
            $composer = escapeshellarg($parentComposer);
        } else {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $whichComposer = $isWindows ? @shell_exec('where composer 2>nul') : @shell_exec('which composer 2>/dev/null');
            if ($whichComposer && trim($whichComposer)) {
                $composer = 'composer';
            } elseif (function_exists('curl_init') || function_exists('file_get_contents')) {
                if (fp_resv_download_composer_phar($localComposer) && file_exists($localComposer)) {
                    @chmod($localComposer, 0755);
                    $composer = 'php ' . escapeshellarg($localComposer);
                }
            }
        }
    }
    
    if ($composer === null) {
        @unlink($lockFile);
        return false;
    }
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $command = $isWindows
        ? sprintf('%s install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1', $composer)
        : sprintf('cd %s && %s install --no-dev --prefer-dist --no-interaction --optimize-autoloader 2>&1', escapeshellarg($pluginDir), $composer);
    
    $returnVar = -1;
    $output = '';
    $executed = false;
    
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', ini_get('disable_functions')), true)) {
        $descriptorspec = [[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']]];
        $process = @proc_open($command, $descriptorspec, $pipes, $pluginDir);
        
        if (is_resource($process)) {
            $executed = true;
            if (isset($pipes[1])) stream_set_timeout($pipes[1], 300);
            if (isset($pipes[2])) stream_set_timeout($pipes[2], 300);
            
            $stdout = isset($pipes[1]) ? stream_get_contents($pipes[1]) : '';
            $stderr = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
            foreach ($pipes as $pipe) if (isset($pipe)) fclose($pipe);
            
            $output = $stdout . ($stderr ? "\n" . $stderr : '');
            $returnVar = proc_close($process);
        }
    }
    
    if (!$executed) {
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
        }
        
        @chdir($oldCwd);
    }
    
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    
    $autoloadPath = $pluginDir . '/vendor/autoload.php';
    $autoloadExists = is_readable($autoloadPath);
    $success = ($autoloadExists && filesize($autoloadPath) > 1000);
    
    if (!$success) {
        $errorDetails[] = 'Return code: ' . $returnVar;
        if ($output && (stripos($output, '<html') !== false || stripos($output, '<script') !== false)) {
            $errorDetails[] = 'Output contiene HTML';
        }
        update_option('fp_resv_composer_install_error', $errorDetails);
    }
    
    return $success;
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
                $errorDetails = get_option('fp_resv_composer_install_error', []);
                // Ottieni la directory del plugin (__DIR__ è più sicuro)
                $pluginDir = __DIR__;
                
                $notice = '<div class="notice notice-error"><p><strong>FP Restaurant Reservations - Errore Critico</strong></p>';
                $notice .= '<p>' . esc_html($message) . '</p>';
                $notice .= '<p><code>' . esc_html($autoload) . '</code></p>';
                
                if ($installAttempted) {
                    $notice .= '<p><strong>⚠️ Installazione automatica fallita.</strong></p>';
                    
                    if (!empty($errorDetails) && is_array($errorDetails)) {
                        $notice .= '<details style="margin-top: 10px;"><summary style="cursor: pointer; font-weight: bold;">Dettagli errore (clicca per espandere)</summary>';
                        $notice .= '<ul style="margin-left: 20px; margin-top: 10px;">';
                        foreach ($errorDetails as $detail) {
                            $notice .= '<li>' . esc_html($detail) . '</li>';
                        }
                        $notice .= '</ul></details>';
                    }
                }
                
                $notice .= '<p><strong>Soluzione:</strong></p>';
                $notice .= '<ol style="margin-left: 20px;">';
                $notice .= '<li>Apri un terminale (SSH) e vai nella directory del plugin:<br>';
                $notice .= '<code style="display: block; margin: 5px 0; padding: 5px; background: #f0f0f0;">cd ' . esc_html($pluginDir) . '</code></li>';
                $notice .= '<li>Esegui il comando Composer:<br>';
                $notice .= '<code style="display: block; margin: 5px 0; padding: 5px; background: #f0f0f0;">composer install --no-dev --prefer-dist</code></li>';
                $notice .= '<li>Se Composer non è installato sul server, puoi:<ul style="margin-top: 5px;">';
                $notice .= '<li>Scaricare <code>composer.phar</code> e metterlo nella directory del plugin</li>';
                $notice .= '<li>Oppure installare Composer globalmente seguendo le istruzioni su <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a></li>';
                $notice .= '</ul></li>';
                $notice .= '<li>Assicurati che la directory del plugin abbia permessi di scrittura:<br>';
                $notice .= '<code style="display: block; margin: 5px 0; padding: 5px; background: #f0f0f0;">chmod 755 ' . esc_html($pluginDir) . '</code></li>';
                $notice .= '</ol>';
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

// Le funzioni di installazione Composer sono già definite sopra, prima del require $autoload

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
