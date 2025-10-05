#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export WP_BASE_URL=${WP_BASE_URL:-http://localhost:8889}

echo "[playwright] Installo Node e dipendenze (container)..."
docker run --rm -v "$PWD":/app -w /app node:20 bash -lc "npm install && npx playwright install --with-deps"

echo "[playwright] Eseguo test E2E..."
docker run --rm -v "$PWD":/app -w /app -e WP_BASE_URL="$WP_BASE_URL" --shm-size=1g node:20 bash -lc "npx playwright test --config tests/E2E/playwright.config.ts"


