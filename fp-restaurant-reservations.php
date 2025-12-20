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
 * Funzione per mostrare notice di attivazione
 * Deve essere definita PRIMA degli hook di attivazione
 */
function fp_resv_show_activation_notice(): void {
    if (!function_exists('get_option')) {
        return;
    }
    
    $activationErrors = get_option('fp_resv_activation_errors', []);
    if (empty($activationErrors) || !is_array($activationErrors)) {
        return;
    }
    
    // Mostra direttamente il notice (questa funzione viene chiamata dentro add_action('admin_notices'))
    $notice = '<div class="notice notice-error is-dismissible"><p><strong>FP Restaurant Reservations - Problemi durante l\'attivazione</strong></p>';
    $notice .= '<ul style="margin-left: 20px; margin-top: 10px;">';
    foreach ($activationErrors as $error) {
        $notice .= '<li>' . (function_exists('esc_html') ? esc_html($error) : htmlspecialchars($error, ENT_QUOTES, 'UTF-8')) . '</li>';
    }
    $notice .= '</ul>';
    $notice .= '<p><strong>Il plugin è stato attivato ma potrebbe non funzionare correttamente.</strong></p>';
    $notice .= '<p>Risolvi i problemi sopra indicati e ricarica la pagina.</p>';
    $notice .= '</div>';
    echo $notice;
}

// Registra il notice PRIMA di qualsiasi altra cosa, così viene sempre mostrato
// anche se il plugin esce prima a causa di errori
if (function_exists('add_action')) {
    add_action('admin_notices', 'fp_resv_show_activation_notice', 1);
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
        $errorDetails[] = 'Composer non trovato sul server';
        $errorDetails[] = 'Cercato in: ' . $localComposer;
        $errorDetails[] = 'Cercato in: ' . dirname($pluginDir) . '/composer.phar';
        $errorDetails[] = 'Comando globale "composer" non disponibile';
        update_option('fp_resv_composer_install_error', $errorDetails);
        @unlink($lockFile);
        return false;
    }
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // Risolvi il percorso reale (per gestire junction/symlink su Windows)
    $pluginDirReal = $pluginDir;
    if ($isWindows && function_exists('realpath')) {
        $realPath = realpath($pluginDir);
        if ($realPath !== false) {
            $pluginDirReal = $realPath;
        }
    }
    
    // Normalizza il percorso per il sistema operativo
    $pluginDirNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pluginDirReal);
    
    // Costruisci il comando Composer
    $composerCommand = sprintf('%s install --no-dev --prefer-dist --no-interaction --optimize-autoloader', $composer);
    
    $returnVar = -1;
    $output = '';
    $executed = false;
    
    // Prova prima con proc_open cambiando directory nel working directory
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', ini_get('disable_functions')), true)) {
        $descriptorspec = [[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']]];
        
        // Su Windows, usa cmd.exe
        if ($isWindows) {
            // Usa cmd.exe con /c e cambia directory nel working directory di proc_open
            $command = sprintf('cmd /c "%s 2>&1"', $composerCommand);
        } else {
            // Su Linux, usa sh -c
            $command = sprintf('sh -c "%s 2>&1"', $composerCommand);
        }
        
        // Imposta la working directory nel processo
        $process = @proc_open($command, $descriptorspec, $pipes, $pluginDirNormalized);
        
        if (is_resource($process)) {
            $executed = true;
            if (isset($pipes[1])) stream_set_timeout($pipes[1], 300);
            if (isset($pipes[2])) stream_set_timeout($pipes[2], 300);
            
            // Leggi l'output in modo non bloccante
            $stdout = '';
            $stderr = '';
            
            // Chiudi stdin
            if (isset($pipes[0])) {
                fclose($pipes[0]);
            }
            
            // Leggi stdout e stderr
            while (is_resource($process)) {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                
                if (stream_select($read, $write, $except, 1) > 0) {
                    foreach ($read as $stream) {
                        if ($stream === $pipes[1]) {
                            $data = stream_get_contents($pipes[1]);
                            if ($data !== false) {
                                $stdout .= $data;
                            }
                        } elseif ($stream === $pipes[2]) {
                            $data = stream_get_contents($pipes[2]);
                            if ($data !== false) {
                                $stderr .= $data;
                            }
                        }
                    }
                }
                
                // Controlla se il processo è ancora in esecuzione
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
            }
            
            // Leggi eventuali dati rimanenti
            if (isset($pipes[1])) {
                $remaining = stream_get_contents($pipes[1]);
                if ($remaining !== false) {
                    $stdout .= $remaining;
                }
                fclose($pipes[1]);
            }
            if (isset($pipes[2])) {
                $remaining = stream_get_contents($pipes[2]);
                if ($remaining !== false) {
                    $stderr .= $remaining;
                }
                fclose($pipes[2]);
            }
            
            $output = $stdout . ($stderr ? "\n" . $stderr : '');
            $returnVar = proc_close($process);
        }
    }
    
    // Fallback: usa exec o shell_exec cambiando directory prima
    if (!$executed) {
        $oldCwd = getcwd();
        $changedDir = @chdir($pluginDirNormalized);
        
        if ($changedDir) {
            if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')), true)) {
                $executed = true;
                @exec($composerCommand . ' 2>&1', $outputLines, $returnVar);
                $output = implode("\n", $outputLines);
            } elseif (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')), true)) {
                $executed = true;
                $output = @shell_exec($composerCommand . ' 2>&1');
                $returnVar = ($output !== null && $output !== '') ? 0 : 1;
            }
        }
        
        @chdir($oldCwd);
    }
    
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    
    $autoloadPath = $pluginDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $autoloadExists = is_readable($autoloadPath);
    $success = ($autoloadExists && filesize($autoloadPath) > 1000);
    
    if (!$success) {
        $errorDetails = [];
        $errorDetails[] = 'Comando eseguito con return code: ' . $returnVar;
        if ($output) {
            // Limita l'output a 2000 caratteri per vedere più dettagli
            $outputPreview = strlen($output) > 2000 ? substr($output, 0, 2000) . "\n... (output troncato, totale: " . strlen($output) . " caratteri)" : $output;
            if (stripos($output, '<html') !== false || stripos($output, '<script') !== false) {
                $errorDetails[] = 'Output contiene HTML (possibile errore del server)';
                $errorDetails[] = 'Output (primi 2000 caratteri): ' . $outputPreview;
            } else {
                $errorDetails[] = 'Output Composer: ' . $outputPreview;
            }
        } else {
            $errorDetails[] = 'Nessun output dal comando Composer';
            if (!$executed) {
                $errorDetails[] = 'Comando non eseguito - funzioni disponibili: proc_open=' . (function_exists('proc_open') ? 'Sì' : 'No') . ', exec=' . (function_exists('exec') ? 'Sì' : 'No') . ', shell_exec=' . (function_exists('shell_exec') ? 'Sì' : 'No');
            }
        }
        $errorDetails[] = 'Comando eseguito: ' . $command;
        $errorDetails[] = 'Composer trovato: ' . $composer;
        $errorDetails[] = 'Directory plugin: ' . $pluginDir;
        $errorDetails[] = 'Autoload path: ' . $autoloadPath;
        $errorDetails[] = 'Autoload esiste: ' . ($autoloadExists ? 'Sì' : 'No');
        if ($autoloadExists) {
            $errorDetails[] = 'Dimensione autoload: ' . filesize($autoloadPath) . ' bytes';
        }
        update_option('fp_resv_composer_install_error', $errorDetails);
    } else {
        // Pulisci gli errori precedenti se l'installazione è riuscita
        delete_option('fp_resv_composer_install_error');
    }
    
    return $success;
}

// Registra gli hook di attivazione/deattivazione PRIMA di caricare l'autoloader
// Questo assicura che siano sempre disponibili, anche se l'autoloader non è ancora caricato
register_activation_hook(__FILE__, static function (): void {
    // Hook di attivazione SEMPLIFICATO - solo salva errori, non esegue codice complesso
    $activationErrors = [];
    $pluginDir = dirname(__FILE__);
    $autoload = $pluginDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    
    // Verifica solo se autoload esiste - NIENTE ALTRO
    if (!file_exists($autoload) || !is_readable($autoload)) {
        $activationErrors[] = 'File vendor/autoload.php non trovato. Esegui "composer install" nella directory del plugin.';
        update_option('fp_resv_activation_errors', $activationErrors);
        return;
    }
    
    // Se autoload esiste, prova a caricare Lifecycle SOLO se possibile
    $lifecyclePath = $pluginDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Kernel' . DIRECTORY_SEPARATOR . 'Lifecycle.php';
    if (file_exists($lifecyclePath) && is_readable($lifecyclePath)) {
        try {
            @include $autoload;
            if (class_exists('Composer\Autoload\ClassLoader')) {
                @require_once $lifecyclePath;
                if (class_exists('FP\Resv\Kernel\Lifecycle')) {
                    try {
                        FP\Resv\Kernel\Lifecycle::activate(__FILE__);
                    } catch (Throwable $e) {
                        $activationErrors[] = 'Errore in Lifecycle::activate: ' . $e->getMessage();
                    }
                }
            }
        } catch (Throwable $e) {
            $activationErrors[] = 'Errore durante attivazione: ' . $e->getMessage();
        }
    }
    
    // Salva errori se presenti
    if (!empty($activationErrors)) {
        update_option('fp_resv_activation_errors', $activationErrors);
    } else {
        delete_option('fp_resv_activation_errors');
    }
});

register_deactivation_hook(__FILE__, static function (): void {
    $lifecyclePath = dirname(__FILE__) . '/src/Kernel/Lifecycle.php';
    if (file_exists($lifecyclePath) && is_readable($lifecyclePath)) {
        try {
            require_once $lifecyclePath;
            if (class_exists('FP\Resv\Kernel\Lifecycle')) {
                FP\Resv\Kernel\Lifecycle::deactivate();
            }
        } catch (Throwable $e) {
            // Ignora errori durante la disattivazione
        }
    }
});

// Il notice degli errori di attivazione è già stato registrato all'inizio del file (riga 86)
// Load autoloader - REQUIRED for plugin to work
$autoload = __DIR__ . '/vendor/autoload.php';

// Se autoload non esiste, mostra solo notice e esci - NIENTE ALTRO
if (!file_exists($autoload) || !is_readable($autoload)) {
    // Mostra notice semplice e esci - non tentare installazione automatica qui
    if (function_exists('add_action')) {
        add_action('admin_notices', function () use ($autoload) {
            $pluginDir = __DIR__;
            $notice = '<div class="notice notice-error is-dismissible">';
            $notice .= '<p><strong>FP Restaurant Reservations - Dipendenze mancanti</strong></p>';
            $notice .= '<p>Il file <code>' . esc_html($autoload) . '</code> non è stato trovato.</p>';
            $notice .= '<p><strong>Soluzione:</strong> Esegui <code>composer install</code> nella directory del plugin.</p>';
            $notice .= '<p>Directory: <code>' . esc_html($pluginDir) . '</code></p>';
            $notice .= '</div>';
            echo $notice;
        });
    }
    return;
}

// Carica l'autoloader - se fallisce, esci silenziosamente
$autoloadLoaded = @include $autoload;
if (!$autoloadLoaded) {
    return;
}

// Le funzioni di installazione Composer sono già definite sopra, prima del require $autoload
// Gli hook di attivazione/deattivazione sono già stati registrati sopra (riga 257), prima del caricamento dell'autoloader
// Il notice degli errori di attivazione è già stato registrato sopra, prima del controllo autoloader

// Inizializza sistema di auto-aggiornamento da GitHub - solo se classe esiste
if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    try {
        $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/franpass87/FP-Restaurant-Reservations/',
            __FILE__,
            'fp-restaurant-reservations'
        );
        if (method_exists($updateChecker, 'getVcsApi')) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
    } catch (Throwable $e) {
        // Ignora errori nel sistema di aggiornamento
    }
}

// Bootstrap plugin - solo se i file esistono
$bootstrapGuardPath = __DIR__ . '/src/Core/BootstrapGuard.php';
if (!file_exists($bootstrapGuardPath) || !is_readable($bootstrapGuardPath)) {
    return;
}

@require_once $bootstrapGuardPath;

if (!class_exists('FP\Resv\Core\BootstrapGuard')) {
    return;
}

$pluginFile = __FILE__;

try {
    // Verifica che le dipendenze PSR siano disponibili prima di procedere
    if (!interface_exists('Psr\Container\ContainerInterface')) {
        $errors = get_option('fp_resv_activation_errors', []);
        if (!is_array($errors)) {
            $errors = [];
        }
        $errors[] = 'Dipendenza mancante: psr/container. Esegui "composer install" nella directory del plugin.';
        update_option('fp_resv_activation_errors', $errors);
        return;
    }
    
    FP\Resv\Core\BootstrapGuard::run($pluginFile, static function () use ($pluginFile): void {
        $bootstrapPath = __DIR__ . '/src/Kernel/Bootstrap.php';
        if (!file_exists($bootstrapPath) || !is_readable($bootstrapPath)) {
            return;
        }
        @require_once $bootstrapPath;
        
        if (!class_exists('FP\Resv\Kernel\Bootstrap')) {
            return;
        }
        
        $boot = static function () use ($pluginFile): void {
            try {
                FP\Resv\Kernel\Bootstrap::boot($pluginFile);
            } catch (Throwable $e) {
                // Cattura errori durante il boot e salvali
                $errors = get_option('fp_resv_activation_errors', []);
                if (!is_array($errors)) {
                    $errors = [];
                }
                $errors[] = 'Errore durante il bootstrap: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Linea: ' . $e->getLine() . ')';
                update_option('fp_resv_activation_errors', $errors);
            }
        };

        if (\did_action('plugins_loaded')) {
            $boot();
        } else {
            \add_action('plugins_loaded', $boot, 20);
        }
    });
} catch (Throwable $e) {
    // Cattura errori durante il bootstrap e salvali
    $errors = get_option('fp_resv_activation_errors', []);
    if (!is_array($errors)) {
        $errors = [];
    }
    $errors[] = 'Errore durante il bootstrap guard: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Linea: ' . $e->getLine() . ')';
    update_option('fp_resv_activation_errors', $errors);
}

// Gli hook di attivazione/deattivazione sono già stati registrati sopra, prima del caricamento dell'autoloader
