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
    $lockFile = $pluginDir . DIRECTORY_SEPARATOR . '.composer-install.lock';
    
    // Verifica permessi di scrittura PRIMA di creare il lock file
    if (!is_writable($pluginDir)) {
        $errorDetails[] = 'Directory plugin non scrivibile: ' . $pluginDir;
        $errorDetails[] = 'Permessi directory: ' . substr(sprintf('%o', fileperms($pluginDir)), -4);
        update_option('fp_resv_composer_install_error', $errorDetails);
        return false;
    }
    
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        if (time() - $lockTime > 600) {
            @unlink($lockFile);
        } else {
            // Lock file ancora valido, probabilmente un'altra installazione è in corso
            return false;
        }
    }
    
    if (@file_put_contents($lockFile, time()) === false) {
        $errorDetails[] = 'Impossibile creare file lock: ' . $lockFile;
        $errorDetails[] = 'Verifica permessi di scrittura sulla directory';
        update_option('fp_resv_composer_install_error', $errorDetails);
        return false;
    }
    register_shutdown_function(function () use ($lockFile) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    });
    
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
        $errorDetails[] = 'Cercato in: ' . dirname($pluginDir) . DIRECTORY_SEPARATOR . 'composer.phar';
        $errorDetails[] = 'Comando globale "composer" non disponibile';
        
        // Verifica se le funzioni necessarie per il download sono disponibili
        $canDownload = (function_exists('curl_init') || (function_exists('file_get_contents') && ini_get('allow_url_fopen')));
        if (!$canDownload) {
            $errorDetails[] = 'Impossibile scaricare composer.phar: cURL e file_get_contents non disponibili';
        }
        
        // Verifica funzioni PHP disponibili
        $disabledFunctions = ini_get('disable_functions');
        $errorDetails[] = 'Funzioni PHP disabilitate: ' . ($disabledFunctions ?: 'Nessuna');
        $errorDetails[] = 'proc_open disponibile: ' . (function_exists('proc_open') && !in_array('proc_open', explode(',', $disabledFunctions), true) ? 'Sì' : 'No');
        $errorDetails[] = 'exec disponibile: ' . (function_exists('exec') && !in_array('exec', explode(',', $disabledFunctions), true) ? 'Sì' : 'No');
        $errorDetails[] = 'shell_exec disponibile: ' . (function_exists('shell_exec') && !in_array('shell_exec', explode(',', $disabledFunctions), true) ? 'Sì' : 'No');
        
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
            // Timeout più lungo per produzione (10 minuti)
            if (isset($pipes[1])) stream_set_timeout($pipes[1], 600);
            if (isset($pipes[2])) stream_set_timeout($pipes[2], 600);
            
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
        $errorDetails[] = 'Comando eseguito: ' . (isset($command) ? $command : 'N/A');
        $errorDetails[] = 'Composer trovato: ' . $composer;
        $errorDetails[] = 'Directory plugin: ' . $pluginDir;
        $errorDetails[] = 'Directory plugin (reale): ' . (isset($pluginDirReal) ? $pluginDirReal : 'N/A');
        $errorDetails[] = 'Directory plugin (normalizzata): ' . (isset($pluginDirNormalized) ? $pluginDirNormalized : 'N/A');
        $errorDetails[] = 'Autoload path: ' . $autoloadPath;
        $errorDetails[] = 'Autoload esiste: ' . ($autoloadExists ? 'Sì' : 'No');
        if ($autoloadExists) {
            $errorDetails[] = 'Dimensione autoload: ' . filesize($autoloadPath) . ' bytes';
        } else {
            // Verifica se la directory vendor esiste
            $vendorDir = dirname($autoloadPath);
            $errorDetails[] = 'Directory vendor esiste: ' . (is_dir($vendorDir) ? 'Sì' : 'No');
            if (is_dir($vendorDir)) {
                $errorDetails[] = 'Permessi directory vendor: ' . substr(sprintf('%o', fileperms($vendorDir)), -4);
            }
        }
        $errorDetails[] = 'Comando eseguito con successo: ' . ($executed ? 'Sì' : 'No');
        $errorDetails[] = 'PHP versione: ' . PHP_VERSION;
        $errorDetails[] = 'Sistema operativo: ' . PHP_OS;
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
    $pluginDir = dirname(__FILE__);
    $autoload = $pluginDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    
    // Se autoload non esiste, prova a installarlo
    if (!is_readable($autoload) && function_exists('fp_resv_install_composer_dependencies')) {
        $installSuccess = fp_resv_install_composer_dependencies($pluginDir);
        
        // Ricontrolla dopo l'installazione
        if (!$installSuccess || !is_readable($autoload)) {
            // L'installazione è fallita, blocca l'attivazione
            $errorDetails = get_option('fp_resv_composer_install_error', []);
            $errorMessage = 'FP Restaurant Reservations: Impossibile installare le dipendenze Composer durante l\'attivazione.';
            
            if (!empty($errorDetails) && is_array($errorDetails)) {
                $errorMessage .= "\n\nDettagli:\n";
                foreach ($errorDetails as $detail) {
                    $errorMessage .= "- " . $detail . "\n";
                }
            }
            
            // Disattiva il plugin
            if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
                deactivate_plugins(plugin_basename(__FILE__));
            }
            
            // Mostra errore e blocca l'attivazione
            if (function_exists('wp_die')) {
                wp_die(
                    esc_html($errorMessage),
                    'Errore di attivazione - FP Restaurant Reservations',
                    ['back_link' => true]
                );
            }
            return;
        }
    }
    
    // Verifica che autoload esista prima di procedere
    if (!is_readable($autoload)) {
        if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        if (function_exists('wp_die')) {
            wp_die(
                'FP Restaurant Reservations: File vendor/autoload.php non trovato. Verifica che le dipendenze Composer siano installate.',
                'Errore di attivazione',
                ['back_link' => true]
            );
        }
        return;
    }
    
    // Carica l'autoloader
    try {
        @include $autoload;
    } catch (Throwable $e) {
        if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        if (function_exists('wp_die')) {
            wp_die(
                'FP Restaurant Reservations: Errore durante il caricamento dell\'autoloader: ' . $e->getMessage(),
                'Errore di attivazione',
                ['back_link' => true]
            );
        }
        return;
    }
    
    // Verifica che l'autoloader sia stato caricato correttamente
    if (!class_exists('Composer\Autoload\ClassLoader')) {
        if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        if (function_exists('wp_die')) {
            wp_die(
                'FP Restaurant Reservations: Autoloader Composer non caricato correttamente.',
                'Errore di attivazione',
                ['back_link' => true]
            );
        }
        return;
    }
    
    // Ora carica Lifecycle se possibile
    $lifecyclePath = $pluginDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Kernel' . DIRECTORY_SEPARATOR . 'Lifecycle.php';
    if (file_exists($lifecyclePath) && is_readable($lifecyclePath)) {
        try {
            require_once $lifecyclePath;
            if (class_exists('FP\Resv\Kernel\Lifecycle')) {
                FP\Resv\Kernel\Lifecycle::activate(__FILE__);
            } else {
                if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
                    deactivate_plugins(plugin_basename(__FILE__));
                }
                if (function_exists('wp_die')) {
                    wp_die(
                        'FP Restaurant Reservations: Classe Lifecycle non trovata dopo il caricamento.',
                        'Errore di attivazione',
                        ['back_link' => true]
                    );
                }
            }
        } catch (Throwable $e) {
            if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
                deactivate_plugins(plugin_basename(__FILE__));
            }
            if (function_exists('wp_die')) {
                wp_die(
                    'FP Restaurant Reservations: Errore durante l\'attivazione: ' . $e->getMessage(),
                    'Errore di attivazione',
                    ['back_link' => true]
                );
            }
        }
    } else {
        if (function_exists('deactivate_plugins') && function_exists('plugin_basename')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        if (function_exists('wp_die')) {
            wp_die(
                'FP Restaurant Reservations: File Lifecycle.php non trovato.',
                'Errore di attivazione',
                ['back_link' => true]
            );
        }
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

// Verifica finale che autoload.php esista prima di caricarlo
if (!file_exists($autoload) || !is_readable($autoload)) {
    // Non fare nulla qui, il controllo è già stato fatto sopra
    // Questo evita errori fatali durante il caricamento
    return;
}

// Carica l'autoloader in modo sicuro
$autoloadLoaded = false;
try {
    $autoloadLoaded = @include $autoload;
} catch (Throwable $e) {
    // Se c'è un errore durante il caricamento, loggalo e esci
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[FP Restaurant Reservations] Errore nel caricamento autoloader: ' . $e->getMessage());
    }
    return;
}

// Se l'autoloader non è stato caricato, esci silenziosamente
if (!$autoloadLoaded) {
    return;
}

// Le funzioni di installazione Composer sono già definite sopra, prima del require $autoload
// Gli hook di attivazione/deattivazione sono già stati registrati sopra (riga 257), prima del caricamento dell'autoloader

// Inizializza sistema di auto-aggiornamento da GitHub
// Solo se l'autoloader è stato caricato correttamente
try {
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/franpass87/FP-Restaurant-Reservations/',
            __FILE__,
            'fp-restaurant-reservations'
        );
        
        // Usa le GitHub Releases per gli aggiornamenti
        if (method_exists($updateChecker, 'getVcsApi')) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
    }
} catch (Throwable $e) {
    // Ignora errori nel sistema di aggiornamento
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[FP Restaurant Reservations] Errore nel sistema di aggiornamento: ' . $e->getMessage());
    }
}

// Bootstrap plugin using new architecture
// Keep BootstrapGuard for error handling during transition
$bootstrapGuardPath = __DIR__ . '/src/Core/BootstrapGuard.php';
if (!is_readable($bootstrapGuardPath)) {
    if (function_exists('add_action')) {
        add_action('admin_notices', function () use ($bootstrapGuardPath) {
            $notice = '<div class="notice notice-error"><p><strong>FP Restaurant Reservations - Errore Critico</strong></p>';
            $notice .= '<p>File BootstrapGuard.php non trovato: ' . esc_html($bootstrapGuardPath) . '</p>';
            $notice .= '</div>';
            echo $notice;
        });
    }
    return;
}

require_once $bootstrapGuardPath;

$pluginFile = __FILE__;

if (!class_exists('FP\Resv\Core\BootstrapGuard')) {
    // Se la classe non esiste, esci silenziosamente per evitare errori fatali
    return;
}

try {
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
} catch (Throwable $e) {
    // Se c'è un errore durante il bootstrap, loggalo ma non bloccare il caricamento
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('[FP Restaurant Reservations] Errore durante il bootstrap: ' . $e->getMessage());
    }
}

// Gli hook di attivazione/deattivazione sono già stati registrati sopra, prima del caricamento dell'autoloader
