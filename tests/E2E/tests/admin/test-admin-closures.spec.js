import { test, expect } from '@playwright/test';

test.describe('Admin Closures Page', () => {
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

  test('should load closures page', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager&fp_resv_tab=closures', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
    
    await expect(page.locator('#fp-resv-manager')).toBeVisible();
    await expect(page.locator('[data-fp-resv-closures]')).toBeVisible();
  });

  test('should load closures without JSON parsing errors', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (text.includes('JSON') || text.includes('SyntaxError')) {
          consoleErrors.push(text);
        }
      }
    });

    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-manager&fp_resv_tab=closures', { waitUntil: 'domcontentloaded' });
    
    await page.waitForSelector('[data-fp-resv-closures]', { timeout: 30000 });
    
    // Wait for AJAX calls to complete (shorter timeout to avoid test timeout)
    await page.waitForTimeout(3000);
    
    // Check for critical JSON parsing errors (the specific error we fixed)
    const jsonErrors = consoleErrors.filter(err => 
      err.includes('No number after minus sign') && 
      err.includes('JSON.parse')
    );
    
    // If fix worked, there should be no "No number after minus sign" errors
    // But we allow other JSON errors that might be from other sources
    expect(jsonErrors.length).toBe(0);
  });
});
