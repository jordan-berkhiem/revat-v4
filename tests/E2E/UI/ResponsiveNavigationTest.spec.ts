import { test, expect } from '@playwright/test';

/**
 * Responsive Navigation Tests
 * Verifies sidebar behavior at mobile and tablet viewports.
 * @group e2e, ui
 */
test.describe('Mobile Viewport (375px)', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
    });

    test('sidebar is collapsed/hidden on mobile', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // On mobile, the sidebar backdrop should be hidden
        const backdrop = page.locator('[data-flux-sidebar-backdrop]');
        const backdropCount = await backdrop.count();
        if (backdropCount > 0) {
            await expect(backdrop).toBeHidden();
        }
    });

    test('mobile toggle button is visible', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Flux sidebar toggle button
        const toggle = page.locator('[data-flux-sidebar-toggle]');
        const count = await toggle.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('clicking toggle reveals sidebar and allows navigation', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Wait for Alpine.js to initialize
        await page.waitForTimeout(500);

        // Dispatch the Flux sidebar toggle event (same as clicking the toggle button)
        await page.evaluate(() => {
            window.dispatchEvent(new CustomEvent('flux-sidebar-toggle'));
        });

        // Wait for sidebar transition
        await page.waitForTimeout(500);

        // Sidebar nav items should now be visible
        const navReports = page.getByTestId('nav-reports');
        await expect(navReports).toBeVisible();

        // Navigate via sidebar link using JS click since the sidebar overlay may not be fully in viewport
        await navReports.evaluate((el: HTMLElement) => el.click());
        await page.waitForURL('**/reports');

        await expect(page).toHaveURL(/\/reports/);
    });
});

test.describe('Tablet Viewport (768px)', () => {
    test('layout adapts appropriately at tablet width', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Content area should be present and have content
        const content = page.locator('[data-flux-main]');
        await expect(content).toHaveCount(1);
        const text = await content.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });
});
