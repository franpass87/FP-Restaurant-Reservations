# Asset Loading Strategy

## 🎯 Comportamento

A partire dalla versione **0.9.0-rc3**, il plugin carica i suoi asset (CSS e JavaScript) **solo** sulle pagine dove è effettivamente presente il form di prenotazione.

### Prima (0.9.0-rc2 e precedenti)
- ❌ Asset caricati su TUTTE le pagine del sito
- ❌ ~150KB di CSS/JS inutili su ogni pagina
- ❌ Possibili conflitti CSS con il tema (es. `#header-outer`)

### Dopo (0.9.0-rc3+)
- ✅ Asset caricati SOLO dove necessario
- ✅ Rilevamento automatico shortcode/block
- ✅ Nessun conflitto CSS con il tema
- ✅ Sito più veloce

---

## 🔍 Rilevamento Automatico

Il plugin rileva automaticamente se la pagina contiene il form:

### 1. **Shortcode nel contenuto**
```php
[fp_reservations]
```

### 2. **Gutenberg Block**
```
<!-- wp:fp-resv/reservations /-->
```

### 3. **WPBakery / Elementor**
Lo shortcode nei post meta (builder lo salvano in `_wpb_shortcodes_custom_css`, `_elementor_data`, ecc.)

---

## ⚙️ Override Manuale

Se hai bisogno di forzare il caricamento degli asset in situazioni particolari, usa il filtro:

### Forzare caricamento su una pagina specifica

```php
add_filter('fp_resv_frontend_should_enqueue', function($shouldEnqueue, $post) {
    // Forza caricamento sulla pagina con ID 123
    if ($post && $post->ID === 123) {
        return true;
    }
    
    return $shouldEnqueue;
}, 10, 2);
```

### Forzare caricamento su tutte le pagine (comportamento pre-0.9.0-rc3)

```php
add_filter('fp_resv_frontend_should_enqueue', function() {
    return true; // Carica sempre (non consigliato)
});
```

### Bloccare caricamento su pagine specifiche

```php
add_filter('fp_resv_frontend_should_enqueue', function($shouldEnqueue, $post) {
    // NON caricare sulla home
    if (is_front_page()) {
        return false;
    }
    
    return $shouldEnqueue;
}, 10, 2);
```

---

## 🐛 Troubleshooting

### Il form non si carica su una pagina

**Causa**: Il rilevamento automatico non ha trovato lo shortcode/block.

**Soluzione**:
1. Verifica che lo shortcode sia scritto correttamente: `[fp_reservations]`
2. Se usi un page builder, verifica che salvi correttamente il contenuto
3. Usa il filtro `fp_resv_frontend_should_enqueue` per forzare il caricamento

### Asset caricati su pagine dove non servono

**Causa**: Shortcode presente nel contenuto ma nascosto (es. in commenti, draft, ecc.)

**Soluzione**: Usa il filtro per escludere quelle pagine specifiche.

---

## 📊 Performance Impact

### Prima (caricamento globale)
```
Homepage: 2.3s (150KB plugin assets)
Blog: 2.1s (150KB plugin assets)  
Pagina prenotazioni: 2.4s (150KB plugin assets) ← UNICA dove servono
```

### Dopo (caricamento condizionale)
```
Homepage: 1.8s ← -500ms, -150KB
Blog: 1.6s ← -500ms, -150KB
Pagina prenotazioni: 2.4s ← stesso, asset caricati
```

**Risparmio medio**: ~150KB per pagina + ~500ms tempo caricamento

---

## 🔗 Vedi Anche

- [CHANGELOG.md](../CHANGELOG.md) - Note di versione complete
- [README.md](../README.md) - Documentazione generale

