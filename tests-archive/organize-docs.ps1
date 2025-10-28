# Script per organizzare la documentazione del plugin
# Eseguire dalla cartella LAB: C:\Users\franc\OneDrive\Desktop\FP-Restaurant-Reservations

$ErrorActionPreference = "Continue"

Write-Host "🧹 Organizzazione Documentazione Plugin" -ForegroundColor Cyan
Write-Host "=" * 60

# 1. SPOSTA FILE FIX/DEBUG IN ARCHIVE
Write-Host "`n1️⃣ Spostamento Fix & Debug in Archive..." -ForegroundColor Yellow

$fixFiles = Get-ChildItem -Filter "FIX-*.md"
$debugFiles = Get-ChildItem -Filter "DEBUG-*.md"
$diagnosiFiles = Get-ChildItem -Filter "DIAGNOSI-*.md"
$verificaFiles = Get-ChildItem -Filter "VERIFICA-*.md"
$risoluzione = Get-ChildItem -Filter "RISOLUZIONE-*.md"
$bugFiles = Get-ChildItem -Filter "BUG-*.md"
$bugfixFiles = Get-ChildItem -Filter "BUGFIX-*.md"

$allFixes = $fixFiles + $debugFiles + $diagnosiFiles + $verificaFiles + $risoluzione + $bugFiles + $bugfixFiles

Write-Host "Trovati $($allFixes.Count) file fix/debug/verifica"

foreach ($file in $allFixes) {
    Move-Item $file.FullName "docs\archive\fixes-2025\" -Force -ErrorAction SilentlyContinue
    if ($?) { Write-Host "  ✓ $($file.Name)" -ForegroundColor Green }
}

# 2. SPOSTA VECCHI CHANGELOG/SUMMARY
Write-Host "`n2️⃣ Spostamento Changelog & Summary..." -ForegroundColor Yellow

$summaryFiles = Get-ChildItem -Filter "*SUMMARY*.md"
$riepiloghi = Get-ChildItem -Filter "RIEPILOGO-*.md"
$technical = Get-ChildItem -Filter "TECHNICAL-*.md"

$allSummaries = $summaryFiles + $riepiloghi + $technical

Write-Host "Trovati $($allSummaries.Count) file summary/riepilogo"

foreach ($file in $allSummaries) {
    Move-Item $file.FullName "docs\archive\fixes-2025\" -Force -ErrorAction SilentlyContinue
    if ($?) { Write-Host "  ✓ $($file.Name)" -ForegroundColor Green }
}

# 3. SPOSTA GUIDE SPECIFICHE
Write-Host "`n3️⃣ Spostamento Guide Sviluppo..." -ForegroundColor Yellow

$devFiles = @(
    "FORM-ARCHITECTURE.md",
    "FORM-DEPENDENCIES-MAP.md",
    "GERARCHIA-CAPACITA-SPIEGAZIONE.md",
    "NUOVA-STRUTTURA-CSS-MODULARE.md",
    "CHECKLIST-RISTRUTTURAZIONE.md"
)

foreach ($fileName in $devFiles) {
    if (Test-Path $fileName) {
        Move-Item $fileName "docs\development\" -Force -ErrorAction SilentlyContinue
        if ($?) { Write-Host "  ✓ $fileName" -ForegroundColor Green }
    }
}

# 4. SPOSTA FILE TEST
Write-Host "`n4️⃣ Spostamento File Test..." -ForegroundColor Yellow

$testPhp = Get-ChildItem -Filter "test-*.php"
$testHtml = Get-ChildItem -Filter "test-*.html"
$testMd = Get-ChildItem -Filter "TEST-*.md"

$allTests = $testPhp + $testHtml + $testMd

Write-Host "Trovati $($allTests.Count) file di test"

foreach ($file in $allTests) {
    Move-Item $file.FullName "tests-archive\" -Force -ErrorAction SilentlyContinue
    if ($?) { Write-Host "  ✓ $($file.Name)" -ForegroundColor Green }
}

# 5. SPOSTA ALTRI FILE DEBUG
Write-Host "`n5️⃣ Spostamento File Debug..." -ForegroundColor Yellow

$debugPhp = Get-ChildItem -Filter "debug-*.php"
$diagnosePhp = Get-ChildItem -Filter "diagnose-*.php"
$diagnosticaPhp = Get-ChildItem -Filter "DIAGNOSTICA-*.php"
$checkPhp = Get-ChildItem -Filter "check-*.php"
$forcePhp = Get-ChildItem -Filter "force-*.php"

$allDebugPhp = $debugPhp + $diagnosePhp + $diagnosticaPhp + $checkPhp + $forcePhp

Write-Host "Trovati $($allDebugPhp.Count) file PHP di debug"

foreach ($file in $allDebugPhp) {
    Move-Item $file.FullName "tests-archive\" -Force -ErrorAction SilentlyContinue
    if ($?) { Write-Host "  ✓ $($file.Name)" -ForegroundColor Green }
}

# 6. SPOSTA FILE HTML/SQL DI TEST
Write-Host "`n6️⃣ Spostamento File Test HTML/SQL..." -ForegroundColor Yellow

$debugHtml = Get-ChildItem -Filter "*debug*.html" -ErrorAction SilentlyContinue
$testSql = Get-ChildItem -Filter "*.sql" -ErrorAction SilentlyContinue

foreach ($file in ($debugHtml + $testSql)) {
    Move-Item $file.FullName "tests-archive\" -Force -ErrorAction SilentlyContinue
    if ($?) { Write-Host "  ✓ $($file.Name)" -ForegroundColor Green }
}

# 7. SPOSTA VECCHI README/GUIDE OBSOLETI
Write-Host "`n7️⃣ Spostamento README Obsoleti..." -ForegroundColor Yellow

$oldReadmes = @(
    "README-GRAFICA-THEFORK-SISTEMATA.md",
    "THEFORK-STYLE-README.md",
    "AGENDA_DEBUG_README.md",
    "✅-FIX-APPLICATO-README.md"
)

foreach ($fileName in $oldReadmes) {
    if (Test-Path $fileName) {
        Move-Item $fileName "docs\archive\fixes-2025\" -Force -ErrorAction SilentlyContinue
        if ($?) { Write-Host "  ✓ $fileName" -ForegroundColor Green }
    }
}

# 8. SPOSTA FILE OBSOLETI DI SVILUPPO
Write-Host "`n8️⃣ Spostamento File Obsoleti..." -ForegroundColor Yellow

$obsoleteFiles = @(
    "available-days-endpoint.php",
    "available-days-standalone.php",
    "create-viewer-user.php",
    "verify-shortcode-in-page.php",
    "validate-thefork-installation.js",
    "force-plugin-reload.ps1"
)

foreach ($fileName in $obsoleteFiles) {
    if (Test-Path $fileName) {
        Move-Item $fileName "tests-archive\" -Force -ErrorAction SilentlyContinue
        if ($?) { Write-Host "  ✓ $fileName" -ForegroundColor Green }
    }
}

Write-Host "`n✅ ORGANIZZAZIONE COMPLETATA!" -ForegroundColor Green
Write-Host "=" * 60

# Riepilogo
Write-Host "`n📊 Struttura Finale:" -ForegroundColor Cyan
Write-Host "  docs/archive/fixes-2025/  → Vecchi fix e debug"
Write-Host "  docs/development/         → Guide per sviluppatori"
Write-Host "  docs/user-guide/          → Guide per utenti"
Write-Host "  tests-archive/            → File di test obsoleti"
Write-Host ""

