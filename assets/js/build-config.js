/**
 * Configurazione per il build system ottimizzato
 * Gestisce l'importazione modulare dei componenti
 */

// Configurazione per i moduli JavaScript
export const jsModules = {
    // Utilità
    'utils/dom-helpers': './utils/dom-helpers.js',
    'utils/validation': './utils/validation.js',
    'utils/tracking': './utils/tracking.js',
    
    // Componenti
    'components/form-state': './components/form-state.js',
    'components/form-validation': './components/form-validation.js',
    'components/form-navigation': './components/form-navigation.js',
    
    // App principale
    'form-app-optimized': './form-app-optimized.js'
};

// Configurazione per i moduli CSS
export const cssModules = {
    // Componenti
    'components/buttons': './components/buttons.css',
    'components/cards': './components/cards.css',
    'components/modals': './components/modals.css',
    'components/forms': './components/forms.css',
    'components/loading': './components/loading.css',
    
    // CSS principale ottimizzato
    'admin-optimized': './admin-optimized.css'
};

// Configurazione per il bundling
export const buildConfig = {
    // Entry points
    entries: {
        'form-app': './form-app-optimized.js',
        'admin-style': './admin-optimized.css'
    },
    
    // Output
    output: {
        js: 'dist/fe/',
        css: 'dist/css/'
    },
    
    // Ottimizzazioni
    optimizations: {
        minify: true,
        sourcemaps: true,
        treeshaking: true
    }
};

// Funzione per generare gli import dinamici
export function generateDynamicImports() {
    const jsImports = Object.entries(jsModules)
        .map(([name, path]) => `import { ${name} } from '${path}';`)
        .join('\n');
    
    const cssImports = Object.entries(cssModules)
        .map(([name, path]) => `@import url('${path}');`)
        .join('\n');
    
    return {
        js: jsImports,
        css: cssImports
    };
}

// Funzione per verificare le dipendenze
export function checkDependencies() {
    const requiredFiles = [
        ...Object.values(jsModules),
        ...Object.values(cssModules)
    ];
    
    const missingFiles = requiredFiles.filter(file => {
        // Qui dovresti implementare la verifica dell'esistenza del file
        return false; // Placeholder
    });
    
    if (missingFiles.length > 0) {
        console.warn('File mancanti:', missingFiles);
        return false;
    }
    
    return true;
}
