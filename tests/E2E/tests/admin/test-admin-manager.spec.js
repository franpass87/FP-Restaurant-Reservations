import { test, expect } from '@playwright/test';

test.describe('Admin Manager Page', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('http://fp-development.local/wp-admin/', { waitUntil: 'domcontentloaded' });
    
    // Wait for login form
    await page.waitForSelector('#user_login', { timeout: 30000 });
    
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    
    // Wait for login to complete
    await page.waitForSelector('#wpadminbar, .wp-heading-inline', { timeout: 30000 });
  });

  test('should load manager page', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager', { waitUntil: 'domcontentloaded' });
    
    // Wait for page to load with longer timeout
    await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
    
    // Verify page title
    await expect(page.locator('h1:has-text("Manager Prenotazioni")')).toBeVisible();
    
    // Verify UI elements are present
    await expect(page.locator('button:has-text("Nuova Prenotazione")')).toBeVisible();
    await expect(page.locator('button:has-text("Esporta")')).toBeVisible();
  });

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
    
    // Wait for API calls to complete
    await page.waitForTimeout(3000);
    
    // Check for JSON parsing errors
    const jsonErrors = consoleErrors.filter(err => 
      err.includes('No number after minus sign') || 
      err.includes('JSON.parse')
    );
    
    // This test will fail if the JSON parsing issue exists
    expect(jsonErrors.length).toBe(0);
  });

  test('should have working view switcher', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager');
    await page.waitForLoadState('networkidle');
    
    // Verify view buttons exist
    await expect(page.locator('button:has-text("Giorno")')).toBeVisible();
    await expect(page.locator('button:has-text("Settimana")')).toBeVisible();
    await expect(page.locator('button:has-text("Mese")')).toBeVisible();
  });
});
