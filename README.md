# 🍽️ FP Restaurant Reservations

**Sistema completo di prenotazioni per ristoranti con calendario drag&drop, integrazioni Brevo + Google Calendar, tracking avanzato e stile personalizzabile.**

---

## 📊 INFO PLUGIN

| Dettaglio | Valore |
|-----------|--------|
| **Versione** | 1.3.6 |
| **Status** | Stable ✅ |
| **Richiede WordPress** | 6.5+ |
| **Richiede PHP** | 8.1+ |
| **Licenza** | GPL-2.0-or-later |
| **Autore** | Francesco Passeri |

---

## ✨ CARATTERISTICHE PRINCIPALI

### 🎯 Core Features

- ✅ **Sistema prenotazioni completo** con calendario interattivo
- ✅ **Manager Agenda** drag & drop per gestione prenotazioni (modale **Calendario operativo** per chiusure, aperture speciali e riduzioni capienza)
- ✅ **Meal Plans** configurabili (pranzo, cena, eventi)
- ✅ **Sale & Tavoli** con layout personalizzabile
- ✅ **Chiusure programmate** (singole/ricorrenti)
- ✅ **Report & Analytics** dettagliati

### 🔗 Integrazioni

- ✅ **Brevo (Sendinblue)** - Email marketing automation
- ✅ **Google Calendar** - Sincronizzazione automatica
- ✅ **Stripe** - Pagamenti online sicuri
- ✅ **Tracking avanzato** - GA4, Google Ads, Meta Pixel, Clarity

### 🎨 Frontend

- ✅ **Form personalizzabile** con stile TheFork-like
- ✅ **Calendario giorni disponibili** per meal
- ✅ **Slot orari dinamici** in base a disponibilità reale
- ✅ **Responsive** - mobile-first design
- ✅ **Multi-lingua** (IT, EN, FR, ES, DE)

---

## 🚀 QUICK START

### Per Utenti

> ⚠️ **IMPORTANTE:** Se installi il plugin da GitHub o da sorgente, devi prima eseguire `composer install` nella directory del plugin per installare le dipendenze necessarie. Se vedi un errore "Autoloader Composer mancante", segui le istruzioni mostrate nell'errore.

1. **Installa** il plugin (o esegui `composer install` se installi da sorgente)
2. **Configura** orari di servizio
3. **Inserisci** shortcode `[fp_reservations]` in una pagina
4. **Fatto!** Il form è pronto

👉 **Guida completa:** [docs/guides/user/QUICK-START.md](docs/guides/user/QUICK-START.md)

### Per Sviluppatori

```bash
# Clone repository
git clone https://github.com/franpass87/FP-Restaurant-Reservations.git

# Installa dipendenze
composer install --no-dev --prefer-dist
npm install

# Build assets
npm run build

# IMPORTANTE: Prima di fare commit/push, assicurati che vendor/ sia incluso
# Il plugin funziona con Git Updater solo se vendor/ è nel repository
git add vendor/
git commit -m "Include vendor dependencies"

# Test
php tools/quick-health-check.php
```

> **⚠️ IMPORTANTE per Git Updater:** Per funzionare con Git Updater, `vendor/` deve essere incluso nel repository Git. Dopo `composer install`, esegui `git add vendor/` e fai commit.

👉 **Guida completa:** [docs/guides/developer/README-BUILD.md](docs/guides/developer/README-BUILD.md)

---

## 📚 DOCUMENTAZIONE

### 📖 Indice Completo
👉 **[docs/INDEX.md](docs/INDEX.md)** - Naviga tutta la documentazione

### 📁 Struttura

```
docs/
├── INDEX.md                    ← Indice navigabile
├── README.md                   ← Panoramica docs
│
├── guides/
│   ├── user/                   ← Guide utente
│   └── developer/              ← Guide sviluppatore
│
├── api/                        ← REST API docs
├── bugfixes/                   ← Bugfix recenti
└── archive/                    ← Storico (157+ file)
```

### 🔗 Link Rapidi

| Documento | Descrizione |
|-----------|-------------|
| **[docs/INDEX.md](docs/INDEX.md)** | 📚 Indice completo |
| **[CHANGELOG.md](CHANGELOG.md)** | 📝 Changelog |
| **[docs/SLOT-TIMES-SYSTEM.md](docs/SLOT-TIMES-SYSTEM.md)** | 🕐 Sistema slot orari |
| **[docs/MEALS-CONFIGURATION.md](docs/MEALS-CONFIGURATION.md)** | 🍽️ Config pasti |
| **[docs/ROADMAP-1.0.md](docs/ROADMAP-1.0.md)** | 🗺️ Roadmap v1.0 |

---

## 🎯 STATO ATTUALE

### ✅ Production Ready!

```
╔═══════════════════════════════════════════╗
║  🏆 PLUGIN COMPLETO E TESTATO             ║
║                                           ║
║  Bug critici: 0                          ║
║  Sicurezza: ECCELLENTE                   ║
║  Performance: OTTIMIZZATA                ║
║  Code Quality: ALTA                      ║
║  Timezone: Europe/Rome ✓                 ║
║                                           ║
║  🚀 PRONTO PER LA PRODUZIONE              ║
╚═══════════════════════════════════════════╝
```

### 🆕 Ultimi Aggiornamenti (2 Nov 2025)

- ✅ **Bugfix completo** - 2 sessioni profonde (8 bug risolti)
- ✅ **Security audit** - Verificata sicurezza completa
- ✅ **Performance** - Ottimizzazioni applicate
- ✅ **Timezone** - Corretto ovunque (Europe/Rome)

Dettagli: [docs/bugfixes/2025-11-02/](docs/bugfixes/2025-11-02/)

---

## 🔧 REQUISITI

### Server
- **PHP:** 8.1 o superiore
- **WordPress:** 6.5 o superiore
- **MySQL:** 5.7+ o MariaDB 10.3+
- **Timezone:** Europe/Rome (consigliato)

### Opzionali
- **Composer** - Per sviluppo
- **Node.js** - Per build assets
- **WP-CLI** - Per gestione da terminale

---

## 🛠️ TOOLS INCLUSI

### Test & Diagnostica

```bash
# Test rapido (senza WordPress)
php tools/quick-health-check.php

# Test completo (con WordPress)
php tools/test-plugin-health.php

# Verifica slot orari
php tools/verify-slot-times.php

# Refresh cache
php tools/refresh-cache.php
```

---

## 📦 INSTALLAZIONE

### Da WordPress Admin

1. Vai su **Plugin → Aggiungi nuovo**
2. Carica `fp-restaurant-reservations.zip`
3. Clicca **Installa** poi **Attiva**
4. Configura in **Restaurant Manager → Impostazioni**

### Da Composer

```bash
composer require franpass87/fp-restaurant-reservations
```

### Da Git (Sviluppo)

```bash
git clone https://github.com/franpass87/FP-Restaurant-Reservations.git
cd FP-Restaurant-Reservations
composer install --no-dev
```

---

## 🎨 SHORTCODES

### Form Prenotazione Completo
```
[fp_reservations]
```

### Form Semplificato
```
[fp_reservations style="simple"]
```

### Debug (solo admin)
```
[fp_resv_debug]
```

---

## 🔌 INTEGRAZIONI

### Brevo (Email Marketing)
Configura API key in: **Restaurant Manager → Impostazioni → Brevo**

Documenti:
- [docs/BREVO-EMAIL-EVENTS.md](docs/BREVO-EMAIL-EVENTS.md)

### Google Calendar
Configura OAuth in: **Restaurant Manager → Impostazioni → Google Calendar**

### Stripe Payments
Configura chiavi in: **Restaurant Manager → Impostazioni → Pagamenti**

### Tracking
- **GA4** - Google Analytics 4
- **Google Ads** - Conversion tracking
- **Meta Pixel** - Facebook/Instagram tracking
- **Microsoft Clarity** - Session recording

Documenti: [docs/api/TRACKING-MAP.md](docs/api/TRACKING-MAP.md)

---

## 🐛 TROUBLESHOOTING

### Problemi Comuni

| Problema | Soluzione |
|----------|-----------|
| Slot orari sbagliati | [docs/SLOT-TIMES-SYSTEM.md](docs/SLOT-TIMES-SYSTEM.md) |
| Cache non aggiornata | [docs/guides/developer/CACHE-REFRESH-GUIDE.md](docs/guides/developer/CACHE-REFRESH-GUIDE.md) |
| Email non inviate | [docs/TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md](docs/TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md) |
| Mostrare dump "DEBUG MEALS" nel form | In `wp-config.php` aggiungi `define('FP_RESV_DEBUG_MEALS', true);` (richiede anche `WP_DEBUG`). |

### Health Check

```bash
cd wp-content/plugins/FP-Restaurant-Reservations
php tools/quick-health-check.php
```

---

## 🤝 CONTRIBUTING

Vuoi contribuire? Leggi: [CONTRIBUTING.md](CONTRIBUTING.md)

### Sviluppo

```bash
# Install dev dependencies
composer install
npm install

# Linting
composer lint
npm run lint:js

# Tests
composer test
npm test
```

---

## 📝 CHANGELOG

**Versioni recenti:**
- **1.0.1** - Debug MEALS opzionale (FP_RESV_DEBUG_MEALS), a11y calendario
- **1.0.0** - First stable release (Percorso A - 18 Mar 2026)
- **0.9.0-rc10.3** - Hotfix slot orari (mock → reali)
- **0.9.0-rc10** - Security & race conditions
- **0.9.0-rc9** - Bugfix calendario
- **0.9.0-rc8** - Calendario date ottimizzato
- **0.9.0-rc7** (draft) - Bugfix profondo + performance
- **0.9.0-rc6** - Fix timezone PHP (20 correzioni)
- **0.9.0-rc4** - Fix CSS header tema
- **0.9.0-rc3** - Asset loading ottimizzato
- **0.9.0-rc1** - Release Candidate

👉 **Changelog completo:** [CHANGELOG.md](CHANGELOG.md)

---

## 📄 LICENZA

GPL-2.0-or-later - Vedi [LICENSE](LICENSE)

---

## 👤 AUTORE

**Francesco Passeri**
- Website: [francescopasseri.com](https://francescopasseri.com)
- Email: info@francescopasseri.com
- GitHub: [@franpass87](https://github.com/franpass87)

---

## 🔗 LINK UTILI

- **Repository:** [GitHub](https://github.com/franpass87/FP-Restaurant-Reservations/)
- **Documentazione:** [docs/INDEX.md](docs/INDEX.md)
- **Issues:** [GitHub Issues](https://github.com/franpass87/FP-Restaurant-Reservations/issues)
- **Wiki:** [GitHub Wiki](https://github.com/franpass87/FP-Restaurant-Reservations/wiki)

---

## ⭐ SUPPORTA IL PROGETTO

Se questo plugin ti è utile:
- ⭐ Lascia una stella su GitHub
- 📝 Scrivi una recensione
- 🐛 Segnala bug
- 💡 Suggerisci miglioramenti

---

**Made with ❤️ in Italy** 🇮🇹

---

**Versione:** 1.3.1  
**Data:** 18 Aprile 2026
---

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
