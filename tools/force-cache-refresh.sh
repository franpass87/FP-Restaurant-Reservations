#!/bin/bash
# Force cache refresh per agenda-app.js

echo "🔄 Forzare il refresh della cache degli asset..."
echo ""

# Metodo 1: Touch del file per aggiornare timestamp
touch assets/js/admin/agenda-app.js
echo "✅ File timestamp aggiornato: $(stat -c "%y" assets/js/admin/agenda-app.js 2>/dev/null || stat -f "%Sm" assets/js/admin/agenda-app.js)"

# Metodo 2: Forza refresh via Plugin::forceRefreshAssets()
echo ""
echo "📋 Per completare il refresh, esegui UNO di questi comandi:"
echo ""
echo "1️⃣ Via WP-CLI (CONSIGLIATO):"
echo "   wp eval 'FP\\Resv\\Core\\Plugin::forceRefreshAssets(); echo \"Cache refreshed!\";'"
echo ""
echo "2️⃣ Via URL Admin (apri nel browser):"
echo "   [TUO_SITO]/wp-admin/admin.php?page=fp-resv-settings&fp_resv_refresh_cache=1"
echo ""
echo "3️⃣ Hard refresh nel browser:"
echo "   • Chrome/Edge: Ctrl + Shift + R (Windows) o Cmd + Shift + R (Mac)"
echo "   • Firefox: Ctrl + F5 (Windows) o Cmd + Shift + R (Mac)"
echo ""
