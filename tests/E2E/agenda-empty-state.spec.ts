import { test, expect } from '@playwright/test';

test.describe('Agenda - Empty State Handler', () => {
  test.describe('Quando non ci sono prenotazioni', () => {
    test.beforeEach(async ({ page }) => {
      // Mock API che restituisce array vuoto
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        await route.fulfill({ json: [] });
      });
    });

    test('mostra il messaggio "Nessuna prenotazione"', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Verifica che l'empty state sia visibile
      const emptyState = page.locator('[data-role="empty"]');
      await expect(emptyState).toBeVisible();

      // Verifica il titolo
      await expect(emptyState.locator('h3')).toHaveText('Nessuna prenotazione');

      // Verifica il messaggio
      await expect(emptyState.locator('p')).toHaveText('Non ci sono prenotazioni per questo periodo');

      // Verifica che il pulsante CTA sia presente
      await expect(emptyState.locator('button[data-action="new-reservation"]')).toBeVisible();
    });

    test('nasconde il loading state', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Verifica che il loading non sia visibile
      const loadingState = page.locator('[data-role="loading"]');
      await expect(loadingState).toBeHidden();
    });

    test('nasconde tutte le viste dell\'agenda', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Verifica che tutte le viste siano nascoste
      await expect(page.locator('[data-role="timeline"]')).toBeHidden();
      await expect(page.locator('[data-role="week-view"]')).toBeHidden();
      await expect(page.locator('[data-role="month-view"]')).toBeHidden();
      await expect(page.locator('[data-role="list-view"]')).toBeHidden();
    });

    test('mostra empty state anche dopo cambio vista', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Cambia vista a settimana
      await page.click('[data-action="set-view"][data-view="week"]');
      await expect(page.locator('[data-role="empty"]')).toBeVisible();

      // Cambia vista a mese
      await page.click('[data-action="set-view"][data-view="month"]');
      await expect(page.locator('[data-role="empty"]')).toBeVisible();

      // Cambia vista a lista
      await page.click('[data-action="set-view"][data-view="list"]');
      await expect(page.locator('[data-role="empty"]')).toBeVisible();
    });

    test('mostra empty state dopo cambio data', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Aspetta che l'empty state sia visibile
      await expect(page.locator('[data-role="empty"]')).toBeVisible();

      // Cambia data
      await page.fill('[data-role="date-picker"]', '2025-12-25');

      // Verifica che l'empty state sia ancora visibile
      await expect(page.locator('[data-role="empty"]')).toBeVisible();
    });

    test('apre modal nuova prenotazione dal pulsante empty state', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Clicca sul pulsante "Crea la prima prenotazione"
      await page.click('[data-role="empty"] button[data-action="new-reservation"]');

      // Verifica che il modal sia aperto
      const modal = page.locator('[data-modal="new-reservation"]');
      await expect(modal).toBeVisible();
      await expect(modal).toHaveAttribute('aria-hidden', 'false');
    });
  });

  test.describe('Gestione errori API', () => {
    test('mostra messaggio di errore quando l\'API fallisce con 403', async ({ page }) => {
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        await route.fulfill({
          status: 403,
          json: { message: 'Forbidden' }
        });
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Verifica che l'empty state sia visibile con messaggio di errore
      const emptyState = page.locator('[data-role="empty"]');
      await expect(emptyState).toBeVisible();

      // Verifica che il messaggio contenga informazioni sull'errore
      const message = emptyState.locator('p');
      await expect(message).toContainText('Accesso negato');
    });

    test('mostra messaggio di errore quando l\'API fallisce con 404', async ({ page }) => {
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        await route.fulfill({
          status: 404,
          json: { message: 'Not found' }
        });
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      const emptyState = page.locator('[data-role="empty"]');
      await expect(emptyState).toBeVisible();

      const message = emptyState.locator('p');
      await expect(message).toContainText('Endpoint non trovato');
    });

    test('mostra messaggio di errore quando c\'è un errore di rete', async ({ page }) => {
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        await route.abort('failed');
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      const emptyState = page.locator('[data-role="empty"]');
      await expect(emptyState).toBeVisible();

      const message = emptyState.locator('p');
      await expect(message).toContainText('Errore');
    });

    test('recupera dopo errore temporaneo al cambio data', async ({ page }) => {
      let requestCount = 0;

      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        requestCount++;
        
        if (requestCount === 1) {
          // Prima richiesta fallisce
          await route.fulfill({
            status: 500,
            json: { message: 'Server error' }
          });
        } else {
          // Seconda richiesta ha successo con dati
          await route.fulfill({
            json: [{
              id: 101,
              status: 'confirmed',
              date: '2025-10-12',
              time: '19:00',
              slot_start: '2025-10-12 19:00',
              party: 2,
              customer: {
                first_name: 'Mario',
                last_name: 'Rossi',
                email: 'mario@example.com'
              }
            }]
          });
        }
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Prima mostra errore
      await expect(page.locator('[data-role="empty"]')).toBeVisible();

      // Cambia data
      await page.fill('[data-role="date-picker"]', '2025-10-12');

      // Ora dovrebbe mostrare i dati
      await expect(page.locator('[data-role="timeline"]')).toBeVisible();
      await expect(page.locator('[data-role="empty"]')).toBeHidden();
    });
  });

  test.describe('Transizioni tra stati', () => {
    test('passa da empty a dati quando arrivano prenotazioni', async ({ page }) => {
      let hasData = false;

      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        if (hasData) {
          await route.fulfill({
            json: [{
              id: 101,
              status: 'confirmed',
              date: '2025-10-12',
              time: '19:00',
              slot_start: '2025-10-12 19:00',
              party: 4,
              customer: {
                first_name: 'Luigi',
                last_name: 'Verdi',
                email: 'luigi@example.com'
              }
            }]
          });
        } else {
          await route.fulfill({ json: [] });
        }
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Inizialmente empty
      await expect(page.locator('[data-role="empty"]')).toBeVisible();
      await expect(page.locator('[data-role="timeline"]')).toBeHidden();

      // Simula arrivo dati
      hasData = true;
      await page.fill('[data-role="date-picker"]', '2025-10-12');

      // Ora mostra timeline
      await expect(page.locator('[data-role="timeline"]')).toBeVisible();
      await expect(page.locator('[data-role="empty"]')).toBeHidden();
    });

    test('passa da dati a empty quando le prenotazioni vengono rimosse', async ({ page }) => {
      let hasData = true;

      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        if (hasData) {
          await route.fulfill({
            json: [{
              id: 101,
              status: 'confirmed',
              date: '2025-10-11',
              time: '19:00',
              slot_start: '2025-10-11 19:00',
              party: 2,
              customer: {
                first_name: 'Anna',
                last_name: 'Bianchi',
                email: 'anna@example.com'
              }
            }]
          });
        } else {
          await route.fulfill({ json: [] });
        }
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Inizialmente con dati
      await page.fill('[data-role="date-picker"]', '2025-10-11');
      await expect(page.locator('[data-role="timeline"]')).toBeVisible();
      await expect(page.locator('[data-role="empty"]')).toBeHidden();

      // Simula rimozione dati
      hasData = false;
      await page.fill('[data-role="date-picker"]', '2025-10-12');

      // Ora mostra empty
      await expect(page.locator('[data-role="empty"]')).toBeVisible();
      await expect(page.locator('[data-role="timeline"]')).toBeHidden();
    });
  });

  test.describe('Filtri e empty state', () => {
    test('mostra empty state quando si filtra per servizio senza prenotazioni', async ({ page }) => {
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        const url = new URL(route.request().url());
        const service = url.searchParams.get('service');

        if (service === 'lunch') {
          // Nessuna prenotazione per pranzo
          await route.fulfill({ json: [] });
        } else {
          // Prenotazioni per cena
          await route.fulfill({
            json: [{
              id: 101,
              status: 'confirmed',
              date: '2025-10-11',
              time: '20:00',
              slot_start: '2025-10-11 20:00',
              party: 2,
              customer: {
                first_name: 'Paolo',
                last_name: 'Neri',
                email: 'paolo@example.com'
              }
            }]
          });
        }
      });

      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      // Con "Tutti i servizi" ci sono prenotazioni
      await expect(page.locator('[data-role="timeline"]')).toBeVisible();

      // Filtra per pranzo
      await page.selectOption('[data-role="service-filter"]', 'lunch');

      // Ora mostra empty state
      await expect(page.locator('[data-role="empty"]')).toBeVisible();
      await expect(page.locator('[data-role="timeline"]')).toBeHidden();
    });
  });

  test.describe('Accessibilità', () => {
    test.beforeEach(async ({ page }) => {
      await page.route('**/wp-json/fp-resv/v1/agenda**', async (route) => {
        await route.fulfill({ json: [] });
      });
    });

    test('empty state ha struttura semantica corretta', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      const emptyState = page.locator('[data-role="empty"]');
      
      // Verifica presenza heading
      await expect(emptyState.locator('h3')).toBeVisible();

      // Verifica presenza icona
      await expect(emptyState.locator('.dashicons-calendar-alt')).toBeVisible();

      // Verifica che il pulsante sia accessibile
      const button = emptyState.locator('button[data-action="new-reservation"]');
      await expect(button).toBeVisible();
      await expect(button).toBeEnabled();
    });

    test('loading state è nascosto correttamente agli screen reader', async ({ page }) => {
      await page.goto('/wp-admin/admin.php?page=fp-resv-agenda');

      const loadingState = page.locator('[data-role="loading"]');
      await expect(loadingState).toHaveAttribute('hidden', '');
    });
  });
});
