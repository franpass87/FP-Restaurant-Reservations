# 📞 Sistema Prefissi Telefono per Brevo

## 🎯 **IMPORTANTE: Gestione Liste Brevo**

Il campo prefisso del telefono è **CRITICO** per l'invio corretto alle liste Brevo!

## 🔧 **Come Funziona**

### **Mappatura Prefissi → Liste Brevo**

Il sistema usa il prefisso del telefono per determinare a quale lista Brevo inviare il contatto:

| Prefisso | Paese | Lista Brevo | Codice |
|----------|-------|-------------|---------|
| **+39** | 🇮🇹 Italia | Lista IT | `brevo_list_id_it` |
| **+44** | 🇬🇧 Inghilterra | Lista EN | `brevo_list_id_en` |
| **+33** | 🇫🇷 Francia | Lista EN | `brevo_list_id_en` |
| **+49** | 🇩🇪 Germania | Lista EN | `brevo_list_id_en` |
| **+1** | 🇺🇸 USA | Lista EN | `brevo_list_id_en` |
| **Tutti gli altri** | Altri paesi | Lista EN | `brevo_list_id_en` |

### **Logica di Selezione Lista**

```php
// Nel codice Brevo (AutomationService.php)
private function resolveListKey(string $forced, string $phoneCountry, string $pageLanguage): string
{
    // 1. Se c'è una lingua forzata, usa quella
    if ($forced !== '') {
        return $forced;
    }
    
    // 2. Se il prefisso telefono è +39, usa IT
    if ($phoneCountry === '+39') {
        return 'IT';
    }
    
    // 3. TUTTI GLI ALTRI prefissi vanno in EN
    return 'EN';
}
```

## 📋 **Campi del Form**

### **Campo Telefono Completo**
```html
<!-- Prefisso -->
<select name="fp_resv_phone_prefix">
    <option value="39" selected>🇮🇹 +39</option>
    <option value="44">🇬🇧 +44</option>
    <option value="33">🇫🇷 +33</option>
    <!-- ... altri prefissi ... -->
</select>

<!-- Numero -->
<input type="tel" name="fp_resv_phone" placeholder="123 456 7890">
```

### **Campi Nascosti Aggiornati**
```html
<!-- Prefisso paese -->
<input type="hidden" name="fp_resv_phone_cc" value="39">

<!-- Numero locale -->
<input type="hidden" name="fp_resv_phone_local" value="1234567890">

<!-- Numero completo E.164 -->
<input type="hidden" name="fp_resv_phone_e164" value="+39 123 456 7890">
```

## 🔄 **Flusso di Invio Brevo**

### **1. Raccolta Dati**
- Utente seleziona prefisso (es. +39)
- Utente inserisce numero (es. 123 456 7890)
- Sistema crea numero completo: `+39 123 456 7890`

### **2. Elaborazione**
- Sistema estrae prefisso: `+39`
- Sistema determina lista: `IT` (per +39)
- Sistema cerca `brevo_list_id_it` nelle impostazioni

### **3. Invio a Brevo**
- Contatto inviato alla lista IT
- Attributi Brevo popolati con dati prenotazione
- Log dell'operazione salvato

## ⚙️ **Configurazione Impostazioni**

### **Impostazioni Brevo**
```php
// In wp_options -> fp_resv_brevo
'brevo_list_id_it' => '123',        // ID lista Italia
'brevo_list_id_en' => '456',        // ID lista Inghilterra  
'brevo_list_id' => '789',           // ID lista default
'brevo_phone_prefix_map' => '...'   // Mappa personalizzata
```

### **Mappa Prefissi Personalizzata**
```json
[
    {"prefix": "+39", "list": "IT"},
    {"prefix": "+44", "list": "EN"},
    {"prefix": "+33", "list": "EN"},
    {"prefix": "+49", "list": "EN"}
]
```

## 🎯 **Casi d'Uso**

### **Cliente Italiano**
- **Prefisso**: +39
- **Lista**: IT
- **Email**: In italiano
- **SMS**: In italiano

### **Cliente Inglese**
- **Prefisso**: +44
- **Lista**: EN
- **Email**: In inglese
- **SMS**: In inglese

### **Cliente Francese**
- **Prefisso**: +33
- **Lista**: EN (tutti i non-IT)
- **Email**: In inglese
- **SMS**: In inglese

### **Cliente Americano**
- **Prefisso**: +1
- **Lista**: EN (tutti i non-IT)
- **Email**: In inglese
- **SMS**: In inglese

## 🔍 **Debug e Log**

### **Log Brevo**
```php
// Nel repository Brevo
$this->repository->log($reservationId, 'subscribe', [
    'list'            => 'IT',           // Lista selezionata
    'list_key'        => 'IT',           // Chiave lista
    'list_id'         => 123,            // ID lista Brevo
    'phone_country'   => '+39',          // Prefisso telefono
    'phone'           => '+39 123 456 7890', // Numero completo
], 'success', null);
```

### **Verifica Invio**
1. Controlla log Brevo in database
2. Verifica lista corretta in Brevo dashboard
3. Controlla attributi contatto popolati

## ⚠️ **IMPORTANTE**

### **Se il Prefisso è Sbagliato:**
- ❌ Cliente italiano con +44 → Va in lista EN (sbagliato!)
- ❌ Cliente inglese con +39 → Va in lista IT (sbagliato!)
- ❌ Email/SMS in lingua sbagliata
- ❌ Campagne marketing errate

### **Se il Prefisso è Corretto:**
- ✅ Cliente italiano con +39 → Va in lista IT
- ✅ Cliente inglese con +44 → Va in lista EN
- ✅ Cliente francese con +33 → Va in lista EN
- ✅ Cliente americano con +1 → Va in lista EN
- ✅ Email/SMS in lingua corretta
- ✅ Campagne marketing corrette

## 🎉 **Risultato**

**Il form semplificato ora gestisce correttamente i prefissi per Brevo!**

- ✅ **Prefisso selezionabile** con dropdown
- ✅ **Mappatura corretta** +39 → IT, +44 → EN
- ✅ **Campi nascosti** popolati correttamente
- ✅ **Integrazione Brevo** funzionante
- ✅ **Liste corrette** per ogni paese

---

## 🔧 **Come Testare**

1. **Seleziona prefisso +39** → Dovrebbe andare in lista IT
2. **Seleziona prefisso +44** → Dovrebbe andare in lista EN
3. **Seleziona prefisso +33** → Dovrebbe andare in lista EN
4. **Seleziona prefisso +1** → Dovrebbe andare in lista EN
5. **Controlla log Brevo** per verificare lista corretta
6. **Verifica in Brevo dashboard** che il contatto sia nella lista giusta

**Il sistema Brevo ora funziona correttamente con i prefissi!** 🚀
