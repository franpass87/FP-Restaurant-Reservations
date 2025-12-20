import { test, expect } from '@playwright/test';

test.describe('Debug Session - Runtime Evidence Collection', () => {
  test('collect runtime evidence from manager and closures pages', async ({ page }) => {
    test.setTimeout(120000); // 2 minutes timeout
    // Step 1: Login
    await page.goto('http://fp-development.local/wp-admin/', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#user_login', { timeout: 30000 });
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar, .wp-heading-inline', { timeout: 30000 });
    
    // Step 2: Visit Manager page to trigger handleAgendaV2
    console.log('[DEBUG] Visiting Manager page...');
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager', { 
      waitUntil: 'domcontentloaded' 
    });
    
    // Wait for page to fully load and API calls to complete
    await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
    await page.waitForTimeout(5000); // Wait for API calls
    
    // Step 3: Visit Closures page to trigger handleList
    console.log('[DEBUG] Visiting Closures page...');
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-closures-app', { 
      waitUntil: 'domcontentloaded',
      timeout: 60000
    });
    
    // Wait for page to fully load and AJAX calls to complete
    await page.waitForSelector('h1', { timeout: 30000 });
    
    // Wait for AJAX calls to complete - check for network requests
    await page.waitForResponse(response => 
      response.url().includes('admin-ajax.php') && 
      response.url().includes('fp_resv_closures_list'),
      { timeout: 10000 }
    ).catch(() => {
      console.log('[DEBUG] No AJAX response detected, waiting anyway...');
    });
    
    await page.waitForTimeout(5000); // Additional wait for AJAX calls
    
    // Step 4: Check console for JavaScript errors on Closures page
    console.log('[DEBUG] Checking console for JavaScript errors...');
    const consoleMessages = [];
    page.on('console', msg => {
      if (msg.type() === 'error' || msg.type() === 'warning') {
        consoleMessages.push({ type: msg.type(), text: msg.text() });
      }
    });
    
    // Step 5: Try to trigger a reservation creation via REST API
    console.log('[DEBUG] Testing reservation creation via REST API...');
    try {
      const response = await page.request.post('http://fp-development.local/wp-json/fp-resv/v1/reservations', {
        data: {
          email: 'test@example.com',
          first_name: 'Test',
          last_name: 'User',
          date: '2025-12-15',
          time: '20:00',
          party: 2,
          phone: '+39123456789',
          language: 'it'
        },
        headers: {
          'Content-Type': 'application/json'
        }
      });
      console.log('[DEBUG] Reservation creation response status:', response.status());
    } catch (error) {
      console.log('[DEBUG] Reservation creation failed:', error.message);
    }
    
    // Additional wait to ensure all logs are written
    await page.waitForTimeout(3000);
    
    console.log('[DEBUG] Test completed. Logs should be available in .cursor/debug.log');
    if (consoleMessages.length > 0) {
      console.log('[DEBUG] Console errors/warnings:', consoleMessages);
    }
  });
});

