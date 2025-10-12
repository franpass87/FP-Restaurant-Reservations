#!/bin/bash
# Script per aggiornare il plugin in produzione e forzare refresh cache

echo "🔧 FIX AGENDA - Aggiornamento Produzione"
echo "========================================"
echo ""

# Step 1: Verifica di essere nella cartella corretta
if [ ! -f "fp-restaurant-reservations.php" ]; then
    echo "❌ ERRORE: Non sei nella cartella del plugin!"
    echo "Vai prima in: cd /path/to/wp-content/plugins/fp-restaurant-reservations"
    exit 1
fi

echo "✓ Cartella corretta"
echo ""

# Step 2: Verifica branch corrente
echo "📍 Branch corrente:"
git branch | grep '*'
echo ""

# Step 3: Mostra ultimo commit
echo "📝 Ultimo commit locale:"
git log -1 --oneline
echo ""

# Step 4: Pull dal repository
echo "📥 Pulling dal repository..."
git fetch --all
git pull origin main

# Se sei su un branch diverso, usa quello
# git pull origin cursor/debug-agenda-reservation-loading-error-9725

echo ""
echo "✓ Pull completato"
echo ""

# Step 5: Verifica file aggiornato
echo "📄 Verifica file agenda-app.js:"
ls -lah assets/js/admin/agenda-app.js

# Verifica contenuto (cerca stringa che esiste solo nella versione nuova)
if grep -q "Tipo risposta:" assets/js/admin/agenda-app.js; then
    echo "✅ File aggiornato correttamente!"
else
    echo "❌ ATTENZIONE: File potrebbe non essere aggiornato!"
fi
echo ""

# Step 6: Force refresh timestamp
echo "♻️  Force refresh timestamp cache..."

# Metodo A: WP-CLI (se disponibile)
if command -v wp &> /dev/null; then
    echo "Usando WP-CLI..."
    wp option update fp_resv_last_upgrade $(date +%s) --autoload=no --path=$(pwd)/../../..
    wp cache flush --path=$(pwd)/../../..
    echo "✅ Cache aggiornata via WP-CLI"
else
    echo "⚠️  WP-CLI non disponibile, usa metodo manuale:"
    echo "   1. Vai su: https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda&force_refresh_assets=1"
    echo "   2. Oppure esegui query SQL:"
    echo "      UPDATE wp_options SET option_value = UNIX_TIMESTAMP() WHERE option_name = 'fp_resv_last_upgrade';"
fi
echo ""

# Step 7: Pulisci cache server (se presenti)
echo "🧹 Pulizia cache server..."

# Redis
if command -v redis-cli &> /dev/null; then
    echo "Pulendo Redis..."
    redis-cli FLUSHALL &> /dev/null && echo "✅ Redis pulito"
fi

# Memcached
if command -v nc &> /dev/null; then
    echo "flush_all" | nc localhost 11211 &> /dev/null && echo "✅ Memcached pulito"
fi

# OPcache PHP
if command -v php &> /dev/null; then
    echo "Ricaricando PHP opcache..."
    sudo service php-fpm reload &> /dev/null && echo "✅ PHP-FPM ricaricato"
fi

echo ""
echo "================================================"
echo "✅ AGGIORNAMENTO COMPLETATO!"
echo "================================================"
echo ""
echo "⚠️  IMPORTANTE - Adesso nel BROWSER:"
echo ""
echo "1. Apri DevTools (F12)"
echo "2. Vai nel tab Network"
echo "3. Spunta 'Disable cache'"
echo "4. Premi Ctrl+Shift+R (o Cmd+Shift+R su Mac)"
echo "5. Verifica nella Console che vedi:"
echo "   [Agenda] Tipo risposta: object"
echo ""
echo "6. Nel tab Network cerca 'agenda-app.js'"
echo "7. Verifica che abbia: ?ver=0.1.10.$(date +%s)"
echo ""
echo "🔗 URL: https://www.villadianella.it/wp-admin/admin.php?page=fp-resv-agenda"
echo ""
