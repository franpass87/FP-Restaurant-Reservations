import { test, expect } from '@playwright/test';

test.describe('Admin agenda drag & drop', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
      const json = {
        days: [
          {
            date: '2024-05-20',
            reservations: [
              {
                id: 101,
                time: '19:00',
                party: 2,
                room_id: 1,
                table_id: 11,
                status: 'confirmed',
                customer: { name: 'Ada Lovelace', phone: '+39 055 123456' },
              },
            ],
          },
        ],
        tables: [
          { id: 11, room_id: 1, label: 'Tavolo 11', capacity: 4 },
          { id: 12, room_id: 1, label: 'Tavolo 12', capacity: 4 },
        ],
      };

      await route.fulfill({ json });
    });

    await page.route('**/wp-json/fp-resv/v1/reservations/101/move', async (route, request) => {
      const body = JSON.parse(request.postData() ?? '{}');
      expect(body.table_id).toBe(12);
      expect(body.time).toBe('19:30');
      await route.fulfill({ json: { success: true } });
    });
  });

  test('moves a reservation to a new slot and persists the change', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

    await expect(page.locator('[data-reservation-id="101"]').filter({ hasText: 'Ada Lovelace' })).toBeVisible();

    const source = page.locator('[data-reservation-id="101"]');
    const target = page.locator('[data-slot="2024-05-20T19:30:00"][data-table="12"]');

    await source.dragTo(target, { sourcePosition: { x: 5, y: 5 }, targetPosition: { x: 10, y: 10 } });

    await expect(page.locator('[data-reservation-id="101"]').filter({ hasText: '19:30' })).toBeVisible();
  });
});
