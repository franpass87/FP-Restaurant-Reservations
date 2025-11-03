# 📂 Struttura Documentazione - Guida Organizzativa

**Data Riorganizzazione:** 2 Novembre 2025  
**Versione Plugin:** 0.9.0-rc7

---

## 🎯 SCOPO

Questa guida spiega come è organizzata la documentazione del plugin FP Restaurant Reservations dopo il riordino del 2 Novembre 2025.

---

## 📁 STRUTTURA COMPLETA

```
FP-Restaurant-Reservations/
│
├── 📄 README.md                          ← README principale plugin
├── 📝 CHANGELOG.md                       ← Changelog versioni
├── 📜 CONTRIBUTING.md                    ← Guida contribuzione
├── 🇮🇹 LEGGIMI.md                        ← README italiano
│
├── 📁 docs/                              ← TUTTA LA DOCUMENTAZIONE
│   │
│   ├── 📖 INDEX.md                       ← **INDICE NAVIGABILE** ⭐
│   ├── 📄 README.md                      ← Panoramica docs
│   ├── 📋 STRUTTURA-DOCUMENTAZIONE.md    ← Questo file
│   │
│   ├── 📁 guides/                        ← GUIDE
│   │   ├── user/                         ← Per utenti finali
│   │   │   ├── QUICK-START.md
│   │   │   ├── QUICK-START-RESERVATIONS-VIEWER.md
│   │   │   └── STATUS.md
│   │   │
│   │   └── developer/                    ← Per sviluppatori
│   │       ├── README-BUILD.md
│   │       ├── CACHE-GUIDE.md
│   │       ├── CACHE-REFRESH-GUIDE.md
│   │       ├── METRICS-GUIDE.md
│   │       └── GITHUB-AUTO-DEPLOY.md
│   │
│   ├── 📁 api/                           ← API DOCUMENTATION
│   │   ├── API-AGENDA-BACKEND.md
│   │   ├── TRACKING-MAP.md
│   │   └── SERVER-SIDE-TRACKING.md
│   │
│   ├── 📁 bugfixes/                      ← BUGFIX RECENTI
│   │   └── 2025-11-02/                   ← Per data
│   │       ├── SESSIONE-BUGFIX-COMPLETA-2025-11-02.md
│   │       ├── BUGFIX-COMPLETE-REPORT-2025-11-02.md
│   │       ├── BUGFIX-SESSION-2-2025-11-02.md
│   │       ├── BUGFIX-REPORT-FINAL-2025-11-02.md
│   │       ├── BUGFIX-SESSION-2025-11-02.md
│   │       └── VERIFICA-COMPLETA-2025-11-02.md
│   │
│   ├── 📁 archive/                       ← STORICO
│   │   ├── fixes-2025/                   ← Fix 2025 (157 file)
│   │   └── debug/                        ← Debug logs
│   │
│   ├── 📄 Documenti root docs/           ← Doc principali
│   │   ├── ASSET-LOADING.md
│   │   ├── BUGFIX-TIMEZONE-PHP-2025-11-02.md
│   │   ├── SLOT-TIMES-SYSTEM.md
│   │   ├── MEALS-CONFIGURATION.md
│   │   ├── MIGRATION-GUIDE.md
│   │   ├── ROADMAP-1.0.md
│   │   ├── SECURITY-REPORT.md
│   │   ├── TEST-SCENARIOS.md
│   │   ├── CHECKLIST-TEST-1.0.md
│   │   └── ... altri
│   │
│   └── 📁 development/                   ← Dev docs vari
│
├── 📁 AUDIT/                             ← Security audit
│   ├── REPORT.md
│   ├── ISSUES.json
│   └── TODO.md
│
└── 📁 tools/                             ← Script utilità
    ├── quick-health-check.php
    ├── test-plugin-health.php
    ├── verify-slot-times.php
    └── ... altri
```

---

## 🗺️ NAVIGAZIONE DOCUMENTAZIONE

### Livello 1: Punto di Ingresso

**FILE:** `README.md` (root plugin)
- Panoramica plugin
- Link a documentazione
- Quick start base

### Livello 2: Indice Completo

**FILE:** `docs/INDEX.md` ⭐ **← INIZIA QUI!**
- Indice navigabile completo
- Tabelle di lookup
- Ricerca per argomento
- Link a tutti i documenti

### Livello 3: Categorie Specifiche

**DIRECTORY:**
- `docs/guides/user/` - Guide per utenti
- `docs/guides/developer/` - Guide per sviluppatori
- `docs/api/` - Documentazione API
- `docs/bugfixes/` - Report bugfix

### Livello 4: Documenti Specifici

File markdown specifici per argomento

---

## 📋 CATEGORIE DOCUMENTAZIONE

### 1. 👥 Guide Utente
**Directory:** `docs/guides/user/`

| File | Quando Usarlo |
|------|---------------|
| QUICK-START.md | Prima installazione |
| QUICK-START-RESERVATIONS-VIEWER.md | Setup ruolo viewer |
| STATUS.md | Verifica funzionalità |

**Target:** Restaurant owner, manager, staff

---

### 2. 👨‍💻 Guide Sviluppatore
**Directory:** `docs/guides/developer/`

| File | Quando Usarlo |
|------|---------------|
| README-BUILD.md | Build e deploy |
| CACHE-GUIDE.md | Capire il sistema cache |
| CACHE-REFRESH-GUIDE.md | Problemi cache |
| METRICS-GUIDE.md | Implementare metriche |
| GITHUB-AUTO-DEPLOY.md | Setup auto-deploy |

**Target:** Developer, DevOps

---

### 3. 🌐 API Documentation
**Directory:** `docs/api/`

| File | Argomento |
|------|-----------|
| API-AGENDA-BACKEND.md | REST API Agenda |
| TRACKING-MAP.md | Eventi tracking |
| SERVER-SIDE-TRACKING.md | Server-side tracking |

**Target:** Developer, integrations

---

### 4. 🐛 Bugfix Reports
**Directory:** `docs/bugfixes/YYYY-MM-DD/`

Organizzati per data. Ultimi bugfix in `2025-11-02/`:

| File | Descrizione |
|------|-------------|
| SESSIONE-BUGFIX-COMPLETA-2025-11-02.md | Riepilogo globale |
| BUGFIX-COMPLETE-REPORT-2025-11-02.md | Report consolidato |
| BUGFIX-SESSION-2-2025-11-02.md | Security audit |

**Target:** Developer, QA

---

### 5. 📚 Documenti Principali
**Directory:** `docs/` (root)

Documenti standalone importanti:

| File | Argomento |
|------|-----------|
| SLOT-TIMES-SYSTEM.md | Sistema slot orari |
| BUGFIX-TIMEZONE-PHP-2025-11-02.md | Fix timezone |
| MEALS-CONFIGURATION.md | Config pasti |
| ASSET-LOADING.md | Caricamento asset |
| MIGRATION-GUIDE.md | Migrazione |
| ROADMAP-1.0.md | Roadmap v1.0 |
| SECURITY-REPORT.md | Security audit |

---

### 6. 🗄️ Archivio
**Directory:** `docs/archive/`

Documenti storici, non più attuali ma mantenuti per reference:

- `fixes-2025/` - 157 file di fix passati
- `debug/` - Debug logs storici

**Target:** Historical reference

---

## 🔍 COME TROVARE UN DOCUMENTO

### Metodo 1: Indice Navigabile
1. Apri **[docs/INDEX.md](INDEX.md)**
2. Cerca nella categoria appropriata
3. Usa le tabelle di lookup

### Metodo 2: Ricerca per Argomento

#### "Voglio configurare i pasti"
→ [docs/MEALS-CONFIGURATION.md](MEALS-CONFIGURATION.md)

#### "Ho un problema con gli slot orari"
→ [docs/SLOT-TIMES-SYSTEM.md](SLOT-TIMES-SYSTEM.md)

#### "Voglio vedere i bugfix recenti"
→ [docs/bugfixes/2025-11-02/](bugfixes/2025-11-02/)

#### "Come funzionano le API?"
→ [docs/api/API-AGENDA-BACKEND.md](api/API-AGENDA-BACKEND.md)

#### "Problemi di cache"
→ [docs/guides/developer/CACHE-REFRESH-GUIDE.md](guides/developer/CACHE-REFRESH-GUIDE.md)

### Metodo 3: Grep/Ricerca

```bash
# Cerca in tutta la documentazione
grep -r "parola chiave" docs/

# Esempio: cerca "timezone"
grep -r "timezone" docs/
```

---

## 📝 CONVENZIONI NOMI FILE

### Prefissi

| Prefisso | Significato | Esempio |
|----------|-------------|---------|
| `QUICK-START-` | Guide rapide | QUICK-START.md |
| `BUGFIX-` | Report bugfix | BUGFIX-TIMEZONE-PHP-2025-11-02.md |
| `FIX-` | Fix specifico | FIX-TIMEZONE-ITALIA.md |
| `API-` | Documentazione API | API-AGENDA-BACKEND.md |
| `GUIDE-` | Guide | (usare directory guides/) |

### Suffissi

| Suffisso | Significato | Esempio |
|----------|-------------|---------|
| `-GUIDE` | Guida completa | CACHE-GUIDE.md |
| `-REPORT` | Report | SECURITY-REPORT.md |
| `-2025-11-02` | Data fix | BUGFIX-TIMEZONE-PHP-2025-11-02.md |

### Case Style

- **UPPERCASE** - Documenti importanti/principali
- **lowercase** - Documenti secondari
- **kebab-case** - Per URL-friendly

---

## 🔄 MANUTENZIONE DOCUMENTAZIONE

### Quando Aggiungere Nuovi Documenti

#### Bugfix
1. Crea directory `docs/bugfixes/YYYY-MM-DD/`
2. Aggiungi documenti di report
3. Aggiorna `docs/INDEX.md`

#### Guide
1. Identifica categoria (user/developer)
2. Aggiungi in `docs/guides/[categoria]/`
3. Aggiorna `docs/INDEX.md`

#### API
1. Aggiungi in `docs/api/`
2. Aggiorna `docs/INDEX.md`

### Quando Archiviare

Se un documento è obsoleto ma ha valore storico:
1. Sposta in `docs/archive/fixes-YYYY/`
2. Aggiorna riferimenti
3. Aggiorna `docs/INDEX.md`

---

## ✅ CHECKLIST DOCUMENTAZIONE

### Nuovo Documento

- [ ] Nome file descrittivo
- [ ] Data nel nome (se bugfix/fix)
- [ ] Header con metadata
- [ ] Contenuto strutturato
- [ ] Link relativi corretti
- [ ] Aggiunto a INDEX.md
- [ ] Aggiunto a README.md (se importante)

### Riorganizzazione

- [ ] File spostati in directory corretta
- [ ] Link aggiornati
- [ ] INDEX.md aggiornato
- [ ] README.md aggiornato
- [ ] Verificati link rotti

---

## 🎯 VANTAGGI NUOVA STRUTTURA

### Prima (Disorganizzato)
```
❌ 15+ file .md sparsi nella root
❌ Nessuna categorizzazione
❌ Difficile trovare documenti
❌ Mix di bugfix/guide/api
```

### Dopo (Organizzato)
```
✅ Directory per categoria
✅ Indice navigabile completo
✅ Bugfix organizzati per data
✅ Guide separate user/developer
✅ Facile manutenzione
```

---

## 📞 SUPPORTO DOCUMENTAZIONE

### Problemi con la Documentazione?

1. Verifica [docs/INDEX.md](INDEX.md)
2. Cerca nel file per argomento
3. Controlla [archive/](archive/) per documenti storici

### Vuoi Aggiungere Documentazione?

Segui le convenzioni sopra e aggiorna INDEX.md!

---

## 🎉 RISULTATO

```
╔═══════════════════════════════════════════╗
║  ✅ DOCUMENTAZIONE RIORGANIZZATA          ║
║                                           ║
║  Struttura: CHIARA                       ║
║  Navigazione: FACILE                     ║
║  Manutenzione: SEMPLICE                  ║
║  Aggiornata: 2 Nov 2025                  ║
╚═══════════════════════════════════════════╝
```

---

**Autore:** Francesco Passeri  
**Data Riorganizzazione:** 2 Novembre 2025  
**File Organizzati:** 180+

