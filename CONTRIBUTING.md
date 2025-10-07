# 🤝 Contribuire a FP Restaurant Reservations

Grazie per l'interesse nel contribuire a FP Restaurant Reservations! Questo documento descrive le linee guida e il processo per contribuire al progetto.

## 📊 Stato del Progetto

**Versione attuale**: 0.1.6  
**Status**: ✅ Production Ready  
**Fasi completate**: 21/21  
**Security audit**: ✅ 5/5 problemi risolti

Consulta [STATUS.md](STATUS.md) per lo stato completo del progetto.

## 🛠️ Requisiti di Sviluppo

### Ambiente Minimo
- **PHP**: 8.1+ (raccomandato 8.2+)
- **Node.js**: 18+ LTS
- **Composer**: 2.x
- **npm**: 9+

### Tools Raccomandati
- **Editor**: VS Code, PHPStorm o simile con support per:
  - PHP Intelephense / PHP Language Server
  - ESLint
  - EditorConfig
- **Docker**: Per ambiente di test locale (opzionale)

### Setup Locale

```bash
# Clone repository
git clone https://github.com/franpass87/FP-Restaurant-Reservations.git
cd FP-Restaurant-Reservations

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Run tests
npm run test
```

## 🔄 Workflow di Contribuzione

### 1. Fork e Branch

```bash
# Fork del repository su GitHub
# Clone del tuo fork
git clone https://github.com/TUO_USERNAME/FP-Restaurant-Reservations.git
cd FP-Restaurant-Reservations

# Crea un branch descrittivo
git checkout -b feature/nome-feature
# oppure
git checkout -b bugfix/nome-bug
```

### 2. Sviluppo

- Mantieni le modifiche focalizzate e atomiche
- Segui le linee guida di codifica (vedi sotto)
- Aggiungi test per nuove funzionalità
- Aggiorna la documentazione se necessario

### 3. Quality Checks

Prima di aprire una PR, esegui tutti i controlli di qualità:

```bash
# Linting PHP
npm run lint:php

# Static analysis PHP
npm run lint:phpstan

# Linting JavaScript
npm run lint:js

# Run tests
npm run test

# Check vulnerabilities
npm audit
```

**Tutti i check devono passare** prima di aprire una PR.

### 4. Commit

Usa commit message descrittivi seguendo la convenzione:

```
type(scope): breve descrizione

Descrizione dettagliata se necessario.

Fixes #123
```

**Types**:
- `feat`: Nuova funzionalità
- `fix`: Bug fix
- `docs`: Modifiche documentazione
- `style`: Formattazione codice (no logic changes)
- `refactor`: Refactoring senza change funzionale
- `test`: Aggiunta/modifica test
- `chore`: Maintenance tasks

**Examples**:
```
feat(agenda): aggiunta vista settimanale calendario

fix(survey): risolto problema nonce verification

docs(readme): aggiornato setup instructions
```

### 5. Pull Request

1. Push del branch al tuo fork
2. Apri una PR su GitHub verso `main`
3. Compila il template della PR con:
   - Descrizione delle modifiche
   - Motivazione
   - Test effettuati
   - Screenshot (se UI)
   - Issue correlate

## 📝 Linee Guida di Codifica

### PHP

- **Standard**: PSR-12 + WordPress Coding Standards
- **Strict types**: Usa `declare(strict_types=1);` in tutti i nuovi file
- **Type hints**: Sempre tipizzare parametri e return values
- **Namespace**: PSR-4 autoloading sotto `FP\Resv\`
- **Docblocks**: PHPDoc completi per classi e metodi pubblici

**Example**:
```php
<?php

declare(strict_types=1);

namespace FP\Resv\Domain;

/**
 * Service per la gestione delle prenotazioni.
 */
class ReservationService
{
    /**
     * Crea una nuova prenotazione.
     *
     * @param array<string, mixed> $data Dati prenotazione
     * @return int ID prenotazione creata
     * @throws ValidationException Se i dati non sono validi
     */
    public function create(array $data): int
    {
        // Implementation
    }
}
```

### JavaScript

- **Standard**: ESLint config del progetto
- **Modules**: ES6 modules con import/export
- **Const/Let**: No `var`
- **Arrow functions**: Preferite dove appropriato
- **Async/Await**: Preferito su Promises dirette
- **JSDoc**: Per funzioni complesse

**Example**:
```javascript
/**
 * Carica la disponibilità per una data specifica.
 * @param {string} date - Data in formato YYYY-MM-DD
 * @param {number} guests - Numero ospiti
 * @returns {Promise<Object>} Disponibilità
 */
export async function loadAvailability(date, guests) {
    // Implementation
}
```

### CSS

- **BEM naming**: Per classi CSS
- **Custom properties**: Per valori riutilizzabili
- **Mobile-first**: Media queries progressive
- **Accessibilità**: Contrasti WCAG AA minimo

### Internazionalizzazione

- **Text domain**: `fp-restaurant-reservations`
- **Sempre usare**: `__()`, `_e()`, `esc_html__()`, etc.
- **No hardcoded strings**: Mai stringhe hardcoded in italiano/inglese

**Example**:
```php
// ❌ No
$message = 'Prenotazione creata con successo';

// ✅ Yes
$message = __('Prenotazione creata con successo', 'fp-restaurant-reservations');
```

## 🧪 Testing

### PHPUnit

```bash
# Run all tests
npm run test:phpunit

# Run specific test
./vendor/bin/phpunit tests/Unit/Domain/ReservationServiceTest.php
```

### Playwright E2E

```bash
# Run E2E tests
npm run test:e2e
```

### Test Coverage

- Nuove feature devono includere test
- Bug fix devono includere regression test
- Target coverage: >70% per nuovo codice

## 📚 Documentazione

### Quando Aggiornare

Aggiorna la documentazione se:
- Aggiungi nuove feature
- Modifichi API pubbliche
- Cambi configurazioni
- Modifichi processo di build/deploy

### File da Aggiornare

- `README.md`: Overview e quick start
- `CHANGELOG.md`: Tutte le modifiche
- `docs/*.md`: Guide specifiche
- Docstrings nel codice

## 🐛 Segnalazione Bug

### Prima di Segnalare

1. Verifica che il bug non sia già segnalato
2. Prova con l'ultima versione
3. Raccogli informazioni diagnostiche

### Crea Issue con:

- **Titolo chiaro**: Descrizione breve del problema
- **Versione**: Plugin, WordPress, PHP
- **Ambiente**: Browser, tema, altri plugin attivi
- **Passi per riprodurre**: Dettagliati e numerati
- **Risultato atteso vs effettivo**
- **Log/Screenshot**: Se rilevanti (oscura dati sensibili!)

**Template**:
```markdown
### Descrizione
Breve descrizione del bug

### Ambiente
- Plugin: 0.1.6
- WordPress: 6.6
- PHP: 8.2
- Browser: Chrome 120

### Passi per Riprodurre
1. Vai a...
2. Clicca su...
3. Compila...
4. Vedi errore

### Risultato Atteso
Cosa dovrebbe succedere

### Risultato Effettivo
Cosa succede invece

### Log
```
[log content]
```
```

## 💡 Proposta Feature

Per proporre una nuova feature:

1. Apri una issue con tag `enhancement`
2. Descrivi il caso d'uso
3. Proponi implementazione (opzionale)
4. Attendi feedback del team

## 🔒 Sicurezza

**NON aprire issue pubbliche per vulnerabilità di sicurezza!**

Invia invece email a: **info@francescopasseri.com**

Include:
- Descrizione della vulnerabilità
- Passi per riprodurre
- Impatto potenziale
- Suggerimenti per fix (opzionale)

## 📜 Licenza

Contribuendo accetti che i tuoi contributi saranno licenziati sotto GPL-2.0+.

## 🙏 Riconoscimenti

Tutti i contributori vengono riconosciuti nel file CHANGELOG.md e nei release notes.

---

**Grazie per contribuire a FP Restaurant Reservations!** 🎉

Per domande: info@francescopasseri.com
