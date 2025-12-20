# Debug Manager JSON Parsing Issue

**Problema**: Test `should load reservations without JSON parsing errors` fallisce nella pagina Manager.

**Stato**: In investigazione

## Test che fallisce

```javascript
test('should load reservations without JSON parsing errors', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const text = msg.text();
      if (text.includes('JSON') || text.includes('SyntaxError')) {
        consoleErrors.push(text);
      }
    }
  });

  await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager');
  await page.waitForTimeout(3000);
  
  const jsonErrors = consoleErrors.filter(err => 
    err.includes('No number after minus sign') || 
    err.includes('JSON.parse')
  );
  
  expect(jsonErrors.length).toBe(0); // FALLISCE: riceve 1 errore
});
```

## Possibili cause

1. **Output prima della risposta REST**: Output buffer non completamente pulito prima di inviare risposta JSON
2. **Warning PHP**: Warning o notice PHP che vengono emessi prima della risposta
3. **Hook WordPress**: Hook o filter che emettono output durante la chiamata REST

## Fix applicati

1. ✅ Migliorata pulizia output buffer in `AgendaHandler.php`:
   ```php
   // Clean ALL output buffers before returning
   while (ob_get_level() > 0) {
       ob_end_clean();
   }
   if (ob_get_level() === 0) {
       ob_start();
   }
   ```

## Prossimi passi

1. Verificare quale errore JSON specifico viene rilevato
2. Controllare se c'è output da altri plugin durante la chiamata REST
3. Verificare se WordPress REST API gestisce correttamente l'output buffer

