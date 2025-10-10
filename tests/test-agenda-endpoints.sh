#!/bin/bash

# Script di test per i nuovi endpoint Agenda API
# Uso: ./tests/test-agenda-endpoints.sh [BASE_URL]

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configurazione
BASE_URL="${1:-http://localhost:8080}"
API_BASE="${BASE_URL}/wp-json/fp-resv/v1"
TODAY=$(date +%Y-%m-%d)

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Test Endpoint Agenda API${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""
echo "Base URL: $BASE_URL"
echo "API Base: $API_BASE"
echo "Data: $TODAY"
echo ""

# Contatore test
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Funzione per testare un endpoint
test_endpoint() {
    local name="$1"
    local endpoint="$2"
    local expected_keys="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "${YELLOW}Test $TOTAL_TESTS: $name${NC}"
    echo "Endpoint: $endpoint"
    
    # Esegui richiesta
    response=$(curl -s -w "\n%{http_code}" "$API_BASE$endpoint")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    # Verifica status code
    if [ "$http_code" != "200" ]; then
        echo -e "${RED}✗ FALLITO - HTTP Status: $http_code${NC}"
        echo "Response: $body"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo ""
        return 1
    fi
    
    # Verifica JSON valido
    if ! echo "$body" | jq empty 2>/dev/null; then
        echo -e "${RED}✗ FALLITO - Risposta non è JSON valido${NC}"
        echo "Response: $body"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo ""
        return 1
    fi
    
    # Verifica chiavi attese
    all_keys_present=true
    for key in $expected_keys; do
        if ! echo "$body" | jq -e ".$key" >/dev/null 2>&1; then
            echo -e "${RED}✗ FALLITO - Chiave mancante: $key${NC}"
            all_keys_present=false
        fi
    done
    
    if [ "$all_keys_present" = false ]; then
        echo "Response: $body"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo ""
        return 1
    fi
    
    echo -e "${GREEN}✓ PASSATO${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
    
    # Mostra snippet risposta
    echo "Response (primi 200 caratteri):"
    echo "$body" | jq -c | cut -c1-200
    echo ""
}

# Test 1: GET /agenda (vista giornaliera)
test_endpoint \
    "Agenda - Vista Giornaliera" \
    "/agenda?date=$TODAY&range=day" \
    "meta stats data reservations"

# Test 2: GET /agenda (vista settimanale)
test_endpoint \
    "Agenda - Vista Settimanale" \
    "/agenda?date=$TODAY&range=week" \
    "meta stats data reservations"

# Test 3: GET /agenda (vista mensile)
test_endpoint \
    "Agenda - Vista Mensile" \
    "/agenda?date=$TODAY&range=month" \
    "meta stats data reservations"

# Test 4: GET /agenda/stats (giornaliero)
test_endpoint \
    "Statistiche - Giorno" \
    "/agenda/stats?date=$TODAY&range=day" \
    "range stats"

# Test 5: GET /agenda/stats (settimanale)
test_endpoint \
    "Statistiche - Settimana" \
    "/agenda/stats?date=$TODAY&range=week" \
    "range stats"

# Test 6: GET /agenda/stats (mensile)
test_endpoint \
    "Statistiche - Mese" \
    "/agenda/stats?date=$TODAY&range=month" \
    "range stats"

# Test 7: GET /agenda/overview
test_endpoint \
    "Overview Dashboard" \
    "/agenda/overview" \
    "today week month trends"

# Test 8: Verifica struttura data.slots (vista day)
echo -e "${YELLOW}Test 8: Verifica struttura slots${NC}"
response=$(curl -s "$API_BASE/agenda?date=$TODAY&range=day")
if echo "$response" | jq -e '.data.slots' >/dev/null 2>&1; then
    echo -e "${GREEN}✓ PASSATO - data.slots presente${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FALLITO - data.slots mancante${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))
echo ""

# Test 9: Verifica struttura data.days (vista week)
echo -e "${YELLOW}Test 9: Verifica struttura days${NC}"
response=$(curl -s "$API_BASE/agenda?date=$TODAY&range=week")
if echo "$response" | jq -e '.data.days' >/dev/null 2>&1; then
    day_count=$(echo "$response" | jq '.data.days | length')
    if [ "$day_count" -eq 7 ]; then
        echo -e "${GREEN}✓ PASSATO - data.days contiene 7 giorni${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FALLITO - data.days contiene $day_count giorni invece di 7${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
else
    echo -e "${RED}✗ FALLITO - data.days mancante${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))
echo ""

# Test 10: Verifica stats.by_status
echo -e "${YELLOW}Test 10: Verifica struttura by_status${NC}"
response=$(curl -s "$API_BASE/agenda?date=$TODAY&range=day")
if echo "$response" | jq -e '.stats.by_status' >/dev/null 2>&1; then
    required_statuses="pending confirmed visited no_show cancelled"
    all_present=true
    for status in $required_statuses; do
        if ! echo "$response" | jq -e ".stats.by_status.$status" >/dev/null 2>&1; then
            echo -e "${RED}  Manca stato: $status${NC}"
            all_present=false
        fi
    done
    
    if [ "$all_present" = true ]; then
        echo -e "${GREEN}✓ PASSATO - Tutti gli stati presenti${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗ FALLITO - Stati mancanti${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
else
    echo -e "${RED}✗ FALLITO - stats.by_status mancante${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))
echo ""

# Riepilogo
echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Riepilogo Test${NC}"
echo -e "${YELLOW}========================================${NC}"
echo "Totale Test: $TOTAL_TESTS"
echo -e "${GREEN}Passati: $PASSED_TESTS${NC}"
echo -e "${RED}Falliti: $FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ Tutti i test sono passati!${NC}"
    exit 0
else
    echo -e "${RED}✗ Alcuni test sono falliti${NC}"
    exit 1
fi
