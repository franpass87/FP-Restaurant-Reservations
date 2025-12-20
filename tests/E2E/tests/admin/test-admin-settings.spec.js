import { test, expect } from '@playwright/test';

test.describe('Admin Settings Page', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('http://fp-development.local/wp-admin/', { waitUntil: 'domcontentloaded' });
    
    // Wait for login form
    await page.waitForSelector('#user_login', { timeout: 30000 });
    
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    
    // Wait for login to complete - check for admin bar or dashboard
    await page.waitForSelector('#wpadminbar, .wp-heading-inline', { timeout: 30000 });
  });

  test('should load settings page', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-settings', { waitUntil: 'domcontentloaded' });
    
    // Wait for page content
    await page.waitForSelector('h1', { timeout: 30000 });
    
    // Verify page loads
    await expect(page.locator('h1:has-text("Impostazioni generali")')).toBeVisible({ timeout: 10000 });
    
    // Verify form is present
    await expect(page.locator('form[method="post"]')).toBeVisible();
    
    // Verify nonce is present (hidden input, so check for attachment, not visibility)
    const nonce = page.locator('input[name="_wpnonce"]').first();
    await expect(nonce).toBeAttached();
    const nonceValue = await nonce.inputValue();
    expect(nonceValue.length).toBeGreaterThan(0);
  });

  test('should have no critical console errors', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-settings', { waitUntil: 'domcontentloaded' });
    
    // Wait for page content instead of networkidle (which might never complete)
    await page.waitForSelector('form[method="post"]', { timeout: 30000 });
    await page.waitForTimeout(2000); // Give time for any async errors to appear
    
    // Filter out known non-critical errors
    const criticalErrors = consoleErrors.filter(err => 
      !err.includes('admin-ajax.php') && 
      !err.includes('wp-compression-test') &&
      !err.includes('dashboard-widgets') &&
      !err.includes('Failed to load resource') && // Network errors from WordPress core widgets
      !err.includes('JQMIGRATE') // jQuery migrate warnings
    );
    
    // Log for debugging if there are critical errors
    if (criticalErrors.length > 0) {
      console.log('Critical errors found:', criticalErrors);
    }
    
    expect(criticalErrors.length).toBe(0);
  });
});
