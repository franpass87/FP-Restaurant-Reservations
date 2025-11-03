# 🧭 NAVIGAZIONE RAPIDA - Restaurant Manager

**Trova subito quello che cerchi!**

---

## 🎯 HO BISOGNO DI...

### 📖 "Voglio iniziare a usare il plugin"
👉 **[guides/user/QUICK-START.md](guides/user/QUICK-START.md)**

### 🔧 "Devo configurare i pasti"
👉 **[MEALS-CONFIGURATION.md](MEALS-CONFIGURATION.md)**

### 🕐 "Ho problemi con gli slot orari"
👉 **[SLOT-TIMES-SYSTEM.md](SLOT-TIMES-SYSTEM.md)**

### 👨‍💻 "Voglio fare il build"
👉 **[guides/developer/README-BUILD.md](guides/developer/README-BUILD.md)**

### 🌐 "Come funzionano le API?"
👉 **[api/API-AGENDA-BACKEND.md](api/API-AGENDA-BACKEND.md)**

### 🐛 "Cosa è stato fixato oggi?"
👉 **[bugfixes/2025-11-02/SESSIONE-BUGFIX-COMPLETA-2025-11-02.md](bugfixes/2025-11-02/SESSIONE-BUGFIX-COMPLETA-2025-11-02.md)**

### 🔍 "Voglio vedere tutto l'indice"
👉 **[INDEX.md](INDEX.md)** ⭐

---

## 🚨 TROUBLESHOOTING

### "Gli slot orari sono sbagliati"
1. Verifica timezone WP: Europe/Rome
2. Leggi: [SLOT-TIMES-SYSTEM.md](SLOT-TIMES-SYSTEM.md)
3. Esegui: `php ../tools/verify-slot-times.php`

### "La cache non si aggiorna"
1. Leggi: [guides/developer/CACHE-REFRESH-GUIDE.md](guides/developer/CACHE-REFRESH-GUIDE.md)
2. Esegui: `php ../tools/refresh-cache.php`

### "Il plugin non funziona"
1. Test: `php ../tools/quick-health-check.php`
2. Verifica: Composer installato, PHP 8.1+
3. Log: Controlla debug.log (con WP_DEBUG)

### "Email non arrivano"
1. Leggi: [TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md](TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md)
2. Verifica: Configurazione Brevo/SMTP

---

## 📱 QUICK LINKS

### Top 10 Documenti Più Utili

1. **[INDEX.md](INDEX.md)** ⭐ - Indice completo
2. **[guides/user/QUICK-START.md](guides/user/QUICK-START.md)** - Inizio rapido
3. **[SLOT-TIMES-SYSTEM.md](SLOT-TIMES-SYSTEM.md)** - Sistema slot
4. **[MEALS-CONFIGURATION.md](MEALS-CONFIGURATION.md)** - Config pasti
5. **[../CHANGELOG.md](../CHANGELOG.md)** - Novità versioni
6. **[SECURITY-REPORT.md](SECURITY-REPORT.md)** - Sicurezza
7. **[api/API-AGENDA-BACKEND.md](api/API-AGENDA-BACKEND.md)** - API
8. **[guides/developer/CACHE-GUIDE.md](guides/developer/CACHE-GUIDE.md)** - Cache
9. **[ROADMAP-1.0.md](ROADMAP-1.0.md)** - Roadmap
10. **[TEST-SCENARIOS.md](TEST-SCENARIOS.md)** - Testing

---

## 🎓 PER RUOLO

### 👤 Utente Finale
```
README.md → QUICK-START.md → MEALS-CONFIGURATION.md
```

### 🧑‍💼 Restaurant Manager
```
QUICK-START.md → MEALS-CONFIGURATION.md → guides/user/STATUS.md
```

### 👨‍💻 Sviluppatore
```
INDEX.md → guides/developer/ → api/
```

### 🔧 DevOps
```
guides/developer/README-BUILD.md → CACHE-GUIDE.md
```

### 🐛 QA / Tester
```
TEST-SCENARIOS.md → bugfixes/2025-11-02/
```

---

## 🔍 RICERCA PER PAROLA CHIAVE

### Timezone
- [BUGFIX-TIMEZONE-PHP-2025-11-02.md](BUGFIX-TIMEZONE-PHP-2025-11-02.md)
- [FIX-TIMEZONE-ITALIA.md](FIX-TIMEZONE-ITALIA.md)
- [SLOT-TIMES-SYSTEM.md](SLOT-TIMES-SYSTEM.md)

### Cache
- [guides/developer/CACHE-GUIDE.md](guides/developer/CACHE-GUIDE.md)
- [guides/developer/CACHE-REFRESH-GUIDE.md](guides/developer/CACHE-REFRESH-GUIDE.md)
- [ASSET-LOADING.md](ASSET-LOADING.md)

### API
- [api/API-AGENDA-BACKEND.md](api/API-AGENDA-BACKEND.md)
- [api/TRACKING-MAP.md](api/TRACKING-MAP.md)
- [api/SERVER-SIDE-TRACKING.md](api/SERVER-SIDE-TRACKING.md)

### Sicurezza
- [SECURITY-REPORT.md](SECURITY-REPORT.md)
- [bugfixes/2025-11-02/BUGFIX-SESSION-2-2025-11-02.md](bugfixes/2025-11-02/BUGFIX-SESSION-2-2025-11-02.md)

### Testing
- [TEST-SCENARIOS.md](TEST-SCENARIOS.md)
- [CHECKLIST-TEST-1.0.md](CHECKLIST-TEST-1.0.md)
- [TEST-BUILD-CHECKLIST.md](TEST-BUILD-CHECKLIST.md)

### Email
- [BREVO-EMAIL-EVENTS.md](BREVO-EMAIL-EVENTS.md)
- [TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md](TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md)

---

## 💡 TIPS

### Per Esplorare
1. Apri `INDEX.md`
2. Scorri le categorie
3. Usa Ctrl+F per cercare

### Per Contribuire
1. Leggi `STRUTTURA-DOCUMENTAZIONE.md`
2. Segui le convenzioni
3. Aggiorna sempre INDEX.md

### Per Segnalare Problemi
1. Cerca se già documentato
2. Controlla bugfixes/ e archive/
3. Apri issue su GitHub

---

**Buona navigazione!** 🚀

---

**Creato:** 2 Novembre 2025  
**Versione:** 1.0

