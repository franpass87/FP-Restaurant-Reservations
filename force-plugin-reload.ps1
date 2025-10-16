# Script PowerShell per forzare il reload del plugin
# Esegui dalla root del progetto WordPress

Write-Host "🔄 Forzando reload del plugin..." -ForegroundColor Cyan

# Trova wp-cli
$wpPath = "C:\path\to\wp-cli.phar"  # Modifica con il tuo path se necessario

# Se hai wp-cli installato globalmente
if (Get-Command wp -ErrorAction SilentlyContinue) {
    Write-Host "✅ WP-CLI trovato" -ForegroundColor Green
    
    Write-Host "📦 Disattivazione plugin..." -ForegroundColor Yellow
    wp plugin deactivate fp-restaurant-reservations
    
    Write-Host "⏳ Attesa 3 secondi..." -ForegroundColor Yellow
    Start-Sleep -Seconds 3
    
    Write-Host "📦 Riattivazione plugin..." -ForegroundColor Yellow
    wp plugin activate fp-restaurant-reservations
    
    Write-Host "✅ Plugin ricaricato!" -ForegroundColor Green
    Write-Host ""
    Write-Host "👉 Ora vai su:" -ForegroundColor Cyan
    Write-Host "   http://tuo-sito.local/wp-content/plugins/fp-restaurant-reservations/check-database-quick.php" -ForegroundColor White
} else {
    Write-Host "⚠️  WP-CLI non trovato" -ForegroundColor Yellow
    Write-Host "📝 Segui questi step manuali:" -ForegroundColor Cyan
    Write-Host "   1. WordPress Admin → Plugin" -ForegroundColor White
    Write-Host "   2. Disattiva 'FP Restaurant Reservations'" -ForegroundColor White
    Write-Host "   3. Aspetta 5 secondi" -ForegroundColor White
    Write-Host "   4. Riattiva 'FP Restaurant Reservations'" -ForegroundColor White
}

