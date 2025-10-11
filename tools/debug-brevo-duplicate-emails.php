<?php
/**
 * Script di debug per investigare il problema delle email duplicate (IT + EN) inviate da Brevo
 * 
 * Uso: wp eval-file wp-content/plugins/fp-restaurant-reservations/tools/debug-brevo-duplicate-emails.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

echo "=== DEBUG: Email Duplicate Brevo (IT + EN) ===\n\n";

// 1. Trova le ultime prenotazioni
echo "--- ULTIME 5 PRENOTAZIONI ---\n";
$recent_reservations = $wpdb->get_results(
    "SELECT r.id, r.status, r.date, r.time, r.created_at, c.email, c.first_name, c.last_name, c.lang
     FROM {$wpdb->prefix}fp_reservations r
     LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
     ORDER BY r.created_at DESC
     LIMIT 5",
    ARRAY_A
);

foreach ($recent_reservations as $reservation) {
    echo sprintf(
        "ID: %d | Email: %s | Nome: %s %s | Lingua: %s | Data: %s %s | Stato: %s | Creata: %s\n",
        $reservation['id'],
        $reservation['email'],
        $reservation['first_name'],
        $reservation['last_name'],
        $reservation['lang'] ?? 'N/A',
        $reservation['date'],
        $reservation['time'],
        $reservation['status'],
        $reservation['created_at']
    );
}

echo "\n";

// 2. Chiedi quale prenotazione investigare
if (isset($args[0])) {
    $reservation_id = (int) $args[0];
} else {
    echo "Inserisci l'ID della prenotazione da investigare (o premi invio per usare l'ultima): ";
    $input = trim(fgets(STDIN));
    $reservation_id = $input !== '' ? (int) $input : (int) $recent_reservations[0]['id'];
}

echo "\n=== ANALISI PRENOTAZIONE #{$reservation_id} ===\n\n";

// 3. Recupera i dettagli della prenotazione
$reservation = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT r.*, c.email, c.first_name, c.last_name, c.phone, c.lang
         FROM {$wpdb->prefix}fp_reservations r
         LEFT JOIN {$wpdb->prefix}fp_customers c ON r.customer_id = c.id
         WHERE r.id = %d",
        $reservation_id
    ),
    ARRAY_A
);

if (!$reservation) {
    echo "❌ Prenotazione non trovata!\n";
    exit;
}

echo "--- DETTAGLI PRENOTAZIONE ---\n";
echo "Email: {$reservation['email']}\n";
echo "Nome: {$reservation['first_name']} {$reservation['last_name']}\n";
echo "Telefono: {$reservation['phone']}\n";
echo "Lingua cliente (c.lang): {$reservation['lang']}\n";
echo "Lingua prenotazione (r.lang): {$reservation['lang']}\n";
echo "Data: {$reservation['date']} {$reservation['time']}\n";
echo "Stato: {$reservation['status']}\n";
echo "Creata: {$reservation['created_at']}\n\n";

// 4. Verifica log di Brevo per questa prenotazione
echo "--- LOG BREVO PER QUESTA PRENOTAZIONE ---\n";
$brevo_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, action, status, error, created_at, payload_snippet
         FROM {$wpdb->prefix}fp_brevo_log
         WHERE reservation_id = %d
         ORDER BY created_at ASC",
        $reservation_id
    ),
    ARRAY_A
);

if (empty($brevo_logs)) {
    echo "⚠️  NESSUN LOG BREVO TROVATO per questa prenotazione!\n";
    echo "Questo potrebbe significare che:\n";
    echo "1. Brevo non è abilitato\n";
    echo "2. Il canale email non è impostato su 'Brevo'\n";
    echo "3. Si è verificato un errore prima del logging\n\n";
} else {
    foreach ($brevo_logs as $log) {
        $status_icon = $log['status'] === 'success' ? '✅' : '❌';
        echo sprintf(
            "%s [%s] %s | Status: %s | Error: %s\n",
            $status_icon,
            $log['created_at'],
            $log['action'],
            $log['status'],
            $log['error'] ?? 'none'
        );
        
        if ($log['payload_snippet']) {
            $payload = json_decode($log['payload_snippet'], true);
            if (is_array($payload)) {
                echo "   Payload: " . substr($log['payload_snippet'], 0, 200) . "...\n";
            }
        }
        echo "\n";
    }
}

// 5. Conta quanti eventi email_confirmation sono stati inviati
echo "--- VERIFICA DUPLICATI email_confirmation ---\n";
$email_confirmation_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}fp_brevo_log
         WHERE reservation_id = %d AND action = 'email_confirmation'",
        $reservation_id
    )
);

$email_confirmation_success_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}fp_brevo_log
         WHERE reservation_id = %d AND action = 'email_confirmation' AND status = 'success'",
        $reservation_id
    )
);

echo "Eventi email_confirmation totali: {$email_confirmation_count}\n";
echo "Eventi email_confirmation riusciti: {$email_confirmation_success_count}\n";

if ($email_confirmation_success_count > 1) {
    echo "⚠️  PROBLEMA RILEVATO: Più di un evento email_confirmation è stato inviato con successo!\n";
    echo "Questo potrebbe causare l'invio di email duplicate.\n";
} elseif ($email_confirmation_success_count === 1) {
    echo "✅ Un solo evento email_confirmation è stato inviato (corretto)\n";
} else {
    echo "⚠️  Nessun evento email_confirmation riuscito trovato\n";
}

echo "\n";

// 6. Verifica le impostazioni del canale di notifica
echo "--- CONFIGURAZIONE CANALI NOTIFICHE ---\n";
$notification_settings = get_option('fp_resv_notifications', []);
$confirmation_channel = $notification_settings['confirmation_channel'] ?? 'plugin';
$confirmation_enabled = ($notification_settings['confirmation_enabled'] ?? '1') === '1';

echo "Canale conferma: {$confirmation_channel}\n";
echo "Conferma abilitata: " . ($confirmation_enabled ? 'SI' : 'NO') . "\n";

if ($confirmation_channel === 'brevo') {
    echo "✅ Brevo è configurato come canale per le email di conferma\n";
} else {
    echo "⚠️  Brevo NON è il canale configurato (è '{$confirmation_channel}')\n";
}

echo "\n";

// 7. Verifica configurazione Brevo (liste IT/EN)
echo "--- CONFIGURAZIONE BREVO (LISTE) ---\n";
$brevo_settings = get_option('fp_resv_brevo', []);
$brevo_enabled = ($brevo_settings['brevo_enabled'] ?? '0') === '1';
$brevo_list_it = $brevo_settings['brevo_list_id_it'] ?? '';
$brevo_list_en = $brevo_settings['brevo_list_id_en'] ?? '';
$brevo_list_default = $brevo_settings['brevo_list_id'] ?? '';

echo "Brevo abilitato: " . ($brevo_enabled ? 'SI' : 'NO') . "\n";
echo "Lista IT: {$brevo_list_it}\n";
echo "Lista EN: {$brevo_list_en}\n";
echo "Lista default: {$brevo_list_default}\n";

if ($brevo_list_it !== '' && $brevo_list_en !== '') {
    echo "\n⚠️  POSSIBILE CAUSA DEL PROBLEMA:\n";
    echo "Sono configurate liste separate per IT e EN.\n";
    echo "Se in Brevo ci sono DUE automazioni che ascoltano l'evento 'email_confirmation':\n";
    echo "- Una per la lista IT (invia email in italiano)\n";
    echo "- Una per la lista EN (invia email in inglese)\n";
    echo "Entrambe potrebbero attivarsi per lo stesso evento.\n\n";
    
    echo "SOLUZIONE RACCOMANDATA:\n";
    echo "In Brevo, le automazioni per 'email_confirmation' dovrebbero:\n";
    echo "1. Avere una CONDIZIONE che filtra per lingua del contatto\n";
    echo "2. Oppure essere associate a UNA SOLA lista (IT o EN, non entrambe)\n";
    echo "3. Oppure usare un template multilingua con logica condizionale\n";
}

echo "\n";

// 8. Verifica log di contact_upsert per vedere a quale lista è stato iscritto
echo "--- ISCRIZIONE LISTE BREVO ---\n";
$contact_upsert_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, created_at, payload_snippet, status
         FROM {$wpdb->prefix}fp_brevo_log
         WHERE reservation_id = %d AND action IN ('contact_upsert', 'subscribe')
         ORDER BY created_at ASC",
        $reservation_id
    ),
    ARRAY_A
);

if (empty($contact_upsert_logs)) {
    echo "⚠️  Nessun log di iscrizione trovato\n";
} else {
    foreach ($contact_upsert_logs as $log) {
        $payload = json_decode($log['payload_snippet'], true);
        if (is_array($payload)) {
            $list_id = $payload['list_id'] ?? ($payload['payload']['listIds'][0] ?? 'N/A');
            $list_key = $payload['list_key'] ?? $payload['list'] ?? 'N/A';
            
            echo sprintf(
                "[%s] Iscritto a lista: %s (ID: %s) | Status: %s\n",
                $log['created_at'],
                $list_key,
                $list_id,
                $log['status']
            );
        }
    }
}

echo "\n";

// 9. Verifica mail_log per vedere quante email sono state effettivamente inviate
echo "--- LOG EMAIL INVIATE (wp_fp_mail_log) ---\n";
$mail_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, recipient, subject, status, channel, created_at
         FROM {$wpdb->prefix}fp_mail_log
         WHERE context LIKE %s
         ORDER BY created_at DESC
         LIMIT 10",
        '%"reservation_id":' . $reservation_id . '%'
    ),
    ARRAY_A
);

if (empty($mail_logs)) {
    echo "⚠️  Nessuna email trovata nei log interni del plugin\n";
    echo "Nota: Se usi Brevo per le email, le email vengono inviate tramite Brevo\n";
    echo "e potrebbero non apparire in questo log.\n";
} else {
    foreach ($mail_logs as $mail) {
        $status_icon = $mail['status'] === 'sent' ? '✅' : '❌';
        echo sprintf(
            "%s [%s] A: %s | Oggetto: %s | Canale: %s | Status: %s\n",
            $status_icon,
            $mail['created_at'],
            $mail['recipient'],
            substr($mail['subject'], 0, 50),
            $mail['channel'],
            $mail['status']
        );
    }
}

echo "\n";

// 10. Conclusioni e raccomandazioni
echo "=== CONCLUSIONI ===\n\n";

if ($email_confirmation_success_count === 1) {
    echo "✅ Il plugin ha inviato CORRETTAMENTE un solo evento 'email_confirmation' a Brevo.\n\n";
    echo "Se ricevi comunque due email (IT + EN), il problema è nella configurazione di Brevo:\n\n";
    echo "VERIFICA IN BREVO:\n";
    echo "1. Vai su Automations → Email di conferma prenotazione\n";
    echo "2. Controlla se ci sono DUE automazioni diverse per lo stesso evento\n";
    echo "3. Verifica le condizioni di trigger:\n";
    echo "   - Dovrebbero filtrare per lingua del contatto\n";
    echo "   - Oppure essere associate a liste diverse\n\n";
    echo "SOLUZIONE:\n";
    echo "Assicurati che ogni automazione abbia una condizione che la attiva SOLO per la lingua corretta:\n";
    echo "- Automazione IT: Contact attribute 'LANGUAGE' = 'IT' oppure 'it'\n";
    echo "- Automazione EN: Contact attribute 'LANGUAGE' = 'EN' oppure 'en'\n";
    echo "Oppure usa UN SOLO template multilingua invece di due automazioni separate.\n";
} elseif ($email_confirmation_success_count > 1) {
    echo "❌ PROBLEMA RILEVATO NEL PLUGIN:\n";
    echo "Il plugin ha inviato {$email_confirmation_success_count} eventi 'email_confirmation'.\n";
    echo "Questo non dovrebbe succedere perché c'è un controllo anti-duplicazione.\n\n";
    echo "AZIONE RICHIESTA:\n";
    echo "Segnala questo problema agli sviluppatori con i dettagli di questa prenotazione (#{$reservation_id}).\n";
} else {
    echo "⚠️  Nessun evento email_confirmation trovato per questa prenotazione.\n";
    echo "Verifica la configurazione del canale notifiche.\n";
}

echo "\n=== FINE DEBUG ===\n";
