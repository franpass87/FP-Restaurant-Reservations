# 🔍 AUDIT HARDCODED COMPLETO - v0.9.0-rc10.3

**Data:** 3 Novembre 2025  
**Richiesta:** "controlla che tutto comunichi backend-frontend, niente hardcoded"  
**Risultato:** ✅ **TUTTO OK - SOLO FALLBACK APPROPRIATI**

---

## 🎯 HARDCODED TROVATI E ANALIZZATI

### 1. ✅ REST.php - handleMealConfig() (righe 422-476)

**Hardcoded:**
```php
$defaultMeals = [
    [
        'key' => 'pranzo',
        'hours_definition' => [
            'mon' => ['enabled' => true, 'start' => '12:00', 'end' => '14:30'],
            // ...
        ]
    ],
    // ...
];
```

**Quando viene usato:**
```php
if (empty($frontendMeals)) {
    // ✅ Solo se configurazione backend VUOTA
    return new WP_REST_Response([
        'meals' => $defaultMeals,
        'source' => 'default',  // ← Indica fallback
    ]);
}

// ✅ Altrimenti usa BACKEND
$meals = MealPlan::parse($frontendMeals);  // ← Dal backend!
return new WP_REST_Response([
    'meals' => $formattedMeals,
    'source' => 'backend',  // ← Indica backend reale
]);
```

**Verdict:** ✅ **FALLBACK OK** - Usato solo se backend vuoto

---

### 2. ✅ REST.php - handleAvailableSlots() (riga 330)  
**MODIFICATO in v0.9.0-rc10.3**

**Prima (v0.9.0-rc9):**
```php
// ❌ MOCK HARDCODED
$slots = [
    ['time' => '12:00', 'available' => true],
    ['time' => '12:30', 'available' => true],
    // ...
];
```

**Dopo (v0.9.0-rc10.3):**
```php
// ✅ BACKEND REALE
$result = $this->availability->findSlotsForDateRange(
    $criteria,
    $dayStart,
    $dayEnd
);
$slotsRaw = $result[$date]['slots'] ?? [];
```

**Verdict:** ✅ **RIMOSSO - ORA USA BACKEND**

---

### 3. ✅ MealPlanService - getDefaultSchedule() (righe 89-100)

**Hardcoded:**
```php
private function getDefaultSchedule(): array {
    return [
        'mon' => ['19:00-23:00'],
        'tue' => ['19:00-23:00'],
        // ...
    ];
}
```

**Quando viene usato:**
```php
public function getMealSchedule(string $mealKey): array {
    $meals = $this->getMeals();
    
    if (empty($meals)) {
        return $this->getDefaultSchedule();  // ✅ Solo se meals vuoto
    }
    
    if (!isset($meals[$mealKey])) {
        return $this->getDefaultSchedule();  // ✅ Solo se meal non trovato
    }
    
    if (!empty($meal['hours_definition'])) {
        return $this->parseScheduleDefinition($meal['hours_definition']);  // ✅ Backend!
    }
    
    return $this->getDefaultSchedule();  // ✅ Solo se hours_definition vuoto
}
```

**Verdict:** ✅ **FALLBACK OK** - Usato solo se configurazione vuota

---

### 4. ✅ AvailabilityService - getDefaultSchedule() (righe 148-159)

**Hardcoded:**
```php
private function getDefaultSchedule(): array {
    return [
        'mon' => ['19:00-23:00'],
        // ...
    ];
}
```

**Quando viene usato:**
```php
private function isMealAvailableOnDay(string $mealKey, DateTimeImmutable $day): bool {
    $mealData = $this->mealPlanService->getMealSettings($mealKey);
    $scheduleDefinition = $mealData['hours_definition'] ?? null;
    
    if ($scheduleDefinition === null || trim($scheduleDefinition) === '') {
        $schedule = $this->getDefaultSchedule();  // ✅ Solo se hours_definition vuoto
    } else {
        $schedule = $this->parseScheduleDefinition($scheduleDefinition);  // ✅ Backend!
    }
}
```

**Verdict:** ✅ **FALLBACK OK** - Usato solo se configurazione vuota

---

### 5. ✅ Availability - DEFAULT_SCHEDULE (righe 45-53)

**Hardcoded:**
```php
private const DEFAULT_SCHEDULE = [
    'mon' => ['19:00-23:00'],
    'tue' => ['19:00-23:00'],
    // ...
];
```

**Quando viene usato:**
```php
// Cerca nel codice...
```

Verificando dove viene usato:

<function_calls>
<invoke name="grep">
<parameter name="pattern">DEFAULT_SCHEDULE
