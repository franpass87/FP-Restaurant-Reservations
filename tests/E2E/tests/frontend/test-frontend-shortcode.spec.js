import { test, expect } from '@playwright/test';

test.describe('Frontend Shortcode', () => {
  test('should render shortcode on homepage if present', async ({ page }) => {
    await page.goto('http://fp-development.local/');
    await page.waitForLoadState('networkidle');
    
    // Check if shortcode form is present (if shortcode is used)
    const form = page.locator('.fp-resv-form, [class*="fp-resv"]');
    const formCount = await form.count();
    
    // If form exists, verify it's visible
    if (formCount > 0) {
      await expect(form.first()).toBeVisible();
    }
  });

  test('should have no critical console errors', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('http://fp-development.local/');
    await page.waitForLoadState('networkidle');
    
    // Filter out known non-critical errors
    const criticalErrors = consoleErrors.filter(err => 
      !err.includes('admin-ajax.php') && 
      !err.includes('wp-compression-test')
    );
    
    expect(criticalErrors.length).toBe(0);
  });
});
