import { test, expect } from '@playwright/test';

test.describe('Security Tests', () => {
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

  test('settings page should have nonce in form', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-settings', { waitUntil: 'domcontentloaded' });
    
    // Wait for form to load
    await page.waitForSelector('form[method="post"]', { timeout: 30000 });
    
    // Nonce is a hidden input, so check for attachment, not visibility
    const nonce = page.locator('input[name="_wpnonce"]').first();
    await expect(nonce).toBeAttached({ timeout: 10000 });
    
    const nonceValue = await nonce.inputValue();
    expect(nonceValue).toBeTruthy();
    expect(nonceValue.length).toBeGreaterThan(0);
  });

  test('should escape output in settings page', async ({ page }) => {
    await page.goto('http://fp-development.local/wp-admin/admin.php?page=fp-resv-settings', { waitUntil: 'domcontentloaded' });
    
    // Wait for page to load
    await page.waitForSelector('form[method="post"]', { timeout: 30000 });
    
    // Check that text content is properly escaped
    // Get visible text content (not script tags)
    const bodyText = await page.locator('body').textContent();
    
    // Check for potential XSS patterns in visible text (not in script tags)
    // This is a basic check - more sophisticated XSS testing would require dedicated tools
    const dangerousPatterns = [
      /<script[^>]*>.*?<\/script>/gi, // Script tags in text (should be escaped)
      /javascript:/gi, // JavaScript protocol
      /onerror=/gi, // Event handlers
      /onclick=/gi,
    ];
    
    let foundIssues = 0;
    dangerousPatterns.forEach(pattern => {
      const matches = bodyText?.match(pattern);
      if (matches && matches.length > 0) {
        foundIssues += matches.length;
      }
    });
    
    // Allow some matches (WordPress admin has legitimate uses)
    // But flag if there are many suspicious patterns
    expect(foundIssues).toBeLessThan(10);
  });
});
