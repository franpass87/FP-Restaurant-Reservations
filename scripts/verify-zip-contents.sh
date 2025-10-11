#!/usr/bin/env bash
# Script per verificare il contenuto del file ZIP del plugin
# Controlla che tutti i file necessari siano presenti e che i file indesiderati siano esclusi

set -euo pipefail

ZIP_FILE="${1:-}"

if [[ -z "$ZIP_FILE" ]]; then
    echo "Uso: $0 <file.zip>"
    exit 1
fi

if [[ ! -f "$ZIP_FILE" ]]; then
    echo "Errore: File ZIP non trovato: $ZIP_FILE"
    exit 1
fi

echo "🔍 Verifica contenuto di: $ZIP_FILE"
echo "========================================"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Funzione per verificare la presenza di un file
check_file_present() {
    local pattern=$1
    local description=$2
    
    if unzip -l "$ZIP_FILE" | grep -q "$pattern"; then
        echo -e "${GREEN}✓${NC} $description"
        return 0
    else
        echo -e "${RED}✗${NC} $description"
        ((ERRORS++))
        return 1
    fi
}

# Funzione per verificare l'assenza di un file
check_file_absent() {
    local pattern=$1
    local description=$2
    
    if unzip -l "$ZIP_FILE" | grep -q "$pattern"; then
        echo -e "${RED}✗${NC} $description (TROVATO, dovrebbe essere assente!)"
        ((ERRORS++))
        return 1
    else
        echo -e "${GREEN}✓${NC} $description"
        return 0
    fi
}

# Funzione per avviso
warn_if_present() {
    local pattern=$1
    local description=$2
    
    if unzip -l "$ZIP_FILE" | grep -q "$pattern"; then
        echo -e "${YELLOW}⚠${NC} $description (presente, valutare se necessario)"
        ((WARNINGS++))
        return 1
    else
        echo -e "${GREEN}✓${NC} $description"
        return 0
    fi
}

echo ""
echo "📦 File OBBLIGATORI (devono essere presenti):"
echo "--------------------------------------"
check_file_present "fp-restaurant-reservations\.php" "File principale plugin"
check_file_present "readme\.txt" "README WordPress"
check_file_present "LICENSE" "Licenza"
check_file_present "composer\.json" "Composer config"
check_file_present "src/" "Directory sorgenti PHP"
check_file_present "templates/" "Directory template"
check_file_present "assets/dist/fe/onepage\.iife\.js" "JS compilato (IIFE)"
check_file_present "assets/dist/fe/onepage\.esm\.js" "JS compilato (ESM)"
check_file_present "assets/css/" "Fogli di stile CSS"
check_file_present "assets/vendor/" "Librerie vendor"
check_file_present "vendor/" "Dipendenze Composer"
check_file_present "languages/" "File traduzione"

echo ""
echo "🚫 File che DEVONO essere ESCLUSI:"
echo "--------------------------------------"
check_file_absent "\.git/" "Directory .git"
check_file_absent "\.github/" "Directory .github"
check_file_absent "/tests/" "Directory tests"
check_file_absent "/docs/" "Directory docs"
check_file_absent "node_modules/" "Directory node_modules"
check_file_absent "/scripts/" "Directory scripts"
check_file_absent "/tools/" "Directory tools"
check_file_absent "AUDIT/" "Directory AUDIT"
check_file_absent "assets/js/fe/" "Sorgenti frontend (non compilati)"
check_file_absent "\.md$" "File Markdown"
check_file_absent "docker-compose\.yml" "Docker compose"
check_file_absent "vite\.config\.js" "Vite config"
check_file_absent "eslint\.config\.js" "ESLint config"
check_file_absent "phpcs\.xml" "PHPCS config"
check_file_absent "phpstan\.neon" "PHPStan config"
check_file_absent "package\.json" "Package.json"
check_file_absent "package-lock\.json" "Package-lock.json"
check_file_absent "build\.sh" "Build script"
check_file_absent "\.gitignore" ".gitignore"
check_file_absent "\.gitattributes" ".gitattributes"

echo ""
echo "⚠️  File da VALUTARE:"
echo "--------------------------------------"
warn_if_present "\.map$" "Source maps"
warn_if_present "test-" "File di test"

echo ""
echo "========================================"
echo "📊 RIEPILOGO:"
echo "--------------------------------------"

TOTAL_FILES=$(unzip -l "$ZIP_FILE" | tail -1 | awk '{print $2}')
echo "File totali nello ZIP: $TOTAL_FILES"

if [[ $ERRORS -eq 0 ]]; then
    echo -e "${GREEN}✅ Tutti i controlli critici passati!${NC}"
else
    echo -e "${RED}❌ $ERRORS errori trovati${NC}"
fi

if [[ $WARNINGS -gt 0 ]]; then
    echo -e "${YELLOW}⚠️  $WARNINGS avvisi${NC}"
fi

echo ""
echo "📋 Lista completa file:"
echo "--------------------------------------"
unzip -l "$ZIP_FILE"

exit $ERRORS
