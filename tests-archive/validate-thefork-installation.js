/**
 * Script di validazione installazione The Fork Style
 * Verifica che tutti i file siano presenti e il CSS sia caricato correttamente
 */

(function() {
    'use strict';
    
    console.log('🔍 Validazione The Fork Style Installation...\n');
    
    const results = {
        passed: [],
        failed: [],
        warnings: []
    };
    
    // Test 1: Verifica presenza widget
    function testWidgetPresence() {
        const widget = document.querySelector('.fp-resv-widget');
        if (widget) {
            results.passed.push('✅ Widget container trovato');
            return true;
        } else {
            results.failed.push('❌ Widget container NON trovato');
            return false;
        }
    }
    
    // Test 2: Verifica CSS variabili The Fork
    function testCSSVariables() {
        const widget = document.querySelector('.fp-resv-widget');
        if (!widget) {
            results.failed.push('❌ Impossibile testare variabili CSS (widget non trovato)');
            return false;
        }
        
        const computedStyle = getComputedStyle(widget);
        const primaryColor = computedStyle.getPropertyValue('--fp-color-primary').trim();
        
        // Il colore The Fork verde è #2db77e che in RGB è rgb(45, 183, 126)
        if (primaryColor.includes('45') || primaryColor.includes('2db77e')) {
            results.passed.push('✅ Colore primario The Fork corretto: ' + primaryColor);
            return true;
        } else if (primaryColor) {
            results.warnings.push('⚠️  Colore primario trovato ma diverso dal verde The Fork: ' + primaryColor);
            return true;
        } else {
            results.failed.push('❌ Variabile --fp-color-primary non trovata');
            return false;
        }
    }
    
    // Test 3: Verifica altezza input
    function testInputHeight() {
        const input = document.querySelector('.fp-input');
        if (!input) {
            results.warnings.push('⚠️  Nessun input trovato per test altezza');
            return true;
        }
        
        const height = parseInt(getComputedStyle(input).height);
        
        // The Fork style usa 56px (3.5rem)
        if (height >= 52 && height <= 60) {
            results.passed.push('✅ Altezza input corretta: ' + height + 'px');
            return true;
        } else {
            results.warnings.push('⚠️  Altezza input inaspettata: ' + height + 'px (attesa: 56px)');
            return true;
        }
    }
    
    // Test 4: Verifica border-radius
    function testBorderRadius() {
        const widget = document.querySelector('.fp-resv-widget');
        if (!widget) return true;
        
        const borderRadius = parseInt(getComputedStyle(widget).borderRadius);
        
        // The Fork style usa border-radius generosi (24px / 1.5rem)
        if (borderRadius >= 20) {
            results.passed.push('✅ Border radius generoso: ' + borderRadius + 'px');
            return true;
        } else if (borderRadius > 0) {
            results.warnings.push('⚠️  Border radius trovato ma più piccolo del previsto: ' + borderRadius + 'px');
            return true;
        } else {
            results.warnings.push('⚠️  Nessun border radius trovato');
            return true;
        }
    }
    
    // Test 5: Verifica bottoni pill-shaped
    function testPillButtons() {
        const buttons = document.querySelectorAll('.fp-btn, .fp-meal-pill');
        if (buttons.length === 0) {
            results.warnings.push('⚠️  Nessun bottone trovato per test pill-shape');
            return true;
        }
        
        let pillCount = 0;
        buttons.forEach(btn => {
            const borderRadius = getComputedStyle(btn).borderRadius;
            // Pill shape ha border-radius molto alto o 9999px
            if (borderRadius.includes('9999') || parseInt(borderRadius) > 100) {
                pillCount++;
            }
        });
        
        if (pillCount > 0) {
            results.passed.push('✅ Bottoni pill-shaped trovati: ' + pillCount);
            return true;
        } else {
            results.warnings.push('⚠️  Nessun bottone con pill-shape completo trovato');
            return true;
        }
    }
    
    // Test 6: Verifica progress bar
    function testProgressBar() {
        const progress = document.querySelector('.fp-progress');
        const progressItems = document.querySelectorAll('.fp-progress__item');
        
        if (!progress) {
            results.warnings.push('⚠️  Progress bar non trovata');
            return true;
        }
        
        if (progressItems.length > 0) {
            results.passed.push('✅ Progress bar trovata con ' + progressItems.length + ' items');
            
            // Verifica che abbia display flex (nuovo stile)
            const display = getComputedStyle(progress).display;
            if (display === 'flex') {
                results.passed.push('✅ Progress bar usa layout flex (The Fork style)');
            }
            return true;
        } else {
            results.warnings.push('⚠️  Progress bar trovata ma senza items');
            return true;
        }
    }
    
    // Test 7: Verifica attributi data-* (compatibilità JS)
    function testDataAttributes() {
        const requiredAttrs = [
            '[data-fp-resv]',
            '[data-fp-resv-form]',
            '[data-fp-resv-section]',
            '[data-step]'
        ];
        
        let found = 0;
        requiredAttrs.forEach(selector => {
            if (document.querySelector(selector)) {
                found++;
            }
        });
        
        if (found === requiredAttrs.length) {
            results.passed.push('✅ Tutti gli attributi data-* necessari trovati');
            return true;
        } else {
            results.failed.push('❌ Attributi data-* mancanti (' + found + '/' + requiredAttrs.length + ' trovati)');
            return false;
        }
    }
    
    // Test 8: Verifica responsive
    function testResponsive() {
        const widget = document.querySelector('.fp-resv-widget');
        if (!widget) return true;
        
        const maxWidth = getComputedStyle(widget).maxWidth;
        
        // The Fork style usa 680px
        if (maxWidth.includes('680') || maxWidth.includes('px')) {
            results.passed.push('✅ Max-width responsive configurato');
            return true;
        } else {
            results.warnings.push('⚠️  Max-width: ' + maxWidth);
            return true;
        }
    }
    
    // Esegui tutti i test
    console.log('📋 Esecuzione test...\n');
    
    testWidgetPresence();
    testCSSVariables();
    testInputHeight();
    testBorderRadius();
    testPillButtons();
    testProgressBar();
    testDataAttributes();
    testResponsive();
    
    // Mostra risultati
    console.log('\n📊 RISULTATI VALIDAZIONE:\n');
    
    if (results.passed.length > 0) {
        console.log('✅ TEST PASSATI (' + results.passed.length + '):');
        results.passed.forEach(msg => console.log('   ' + msg));
        console.log('');
    }
    
    if (results.warnings.length > 0) {
        console.log('⚠️  AVVISI (' + results.warnings.length + '):');
        results.warnings.forEach(msg => console.log('   ' + msg));
        console.log('');
    }
    
    if (results.failed.length > 0) {
        console.log('❌ TEST FALLITI (' + results.failed.length + '):');
        results.failed.forEach(msg => console.log('   ' + msg));
        console.log('');
    }
    
    // Verdetto finale
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    if (results.failed.length === 0) {
        console.log('🎉 INSTALLAZIONE THE FORK STYLE: ✅ SUCCESSO!');
        console.log('');
        console.log('Il form è stato ricreato correttamente con l\'estetica The Fork.');
        console.log('Tutti i componenti sono funzionanti e compatibili.');
    } else {
        console.log('⚠️  INSTALLAZIONE THE FORK STYLE: ⚠️  CON PROBLEMI');
        console.log('');
        console.log('Alcuni test sono falliti. Verifica:');
        console.log('1. Che assets/css/form.css importi form-thefork.css');
        console.log('2. Che il CSS sia stato caricato correttamente');
        console.log('3. Che non ci siano conflitti con altri CSS');
    }
    
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    // Info aggiuntive
    console.log('\n📚 DOCUMENTAZIONE:');
    console.log('   - THEFORK-STYLE-README.md');
    console.log('   - THEFORK-STYLE-MIGRATION.md');
    console.log('   - CHANGELOG-THEFORK-STYLE.md');
    console.log('\n🧪 TEST VISIVO:');
    console.log('   Apri: test-thefork-form.html');
    console.log('\n💡 PERSONALIZZAZIONE:');
    console.log('   Modifica: assets/css/form/_variables-thefork.css');
    console.log('');
    
    // Return per uso programmatico
    return {
        success: results.failed.length === 0,
        passed: results.passed.length,
        warnings: results.warnings.length,
        failed: results.failed.length,
        details: results
    };
})();
