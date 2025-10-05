Param(
  [string]$WpUrl = "http://localhost:8889",
  [string]$Title = "FP Reservations Dev",
  [string]$AdminUser = "admin",
  [string]$AdminPass = "admin",
  [string]$AdminEmail = "admin@example.com"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Set-Location (Join-Path $PSScriptRoot "..")

Write-Host "[wp-init] Attendo WordPress in $WpUrl..."
for ($i=0; $i -lt 60; $i++) {
  try {
    docker compose exec -T wordpress bash -lc "curl -sSf http://localhost | head -n1 >/dev/null" | Out-Null
    break
  } catch {
    Start-Sleep -Seconds 2
  }
}

Write-Host "[wp-init] Installazione WP..."
docker compose run --rm wpcli core install --url="$WpUrl" --title="$Title" --admin_user="$AdminUser" --admin_password="$AdminPass" --admin_email="$AdminEmail" | Out-Null

Write-Host "[wp-init] Attivo plugin FP Restaurant Reservations..."
docker compose run --rm wpcli plugin activate fp-restaurant-reservations | Out-Null

Write-Host "[wp-init] Eseguo migrazioni plugin..."
docker compose run --rm wpcli eval "do_action('activate_fp-restaurant-reservations/fp-restaurant-reservations.php');" | Out-Null

Write-Host "[wp-init] Eseguo seed demo..."
docker compose run --rm wpcli eval-file wp-content/plugins/fp-restaurant-reservations/scripts/seed.php | Out-Null

Write-Host "[wp-init] Fatto. Login: $WpUrl/wp-admin (admin/admin)"


