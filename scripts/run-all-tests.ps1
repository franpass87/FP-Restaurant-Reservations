Param(
  [string]$WpUrl = "http://localhost:8889"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Set-Location (Join-Path $PSScriptRoot "..")

Write-Host "[tests] Avvio docker compose..."
docker compose up -d

Write-Host "[tests] Inizializzo WordPress e plugin..."
powershell -File scripts/wp-init.ps1 -WpUrl $WpUrl

Write-Host "[tests] PHPUnit..."
bash scripts/test-phpunit.sh

Write-Host "[tests] Playwright..."
$env:WP_BASE_URL = $WpUrl
bash scripts/test-playwright.sh

Write-Host "[tests] Completato."


