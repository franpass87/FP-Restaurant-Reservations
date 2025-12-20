import { test, expect } from '@playwright/test';

test.describe('Admin Login', () => {
  test('should login to WordPress admin', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/');
    
    // Fill login form
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForURL('**/wp-admin/**');
    
    // Verify we're logged in
    await expect(page.locator('#wpadminbar')).toBeVisible();
    // Use more specific selector to avoid strict mode violation
    await expect(page.locator('a.wp-first-item:has-text("FP Reservations")')).toBeVisible();
  });
});
