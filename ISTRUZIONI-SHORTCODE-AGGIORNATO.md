# 🔧 Shortcode Non Funziona - Soluzione

## Problema
Lo shortcode `[fp_resv_debug]` non visualizza nulla sulla pagina.

## Soluzione Passo-Passo

### 1️⃣ RICARICA IL FILE AGGIORNATO

**IMPORTANTE**: Devi ricaricare il file `src/Frontend/Shortcodes.php` sul server via FTP/SFTP, sovrascrivendo quello vecchio.

Il nuovo file ha:
- ✅ Error logging avanzato
- ✅ Try-catch per catturare errori
- ✅ Shortcode di test semplice

---

### 2️⃣ PULISCI LA CACHE

Dopo aver caricato il file, **pulisci la cache**:

1. **Cache del plugin** (se usi WP Rocket, W3 Total Cache, ecc.)
2. **Cache di OPcache PHP** (chiedi al tuo hosting o riavvia PHP-FPM)
3. **Cache del browser** (CTRL+F5 sulla pagina)

---

### 3️⃣ TESTA CON LO SHORTCODE SEMPLICE

Prima di provare `[fp_resv_debug]`, testa con lo shortcode più semplice:

**Crea una nuova pagina** (o modifica quella esistente) con:

```
[fp_resv_test]
```

**Cosa aspettarsi:**
- Se vedi un **box blu** con "✅ Test Shortcode FP Restaurant Reservations" → **FUNZIONA!**
- Se **non vedi nulla** → Il file non è stato caricato o la cache non è stata pulita

---

### 4️⃣ SE IL TEST FUNZIONA, USA IL DEBUG

Se `[fp_resv_test]` funziona, allora prova con:

```
[fp_resv_debug]
```

Questo mostrerà il pannello diagnostico completo.

---

### 5️⃣ SE NON VEDI NULLA

Se anche `[fp_resv_test]` non mostra nulla:

#### A) Verifica che il file sia stato caricato
- Controlla la **data di modifica** del file sul server
- Deve essere **recente** (oggi)

#### B) Disattiva e riattiva il plugin
1. **Admin WordPress** → **Plugin**
2. **Disattiva** FP Restaurant Reservations
3. **Riattiva** il plugin
4. Prova di nuovo lo shortcode

#### C) Controlla i log PHP
Cerca errori nei log PHP del server. Chiedi al tuo hosting dove si trovano (di solito `/error_log` o `/logs/error.log`)

Cerca righe che contengono:
```
[FP-RESV-SHORTCODE]
[FP-RESV-TEST]
[FP-RESV-DEBUG]
```

---

### 6️⃣ COSA ASPETTARSI DAI LOG

Se tutto funziona, nei log PHP vedrai:

```
[FP-RESV-SHORTCODE] register() method called
[FP-RESV-SHORTCODE] add_shortcode("fp_reservations") executed
[FP-RESV-SHORTCODE] add_shortcode("fp_resv_debug") executed
[FP-RESV-SHORTCODE] add_shortcode("fp_resv_test") executed
[FP-RESV-TEST] Test shortcode called!
```

Se vedi questi messaggi nei log ma non vedi nulla sulla pagina:
- C'è un problema con il tema WordPress
- Il tema non esegue `the_content()` correttamente

---

## ⚡ Checklist Rapida

- [ ] File `src/Frontend/Shortcodes.php` caricato sul server
- [ ] Data modifica del file è recente (oggi)
- [ ] Cache pulita (plugin + PHP + browser)
- [ ] Plugin disattivato e riattivato
- [ ] Testato `[fp_resv_test]` prima di `[fp_resv_debug]`
- [ ] Controllati i log PHP per errori

---

## 🆘 Se Ancora Non Funziona

Fammi sapere:

1. **Vedi qualcosa con `[fp_resv_test]`?** (SÌ/NO)
2. **Hai pulito la cache?** (SÌ/NO)
3. **Hai disattivato/riattivato il plugin?** (SÌ/NO)
4. **Ci sono errori nei log PHP?** (copia gli errori)

Con queste informazioni posso dirti esattamente cosa sta succedendo!

---

## 📝 Alternative se lo Shortcode Non Funziona

Se proprio non riesci a far funzionare lo shortcode, possiamo:

1. **Creare una pagina admin custom** nel backend WordPress
2. **Usare WP-CLI** se hai accesso SSH
3. **Query diretta al database** via phpMyAdmin

Ma prima prova con lo shortcode di test `[fp_resv_test]`! 🚀

