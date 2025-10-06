/**
 * Test script per verificare il build
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

console.log('🚀 Test build script avviato...');

// Verifica file di input
const optimizedPath = path.resolve(__dirname, 'fe/form-app-optimized.js');
const fallbackPath = path.resolve(__dirname, 'fe/form-app-fallback.js');

console.log('📋 Verifica file di input:');
console.log(`   ${fs.existsSync(optimizedPath) ? '✅' : '❌'} form-app-optimized.js`);
console.log(`   ${fs.existsSync(fallbackPath) ? '✅' : '❌'} form-app-fallback.js`);

// Verifica file di output
const distDir = path.resolve(__dirname, '../dist');
const outputOptimized = path.resolve(distDir, 'fe/form-app-optimized.js');
const outputFallback = path.resolve(distDir, 'fe/form-app-fallback.js');

console.log('\n📦 Verifica file di output:');
console.log(`   ${fs.existsSync(outputOptimized) ? '✅' : '❌'} dist/fe/form-app-optimized.js`);
console.log(`   ${fs.existsSync(outputFallback) ? '✅' : '❌'} dist/fe/form-app-fallback.js`);

// Crea directory se non esiste
if (!fs.existsSync(distDir)) {
    fs.mkdirSync(distDir, { recursive: true });
    console.log('📁 Creata directory dist/');
}

// Copia file se necessario
if (fs.existsSync(optimizedPath) && !fs.existsSync(outputOptimized)) {
    const outputDir = path.dirname(outputOptimized);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }
    fs.copyFileSync(optimizedPath, outputOptimized);
    console.log('✅ Copiato form-app-optimized.js');
}

if (fs.existsSync(fallbackPath) && !fs.existsSync(outputFallback)) {
    const outputDir = path.dirname(outputFallback);
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }
    fs.copyFileSync(fallbackPath, outputFallback);
    console.log('✅ Copiato form-app-fallback.js');
}

console.log('\n✅ Test build completato!');
