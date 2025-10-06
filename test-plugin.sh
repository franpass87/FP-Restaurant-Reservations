#!/bin/bash
# Test automatico per verificare configurazione build
# Eseguire prima di creare il build di produzione

set -e

# Colori per output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          TEST CONFIGURAZIONE BUILD PLUGIN                    ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

test_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $2"
        ((FAILED++))
        return 1
    fi
}

# Test 1: File JavaScript compilati
echo -e "${YELLOW}▶ Test 1: File JavaScript compilati${NC}"
[ -f "assets/dist/fe/onepage.iife.js" ]
test_result $? "onepage.iife.js exists"

[ -f "assets/dist/fe/onepage.esm.js" ]
test_result $? "onepage.esm.js exists"

[ -s "assets/dist/fe/onepage.iife.js" ]
test_result $? "onepage.iife.js is not empty"

SIZE_IIFE=$(wc -c < assets/dist/fe/onepage.iife.js 2>/dev/null || echo 0)
[ $SIZE_IIFE -gt 10000 ]
test_result $? "onepage.iife.js has reasonable size (${SIZE_IIFE} bytes)"

echo ""

# Test 2: Contenuto bundle
echo -e "${YELLOW}▶ Test 2: Contenuto bundle${NC}"
grep -q "FPResv" assets/dist/fe/onepage.iife.js 2>/dev/null
test_result $? "Bundle contains 'FPResv' global"

grep -q "FormApp" assets/dist/fe/onepage.iife.js 2>/dev/null
test_result $? "Bundle contains 'FormApp' class"

grep -q "window.FPResv" assets/dist/fe/onepage.iife.js 2>/dev/null
test_result $? "Bundle exports to window.FPResv"

echo ""

# Test 3: Configurazione build.sh
echo -e "${YELLOW}▶ Test 3: Configurazione build.sh${NC}"
[ -f "build.sh" ]
test_result $? "build.sh exists"

bash -n build.sh 2>/dev/null
test_result $? "build.sh has valid syntax"

grep -q "npm install" build.sh 2>/dev/null
test_result $? "build.sh runs npm install"

grep -q "npm run build" build.sh 2>/dev/null
test_result $? "build.sh runs npm run build"

grep -q "exclude=assets/js/fe" build.sh 2>/dev/null
test_result $? "build.sh excludes frontend sources"

echo ""

# Test 4: File configurazione
echo -e "${YELLOW}▶ Test 4: File configurazione${NC}"
[ -f "vite.config.js" ]
test_result $? "vite.config.js exists"

[ -f "package.json" ]
test_result $? "package.json exists"

grep -q '"build"' package.json 2>/dev/null
test_result $? "package.json has build script"

echo ""

# Test 5: WidgetController.php
echo -e "${YELLOW}▶ Test 5: WidgetController configurato correttamente${NC}"
[ -f "src/Frontend/WidgetController.php" ]
test_result $? "WidgetController.php exists"

grep -q "assets/dist/fe/onepage.iife.js" src/Frontend/WidgetController.php 2>/dev/null
test_result $? "WidgetController loads compiled IIFE file"

grep -q "file_exists" src/Frontend/WidgetController.php 2>/dev/null
test_result $? "WidgetController checks file existence"

echo ""

# Test 6: Dipendenze
echo -e "${YELLOW}▶ Test 6: Dipendenze installate${NC}"
command -v npm >/dev/null 2>&1
test_result $? "npm is installed"

if command -v npm >/dev/null 2>&1; then
    npm list vite >/dev/null 2>&1
    test_result $? "vite is installed"
fi

[ -d "node_modules" ]
test_result $? "node_modules directory exists"

echo ""

# Riepilogo
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}║  ✓ TUTTI I TEST PASSATI (${PASSED}/${PASSED})${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${GREEN}🎉 Il plugin è pronto per il build!${NC}"
    echo ""
    echo "Esegui: ${YELLOW}./build.sh${NC} per creare il pacchetto ZIP"
    exit 0
else
    echo -e "${RED}║  ✗ ALCUNI TEST FALLITI (${PASSED}/$((PASSED + FAILED)))${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}⚠️  Risolvere i problemi prima di procedere con il build${NC}"
    echo ""
    echo "Possibili soluzioni:"
    echo "1. Eseguire: ${YELLOW}npm install${NC}"
    echo "2. Eseguire: ${YELLOW}npm run build${NC}"
    echo "3. Verificare configurazione in vite.config.js"
    exit 1
fi
