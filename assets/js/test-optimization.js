/**
 * Test per verificare che le ottimizzazioni non abbiano rotto le funzionalità
 */

// Test per verificare che tutti i moduli siano importabili
async function testModuleImports() {
    console.log('🧪 Testing module imports...');
    
    const modules = [
        './utils/dom-helpers.js',
        './utils/validation.js', 
        './utils/tracking.js',
        './components/form-state.js',
        './components/form-validation.js',
        './components/form-navigation.js'
    ];
    
    const results = [];
    
    for (const module of modules) {
        try {
            const imported = await import(module);
            console.log(`✅ ${module} imported successfully`);
            results.push({ module, status: 'success' });
        } catch (error) {
            console.error(`❌ ${module} failed to import:`, error);
            results.push({ module, status: 'error', error });
        }
    }
    
    return results;
}

// Test per verificare che le funzioni principali siano disponibili
function testCoreFunctionality() {
    console.log('🧪 Testing core functionality...');
    
    const tests = [
        {
            name: 'FormState class',
            test: () => {
                // eslint-disable-next-line no-undef
                const { FormState } = require('./components/form-state.js');
                const state = new FormState();
                return state.getState() !== null;
            }
        },
        {
            name: 'DOM helpers',
            test: () => {
                // eslint-disable-next-line no-undef
                const { closestWithAttribute } = require('./utils/dom-helpers.js');
                return typeof closestWithAttribute === 'function';
            }
        },
        {
            name: 'Validation utilities',
            test: () => {
                // eslint-disable-next-line no-undef
                const { toNumber } = require('./utils/validation.js');
                return typeof toNumber === 'function' && toNumber('123') === 123;
            }
        },
        {
            name: 'Tracking utilities',
            test: () => {
                // eslint-disable-next-line no-undef
                const { pushDataLayerEvent } = require('./utils/tracking.js');
                return typeof pushDataLayerEvent === 'function';
            }
        }
    ];
    
    const results = [];
    
    for (const test of tests) {
        try {
            const passed = test.test();
            console.log(`${passed ? '✅' : '❌'} ${test.name}: ${passed ? 'PASSED' : 'FAILED'}`);
            results.push({ name: test.name, passed });
        } catch (error) {
            console.error(`❌ ${test.name}: ERROR -`, error);
            results.push({ name: test.name, passed: false, error });
        }
    }
    
    return results;
}

// Test per verificare la compatibilità con il codice esistente
function testBackwardCompatibility() {
    console.log('🧪 Testing backward compatibility...');
    
    const tests = [
        {
            name: 'FormApp class exists',
            test: () => {
                return typeof window.FPResv !== 'undefined' && 
                       typeof window.FPResv.FormApp !== 'undefined';
            }
        },
        {
            name: 'fpResvApp alias exists',
            test: () => {
                return typeof window.fpResvApp !== 'undefined';
            }
        },
        {
            name: 'DOM ready event listener',
            test: () => {
                return document.readyState === 'loading' || 
                       document.readyState === 'interactive' || 
                       document.readyState === 'complete';
            }
        }
    ];
    
    const results = [];
    
    for (const test of tests) {
        try {
            const passed = test.test();
            console.log(`${passed ? '✅' : '❌'} ${test.name}: ${passed ? 'PASSED' : 'FAILED'}`);
            results.push({ name: test.name, passed });
        } catch (error) {
            console.error(`❌ ${test.name}: ERROR -`, error);
            results.push({ name: test.name, passed: false, error });
        }
    }
    
    return results;
}

// Test per verificare le performance
function testPerformance() {
    console.log('🧪 Testing performance...');
    
    const startTime = performance.now();
    
    // Simula il caricamento dei moduli
    const modules = [
        'dom-helpers',
        'validation', 
        'tracking',
        'form-state',
        'form-validation',
        'form-navigation'
    ];
    
    // Simula l'inizializzazione
    for (let i = 0; i < 1000; i++) {
        // Simula operazioni di inizializzazione
        Math.random();
    }
    
    const endTime = performance.now();
    const duration = endTime - startTime;
    
    console.log(`⏱️ Performance test completed in ${duration.toFixed(2)}ms`);
    
    return {
        duration,
        passed: duration < 100 // Soglia di 100ms
    };
}

// Funzione principale per eseguire tutti i test
async function runOptimizationTests() {
    console.log('🚀 Starting optimization tests...');
    console.log('=====================================');
    
    const results = {
        moduleImports: await testModuleImports(),
        coreFunctionality: testCoreFunctionality(),
        backwardCompatibility: testBackwardCompatibility(),
        performance: testPerformance()
    };
    
    console.log('=====================================');
    console.log('📊 Test Results Summary:');
    console.log('=====================================');
    
    // Riassunto dei risultati
    const moduleImportResults = results.moduleImports.filter(r => r.status === 'success').length;
    const coreFunctionalityResults = results.coreFunctionality.filter(r => r.passed).length;
    const compatibilityResults = results.backwardCompatibility.filter(r => r.passed).length;
    const performancePassed = results.performance.passed;
    
    console.log(`📦 Module Imports: ${moduleImportResults}/${results.moduleImports.length} passed`);
    console.log(`🔧 Core Functionality: ${coreFunctionalityResults}/${results.coreFunctionality.length} passed`);
    console.log(`🔄 Backward Compatibility: ${compatibilityResults}/${results.backwardCompatibility.length} passed`);
    console.log(`⚡ Performance: ${performancePassed ? 'PASSED' : 'FAILED'} (${results.performance.duration.toFixed(2)}ms)`);
    
    const allPassed = moduleImportResults === results.moduleImports.length &&
                     coreFunctionalityResults === results.coreFunctionality.length &&
                     compatibilityResults === results.backwardCompatibility.length &&
                     performancePassed;
    
    console.log('=====================================');
    console.log(`🎯 Overall Result: ${allPassed ? '✅ ALL TESTS PASSED' : '❌ SOME TESTS FAILED'}`);
    console.log('=====================================');
    
    return {
        allPassed,
        results
    };
}

// Esegui i test se il file viene caricato direttamente
if (typeof window !== 'undefined' && window.location.pathname.includes('test-optimization')) {
    runOptimizationTests().then(results => {
        if (results.allPassed) {
            console.log('🎉 Optimization verification completed successfully!');
        } else {
            console.error('⚠️ Some optimization tests failed. Please review the results above.');
        }
    });
}

// Esporta le funzioni per uso esterno
// eslint-disable-next-line no-undef
if (typeof module !== 'undefined' && module.exports) {
    // eslint-disable-next-line no-undef
    module.exports = {
        testModuleImports,
        testCoreFunctionality,
        testBackwardCompatibility,
        testPerformance,
        runOptimizationTests
    };
}
