# 📚 Indice Documentazione - FP Restaurant Reservations

**Versione:** 0.1.12  
**Ultimo aggiornamento:** 25 Ottobre 2025

---

## 🚀 Per Iniziare

### Guide Utente
- **[Quick Start (5 minuti)](user-guide/QUICK-START.md)** - Installazione e configurazione rapida
- **[Quick Start Reservations Viewer](user-guide/QUICK-START-RESERVATIONS-VIEWER.md)** - Guida al ruolo Viewer
- **[Status & Roadmap](user-guide/STATUS.md)** - Stato progetto e prossimi sviluppi

### Documentazione Tecnica
- **[Configurazione Meals](MEALS-CONFIGURATION.md)** - Come configurare i turni (pranzo/cena)
- **[API Agenda Backend](API-AGENDA-BACKEND.md)** - Endpoint REST per l'agenda
- **[Server-Side Tracking](SERVER-SIDE-TRACKING.md)** - Sistema di tracking eventi
- **[GitHub Auto-Deploy](GITHUB-AUTO-DEPLOY.md)** - Deploy automatico con GitHub

---

## 🔧 Per Sviluppatori

### Architettura
- **[development/FORM-ARCHITECTURE.md](development/FORM-ARCHITECTURE.md)** - Architettura del form frontend
- **[development/FORM-DEPENDENCIES-MAP.md](development/FORM-DEPENDENCIES-MAP.md)** - Mappa delle dipendenze
- **[development/GERARCHIA-CAPACITA-SPIEGAZIONE.md](development/GERARCHIA-CAPACITA-SPIEGAZIONE.md)** - Sistema di calcolo capacità

### Guide Sviluppo
- **[Code Audit 2025-10-13](CODE-AUDIT-2025-10-13.md)** - Audit completo del codice
- **[Bugfix Summary](BUGFIX-SUMMARY-2025-10-13.md)** - Sommario fix applicati
- **[Brevo Email Events](BREVO-EMAIL-EVENTS.md)** - Integrazione email Brevo
- **[Fix Brevo Event Delivery](FIX-BREVO-EVENT-DELIVERY.md)** - Risoluzione problemi Brevo

### Troubleshooting
- **[Manual Booking Notifications](TROUBLESHOOTING-MANUAL-BOOKING-NOTIFICATIONS.md)** - Debug notifiche
- **[Bugfix Menu Settings](BUGFIX-MENU-SETTINGS-VISIBILITY.md)** - Fix visibilità menu

---

## 📁 Struttura Completa

```
docs/
├── INDEX.md                          ← Questo file
├── README.md                         → Panoramica tecnica completa
├── MEALS-CONFIGURATION.md            → Configurazione turni
├── API-AGENDA-BACKEND.md             → API REST backend
├── SERVER-SIDE-TRACKING.md           → Sistema tracking
├── GITHUB-AUTO-DEPLOY.md             → Deploy automation
│
├── user-guide/                       → Guide per utenti finali
│   ├── QUICK-START.md                  → Installazione rapida
│   ├── QUICK-START-RESERVATIONS-VIEWER.md → Ruolo Viewer
│   └── STATUS.md                       → Stato e roadmap
│
├── development/                      → Guide per sviluppatori
│   ├── FORM-ARCHITECTURE.md            → Architettura form
│   ├── FORM-DEPENDENCIES-MAP.md        → Dipendenze
│   ├── GERARCHIA-CAPACITA-SPIEGAZIONE.md → Sistema capacità
│   └── NUOVA-STRUTTURA-CSS-MODULARE.md → Architettura CSS
│
└── archive/                          → Documentazione storica
    └── fixes-2025/                     → Fix e debug del 2025
        ├── FIX-*.md (70+ file)
        ├── DEBUG-*.md
        ├── VERIFICA-*.md
        ├── RISOLUZIONE-*.md
        └── ... (163 file storici)
```

---

## 🎯 Changelog Principali

### v0.1.12 (25 Ottobre 2025) - **ATTUALE**
- ✅ 4 bug critici risolti (giorni disponibili, status, timestamp, meal plan)
- ✅ 7 miglioramenti UX frontend
- ✅ Design system ottimizzato (gradienti -75%, spacing -17%)
- ✅ Documentazione organizzata professionalmente

### v0.1.11 (13 Ottobre 2025)
- ✅ Security hardening (7 vulnerabilità critiche risolte)
- ✅ Code quality (58 bug risolti in 8 sessioni)

### v0.1.10
- ✅ Performance +900%, response time -97%
- ✅ Architettura enterprise (cache, metrics, async)

[**Changelog Completo →**](../CHANGELOG.md)

---

## 🆘 Supporto

### Hai Problemi?
1. Controlla il **[Changelog](../CHANGELOG.md)** - il tuo problema potrebbe essere già risolto
2. Vedi la cartella **[archive/fixes-2025/](archive/fixes-2025/)** - contiene 163 fix storici
3. Apri un issue su [GitHub](https://github.com/franpass87/FP-Restaurant-Reservations/issues)

### Contributing
Leggi [CONTRIBUTING.md](../CONTRIBUTING.md) per linee guida su come contribuire.

---

## 📊 Metriche Qualità

```
✅ Sicurezza:        Nessuna vulnerabilità
✅ Code Quality:     0 errori ESLint
✅ Performance:      <5ms API response
✅ Test Coverage:    PHPUnit + Playwright E2E
✅ Compatibilità:    WordPress 6.5+, PHP 8.1+
✅ Documentazione:   Completa e organizzata
```

---

**Ultimo aggiornamento:** 25 Ottobre 2025  
**Manutentore:** Francesco Passeri

