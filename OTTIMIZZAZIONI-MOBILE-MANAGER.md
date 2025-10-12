# Ottimizzazioni Mobile Manager Prenotazioni

## Panoramica

Il Manager Prenotazioni è stato completamente ottimizzato per l'uso su dispositivi mobili, garantendo un'esperienza fluida e touch-friendly su tutti i dispositivi.

## Modifiche Implementate

### 1. CSS Responsive Avanzato

#### Tablet (≤ 1024px)
- Toolbar riorganizzato in layout verticale
- Statistiche dashboard in griglia 2 colonne
- Filtri e controlli a larghezza piena

#### Mobile (≤ 768px)
- **Header compatto**: Logo e breadcrumb ottimizzati, azioni in riga
- **Stats dashboard scorrevole**: Scorrimento orizzontale con snap per navigazione fluida
- **Toolbar mobile-first**: Controlli impilati verticalmente, view switcher solo icone
- **Buttons touch-friendly**: Dimensione minima 44px per facilità di tap
- **Date navigation**: Layout orizzontale ottimizzato con date picker centrale
- **Modal bottom sheet**: Modal che appare dal basso (stile nativo)
- **Calendario ottimizzato**: Celle più piccole ma leggibili, font ridimensionati
- **Card prenotazioni**: Layout verticale, informazioni semplificate

#### Small Mobile (≤ 480px)
- **Header ultra-compatto**: Solo icone per azioni secondarie
- **Stats cards carousel**: Scorrimento orizzontale con scroll snap
- **View switcher icone**: Solo dashicons visibili
- **Calendario mini**: Dimensioni ottimizzate per schermi piccoli
- **Modal fullscreen**: Modal a schermo intero su dispositivi molto piccoli
- **Date picker full-width**: Selettore data occupa tutta la larghezza

### 2. Touch Device Optimizations

```css
@media (hover: none) and (pointer: coarse)
```

- **Aree di tap aumentate**: Min 44x44px per tutti gli elementi interattivi
- **Tap highlight**: Colore personalizzato per feedback visivo
- **Hover effects rimossi**: Nessuna animazione hover su touch
- **Active states**: Feedback visivo al tap (scale, opacity)

### 3. Landscape Mobile Support

Ottimizzazioni specifiche per orientamento landscape:
- Header in layout orizzontale
- Stats in griglia 2 colonne
- Calendario con altezza aumentata

### 4. Meta Viewport & Admin Adjustments

**File**: `src/Domain/Reservations/AdminController.php`

Aggiunto metodo `addMobileViewportMeta()`:
- Meta viewport ottimizzato: `width=device-width, initial-scale=1.0, maximum-scale=5.0`
- Rimozione padding WordPress admin su mobile
- Stili inline per layout WordPress admin

## Caratteristiche Mobile

### Navigation Ottimizzata
- ✅ Date picker touch-friendly
- ✅ View switcher con icone grandi
- ✅ Pulsanti dimensione minima 44px
- ✅ Filtri dropdown nativi iOS/Android

### Stats Dashboard
- ✅ Scorrimento orizzontale con scroll snap
- ✅ Card ottimizzate per swipe
- ✅ Icone e valori ridimensionati
- ✅ Touch-friendly hover states

### Calendar Views

#### Mese
- Celle calendario ottimizzate
- Font leggibili su schermi piccoli
- Tap areas aumentate
- Info prenotazioni compatte

#### Giorno/Lista
- Card a larghezza piena
- Layout verticale ottimizzato
- Informazioni essenziali in primo piano

### Modal & Forms
- Modal bottom sheet su mobile (768px)
- Modal fullscreen su small mobile (480px)
- Input font-size 16px (previene zoom iOS)
- Form a colonna singola
- Pulsanti touch-friendly

## Breakpoints Utilizzati

| Breakpoint | Descrizione | Target |
|------------|-------------|--------|
| 1024px | Tablet landscape | iPad, tablet Android |
| 768px | Mobile/Tablet portrait | iPhone Plus, iPad portrait |
| 480px | Small mobile | iPhone standard, Android phone |

## Media Queries Speciali

### Hover Detection
```css
@media (hover: none) and (pointer: coarse)
```
Rileva dispositivi touch puri (no mouse/trackpad).

### Orientation
```css
@media (max-width: 768px) and (orientation: landscape)
```
Layout specifici per mobile in landscape.

## Best Practices Implementate

### Touch Targets
- ✅ Minimo 44x44px (Apple HIG)
- ✅ Spaziatura adeguata tra elementi
- ✅ Feedback visivo al tap

### Typography
- ✅ Font size 16px su input (no iOS zoom)
- ✅ Line-height aumentato per leggibilità
- ✅ Contrasto WCAG AA compliant

### Performance
- ✅ Scroll snap per navigazione fluida
- ✅ Transform per animazioni (GPU)
- ✅ Will-change solo dove necessario
- ✅ Overflow-scrolling touch (-webkit)

### Accessibilità
- ✅ Touch targets grandi
- ✅ Focus visibile
- ✅ Labels descrittive
- ✅ Zoom non bloccato (max 5x)

## File Modificati

### CSS
- `assets/css/admin-manager.css`
  - Sezione responsive completamente riscritta
  - +500 righe di ottimizzazioni mobile
  - Touch device optimizations
  - Landscape mode support

### PHP
- `src/Domain/Reservations/AdminController.php`
  - Aggiunto metodo `addMobileViewportMeta()`
  - Meta viewport configurato
  - Stili inline per admin WordPress

## Testing Consigliato

### Dispositivi
- ✅ iPhone SE/8 (375px)
- ✅ iPhone 12/13/14 (390px)
- ✅ iPhone Plus/Max (428px)
- ✅ iPad Portrait (768px)
- ✅ iPad Landscape (1024px)
- ✅ Android Phone (360-480px)
- ✅ Android Tablet (600-800px)

### Browser
- Safari iOS (14+)
- Chrome Mobile
- Firefox Mobile
- Samsung Internet

### Orientamenti
- Portrait
- Landscape

### Funzionalità da Testare
1. Navigation date picker
2. View switcher
3. Stats cards swipe
4. Calendar tap/swipe
5. Modal apertura/chiusura
6. Form input (no zoom iOS)
7. Filtri dropdown
8. Search box

## Compatibilità

- **iOS**: 12+
- **Android**: 5.0+
- **Chrome**: 90+
- **Safari**: 12+
- **Firefox**: 88+

## Note per Developer

### Modificare Breakpoints
I breakpoints sono definiti nelle media queries in `admin-manager.css`:
```css
@media (max-width: 1024px) { /* Tablet */ }
@media (max-width: 768px) { /* Mobile */ }
@media (max-width: 480px) { /* Small Mobile */ }
```

### Aggiungere Touch Optimizations
Per elementi interattivi custom, assicurati di:
```css
.custom-element {
    min-height: 44px; /* Apple HIG */
    -webkit-tap-highlight-color: rgba(79, 70, 229, 0.1);
}

@media (hover: none) and (pointer: coarse) {
    .custom-element:hover {
        transform: none; /* Rimuovi hover su touch */
    }
    .custom-element:active {
        transform: scale(0.97); /* Feedback visivo */
    }
}
```

### Debug Mobile
Per debug su dispositivo reale:
1. iOS Safari: Settings → Safari → Advanced → Web Inspector
2. Chrome Android: chrome://inspect
3. Firefox Android: about:debugging

## Performance Tips

### Scroll Snap
Lo scroll snap è ottimizzato per performance:
```css
scroll-snap-type: x mandatory;
-webkit-overflow-scrolling: touch;
```

### Hardware Acceleration
Animazioni usano transform per GPU acceleration:
```css
transform: translateY(-2px); /* GPU */
/* Evita: top, left, margin */ /* CPU */
```

## Future Improvements

- [ ] PWA support per installazione home screen
- [ ] Gesture swipe per navigare tra date
- [ ] Haptic feedback su azioni importanti
- [ ] Offline mode con Service Worker
- [ ] Pull-to-refresh per aggiornare dati
- [ ] Bottom navigation bar nativa

## Supporto

Per problemi o domande sulle ottimizzazioni mobile:
1. Verifica breakpoint in Chrome DevTools
2. Testa su dispositivo reale
3. Controlla console per errori JS
4. Valida HTML/CSS con W3C Validator

---

**Ultima modifica**: 2025-10-12
**Versione**: 1.0.0
**Autore**: FP Restaurant Reservations Team

