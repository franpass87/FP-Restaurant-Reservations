<?php
/**
 * Script di debug per verificare perché le prenotazioni manuali non inviano email/eventi Brevo
 * 
 * Usage: wp eval-file tools/debug-manual-booking-notifications.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

echo "\n=== DEBUG: Notifiche Prenotazioni Manuali ===\n\n";

// Verifica che il plugin sia caricato
if (!class_exists('FP\\Resv\\Core\\Plugin')) {
    echo "❌ Plugin non caricato!\n";
    exit(1);
}

$container = FP\Resv\Core\Plugin::$container;
if (!$container) {
    echo "❌ Container non disponibile!\n";
    exit(1);
}

// Ottieni le opzioni
$options = $container->get(FP\Resv\Domain\Settings\Options::class);
if (!$options) {
    echo "❌ Options service non disponibile!\n";
    exit(1);
}

echo "✅ Plugin caricato correttamente\n\n";

// 1. Verifica configurazione Brevo
echo "--- CONFIGURAZIONE BREVO ---\n";
$brevoSettings = $options->getGroup('fp_resv_brevo', []);

$brevoEnabled = ($brevoSettings['brevo_enabled'] ?? '0') === '1';
$brevoApiKey = trim((string)($brevoSettings['brevo_api_key'] ?? ''));
$brevoApiKeyMasked = $brevoApiKey !== '' ? substr($brevoApiKey, 0, 10) . '...' : '(vuota)';

echo sprintf("Brevo abilitato: %s\n", $brevoEnabled ? '✅ SI' : '❌ NO');
echo sprintf("API Key Brevo: %s\n", $brevoApiKeyMasked);

if (!$brevoEnabled) {
    echo "\n⚠️  PROBLEMA: Brevo non è abilitato!\n";
    echo "   Vai su Impostazioni → Brevo e abilita l'integrazione.\n\n";
}

if ($brevoApiKey === '') {
    echo "\n⚠️  PROBLEMA: API Key Brevo non configurata!\n";
    echo "   Vai su Impostazioni → Brevo e inserisci la tua API Key.\n\n";
}

// 2. Verifica configurazione notifiche
echo "\n--- CONFIGURAZIONE NOTIFICHE ---\n";
$notifSettings = $options->getGroup('fp_resv_notifications', []);

$confirmationChannel = $notifSettings['customer_confirmation_channel'] ?? 'plugin';
$reminderChannel = $notifSettings['customer_reminder_channel'] ?? 'plugin';
$reviewChannel = $notifSettings['customer_review_channel'] ?? 'plugin';

echo sprintf("Canale email conferma: %s\n", $confirmationChannel);
echo sprintf("Canale email reminder: %s\n", $reminderChannel);
echo sprintf("Canale email review: %s\n", $reviewChannel);

if ($brevoEnabled && $brevoApiKey !== '' && $confirmationChannel !== 'brevo') {
    echo "\n⚠️  AVVISO: Brevo è configurato ma il canale di conferma non è impostato su 'brevo'\n";
    echo "   Se vuoi usare Brevo per le email di conferma, vai su:\n";
    echo "   Impostazioni → Notifiche → Email Conferma → Canale = 'Usa Brevo'\n\n";
}

// 3. Verifica email destinatari staff
$restaurantEmails = $notifSettings['restaurant_emails'] ?? [];
$webmasterEmails = $notifSettings['webmaster_emails'] ?? [];

echo "\n--- EMAIL STAFF ---\n";
if (is_array($restaurantEmails) && count($restaurantEmails) > 0) {
    echo "Email ristorante: " . implode(', ', array_filter($restaurantEmails)) . "\n";
} else {
    echo "⚠️  Nessuna email ristorante configurata\n";
}

if (is_array($webmasterEmails) && count($webmasterEmails) > 0) {
    echo "Email webmaster: " . implode(', ', array_filter($webmasterEmails)) . "\n";
} else {
    echo "⚠️  Nessuna email webmaster configurata\n";
}

// 4. Test connessione Brevo
echo "\n--- TEST CONNESSIONE BREVO ---\n";
if ($brevoEnabled && $brevoApiKey !== '') {
    try {
        $brevoClient = $container->get(FP\Resv\Domain\Brevo\Client::class);
        $isConnected = $brevoClient && $brevoClient->isConnected();
        
        if ($isConnected) {
            echo "✅ Connessione a Brevo: OK\n";
        } else {
            echo "❌ Connessione a Brevo: FALLITA\n";
            echo "   Verifica che l'API Key sia valida.\n";
        }
    } catch (Exception $e) {
        echo "❌ Errore durante test connessione: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️  Brevo non configurato, skip test connessione\n";
}

// 5. Verifica log recenti
echo "\n--- LOG RECENTI (ultimi 5) ---\n";
global $wpdb;
$logsTable = $wpdb->prefix . 'fp_brevo_log';

if ($wpdb->get_var("SHOW TABLES LIKE '$logsTable'") === $logsTable) {
    $recentLogs = $wpdb->get_results(
        "SELECT event_type, status, created_at, error_message 
         FROM $logsTable 
         ORDER BY created_at DESC 
         LIMIT 5",
        ARRAY_A
    );
    
    if ($recentLogs && count($recentLogs) > 0) {
        foreach ($recentLogs as $log) {
            $status = $log['status'] === 'success' ? '✅' : '❌';
            echo sprintf(
                "%s %s - %s - %s %s\n",
                $status,
                $log['created_at'],
                $log['event_type'],
                $log['status'],
                $log['error_message'] ? '(' . $log['error_message'] . ')' : ''
            );
        }
    } else {
        echo "Nessun log trovato.\n";
    }
} else {
    echo "Tabella log non trovata.\n";
}

// 6. Verifica log email WordPress
echo "\n--- LOG EMAIL WORDPRESS (ultimi 5) ---\n";
$mailLogsTable = $wpdb->prefix . 'fp_mail_log';

if ($wpdb->get_var("SHOW TABLES LIKE '$mailLogsTable'") === $mailLogsTable) {
    $recentMails = $wpdb->get_results(
        "SELECT recipient, subject, status, created_at, error_message 
         FROM $mailLogsTable 
         ORDER BY created_at DESC 
         LIMIT 5",
        ARRAY_A
    );
    
    if ($recentMails && count($recentMails) > 0) {
        foreach ($recentMails as $mail) {
            $status = $mail['status'] === 'sent' ? '✅' : '❌';
            echo sprintf(
                "%s %s - %s → %s - %s\n",
                $status,
                $mail['created_at'],
                $mail['subject'],
                $mail['recipient'],
                $mail['error_message'] ?? ''
            );
        }
    } else {
        echo "Nessun log email trovato.\n";
    }
} else {
    echo "Tabella log email non trovata.\n";
}

// 7. Riepilogo e raccomandazioni
echo "\n\n=== RIEPILOGO E RACCOMANDAZIONI ===\n\n";

$issues = [];

if (!$brevoEnabled) {
    $issues[] = "Brevo non è abilitato nelle impostazioni";
}

if ($brevoApiKey === '') {
    $issues[] = "API Key Brevo mancante";
}

if ($brevoEnabled && $brevoApiKey !== '' && $confirmationChannel !== 'brevo') {
    $issues[] = "Canale notifiche non impostato su Brevo (attualmente: $confirmationChannel)";
}

if (empty($restaurantEmails) && empty($webmasterEmails)) {
    $issues[] = "Nessuna email staff configurata per ricevere notifiche";
}

if (count($issues) > 0) {
    echo "❌ PROBLEMI RILEVATI:\n";
    foreach ($issues as $i => $issue) {
        echo sprintf("   %d. %s\n", $i + 1, $issue);
    }
    
    echo "\n📋 AZIONI DA FARE:\n";
    
    if (!$brevoEnabled || $brevoApiKey === '') {
        echo "   1. Vai su WordPress Admin → FP Reservations → Impostazioni → Brevo\n";
        echo "      - Abilita Brevo\n";
        echo "      - Inserisci una API Key valida\n";
    }
    
    if ($brevoEnabled && $brevoApiKey !== '' && $confirmationChannel !== 'brevo') {
        echo "   2. Vai su WordPress Admin → FP Reservations → Impostazioni → Notifiche\n";
        echo "      - Imposta 'Canale email conferma' su 'Usa Brevo'\n";
        echo "      - Oppure, se vuoi usare il sistema email del plugin, lascia su 'Plugin'\n";
    }
    
    if (empty($restaurantEmails) && empty($webmasterEmails)) {
        echo "   3. Vai su WordPress Admin → FP Reservations → Impostazioni → Notifiche\n";
        echo "      - Configura almeno un indirizzo email per ricevere notifiche staff\n";
    }
    
    echo "\n   4. Dopo aver configurato tutto, prova a creare una nuova prenotazione manuale\n";
    echo "      e verifica che arrivi l'email/evento Brevo.\n";
} else {
    echo "✅ La configurazione sembra corretta!\n\n";
    
    echo "Se le notifiche ancora non funzionano, verifica:\n";
    echo "   - Che le automazioni in Brevo siano configurate e attive\n";
    echo "   - Che il server WordPress possa inviare email (test con wp_mail)\n";
    echo "   - I log sopra per eventuali errori specifici\n";
}

echo "\n=== FINE DEBUG ===\n";
