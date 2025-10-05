#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "[phpunit] Installo dipendenze dev con Composer (container)..."
docker run --rm -v "$PWD":/app -w /app composer:2 install

echo "[phpunit] Eseguo test PHPUnit..."
docker run --rm -v "$PWD":/app -w /app php:8.2-cli bash -lc "./vendor/bin/phpunit -c tests/phpunit.xml"


