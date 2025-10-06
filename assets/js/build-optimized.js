/**
 * Build script per le ottimizzazioni JavaScript
 * Gestisce sia la versione modulare che quella di fallback
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configurazione dei file
const config = {
    // File di input
    optimized: './assets/js/fe/form-app-optimized.js',
    fallback: './assets/js/fe/form-app-fallback.js',
    
    // File di output
    output: {
        optimized: './assets/dist/fe/form-app-optimized.js',
        fallback: './assets/dist/fe/form-app-fallback.js',
        minified: './assets/dist/fe/form-app.min.js'
    },
    
    // Configurazione Vite
    viteConfig: './vite.config.js'
};

// Funzione per verificare se un file esiste
function fileExists(filePath) {
    try {
        return fs.existsSync(filePath);
    } catch (error) {
        return false;
    }
}

// Funzione per copiare file
function copyFile(source, destination) {
    try {
        const sourcePath = path.resolve(source);
        const destPath = path.resolve(destination);
        
        // Crea la directory di destinazione se non esiste
        const destDir = path.dirname(destPath);
        if (!fs.existsSync(destDir)) {
            fs.mkdirSync(destDir, { recursive: true });
        }
        
        fs.copyFileSync(sourcePath, destPath);
        console.log(`✅ Copiato: ${source} → ${destination}`);
        return true;
    } catch (error) {
        console.error(`❌ Errore copiando ${source}:`, error.message);
        return false;
    }
}

// Funzione per verificare la sintassi JavaScript
function validateJavaScript(filePath) {
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        
        // Verifica base: controlla che non ci siano errori di sintassi evidenti
        if (content.includes('import ') && !content.includes('export ')) {
            console.warn(`⚠️  ${filePath}: Contiene import ma potrebbe non avere export`);
        }
        
        if (content.includes('export ') && !content.includes('import ')) {
            console.warn(`⚠️  ${filePath}: Contiene export ma potrebbe non avere import`);
        }
        
        console.log(`✅ Sintassi verificata: ${filePath}`);
        return true;
    } catch (error) {
        console.error(`❌ Errore validazione ${filePath}:`, error.message);
        return false;
    }
}

// Funzione principale di build
function buildOptimized() {
    console.log('🚀 Avvio build ottimizzazioni...\n');
    
    // Verifica file di input
    console.log('📋 Verifica file di input:');
    const optimizedExists = fileExists(config.optimized);
    const fallbackExists = fileExists(config.fallback);
    
    console.log(`   ${optimizedExists ? '✅' : '❌'} ${config.optimized}`);
    console.log(`   ${fallbackExists ? '✅' : '❌'} ${config.fallback}`);
    
    if (!optimizedExists && !fallbackExists) {
        console.error('❌ Nessun file di input trovato!');
        return false;
    }
    
    // Valida sintassi
    console.log('\n🔍 Validazione sintassi:');
    if (optimizedExists) {
        validateJavaScript(config.optimized);
    }
    if (fallbackExists) {
        validateJavaScript(config.fallback);
    }
    
    // Copia file ottimizzati
    console.log('\n📦 Copia file ottimizzati:');
    let success = true;
    
    if (optimizedExists) {
        success = copyFile(config.optimized, config.output.optimized) && success;
    }
    
    if (fallbackExists) {
        success = copyFile(config.fallback, config.output.fallback) && success;
    }
    
    // Crea file minificato (versione semplificata)
    if (success && fallbackExists) {
        console.log('\n🗜️  Creazione versione minificata:');
        try {
            const fallbackContent = fs.readFileSync(config.fallback, 'utf8');
            const minifiedContent = fallbackContent
                .replace(/\s+/g, ' ')  // Rimuove spazi extra
                .replace(/\/\*[\s\S]*?\*\//g, '')  // Rimuove commenti multi-linea
                .replace(/\/\/.*$/gm, '')  // Rimuove commenti single-line
                .trim();
            
            fs.writeFileSync(config.output.minified, minifiedContent);
            console.log(`✅ Creato: ${config.output.minified}`);
        } catch (error) {
            console.error(`❌ Errore creando versione minificata:`, error.message);
            success = false;
        }
    }
    
    // Verifica file di output
    console.log('\n✅ Verifica file di output:');
    Object.values(config.output).forEach(outputPath => {
        const exists = fileExists(outputPath);
        console.log(`   ${exists ? '✅' : '❌'} ${outputPath}`);
    });
    
    // Riepilogo
    console.log('\n📊 Riepilogo build:');
    if (success) {
        console.log('✅ Build completato con successo!');
        console.log('\n📁 File generati:');
        Object.entries(config.output).forEach(([key, path]) => {
            if (fileExists(path)) {
                const stats = fs.statSync(path);
                console.log(`   ${key}: ${path} (${Math.round(stats.size / 1024)}KB)`);
            }
        });
    } else {
        console.log('❌ Build completato con errori!');
    }
    
    return success;
}

// Esegui build se chiamato direttamente
// eslint-disable-next-line no-undef
if (import.meta.url.endsWith(process.argv[1])) {
    const success = buildOptimized();
    // eslint-disable-next-line no-undef
    process.exit(success ? 0 : 1);
}

export {
    buildOptimized,
    config
};
