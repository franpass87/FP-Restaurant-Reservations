#!/usr/bin/env bash
set -euo pipefail

# Endpoint WP
WP_URL=${WP_URL:-http://localhost:8889}
WP_TITLE=${WP_TITLE:-"FP Reservations Dev"}
WP_ADMIN_USER=${WP_ADMIN_USER:-admin}
WP_ADMIN_PASS=${WP_ADMIN_PASS:-admin}
WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL:-admin@example.com}

cd "$(dirname "$0")/.."

echo "[wp-init] Attendo WordPress in ${WP_URL}..."
until docker compose exec -T wordpress bash -lc "curl -sSf http://localhost | head -n1 >/dev/null"; do
  sleep 2
done

echo "[wp-init] Installazione WP..."
docker compose run --rm wpcli core install \
  --url="${WP_URL}" \
  --title="${WP_TITLE}" \
  --admin_user="${WP_ADMIN_USER}" \
  --admin_password="${WP_ADMIN_PASS}" \
  --admin_email="${WP_ADMIN_EMAIL}" || true

echo "[wp-init] Attivo plugin FP Restaurant Reservations..."
docker compose run --rm wpcli plugin activate fp-restaurant-reservations || true

echo "[wp-init] Eseguo migrazioni plugin..."
docker compose run --rm wpcli eval "do_action('activate_fp-restaurant-reservations/fp-restaurant-reservations.php');"

echo "[wp-init] Eseguo seed demo..."
docker compose run --rm wpcli eval-file wp-content/plugins/fp-restaurant-reservations/scripts/seed.php || true

echo "[wp-init] Fatto. Login: ${WP_URL}/wp-admin (admin/admin)"


