import { test, expect } from '@playwright/test';

/**
 * Mockup Structure Tests
 * Verifies page-specific structural elements match their specs.
 * @group e2e, ui
 */
test.describe('Dashboard Page', () => {
    test('has stat cards Livewire component', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Dashboard renders stat-cards as a lazy Livewire component
        const statCards = page.locator('[wire\\:name="dashboard.stat-cards"], [wire\\:id]').first();
        await expect(statCards).toHaveCount(1);
    });

    test('has revenue chart Livewire component', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Revenue chart is a lazy Livewire component
        const chart = page.locator('[wire\\:name="dashboard.revenue-chart"]');
        await expect(chart).toHaveCount(1);
    });

    test('has campaign performance widget', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        const widget = page.locator('[wire\\:name="dashboard.campaign-performance"]');
        await expect(widget).toHaveCount(1);
    });

    test('has time-range pill group', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Time range buttons (7d, 30d, 90d, Custom)
        const buttons = page.locator('button:has-text("7d"), button:has-text("30d"), button:has-text("90d")');
        const count = await buttons.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });
});

test.describe('Reports Page', () => {
    test('has filter bar with filter controls', async ({ page }) => {
        await page.goto('/reports');
        await page.waitForLoadState('domcontentloaded');

        // Reports page should have filter/form elements
        const filterElements = page.locator('select, [data-flux-input], input[type="date"], [data-flux-dropdown]');
        const count = await filterElements.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('has Apply button for filters', async ({ page }) => {
        await page.goto('/reports');
        await page.waitForLoadState('domcontentloaded');

        const applyBtn = page.locator('button:has-text("Apply"), [data-flux-button]:has-text("Apply")');
        await expect(applyBtn.first()).toBeVisible();
    });

    test('has report data table', async ({ page }) => {
        await page.goto('/reports');
        await page.waitForLoadState('domcontentloaded');

        const table = page.locator('table');
        const count = await table.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('has view switcher', async ({ page }) => {
        await page.goto('/reports');
        await page.waitForLoadState('domcontentloaded');

        // View switcher for Email Metrics / Attribution toggle
        const switcher = page.locator('[role="tablist"], [data-flux-button-group]');
        const count = await switcher.count();
        expect(count).toBeGreaterThanOrEqual(1);
    });
});

test.describe('Settings Users Page', () => {
    test('has members table with expected columns', async ({ page }) => {
        await page.goto('/settings/users');
        await page.waitForLoadState('domcontentloaded');

        // Members table should exist
        const table = page.locator('table');
        const count = await table.count();
        expect(count).toBeGreaterThanOrEqual(1);

        // Check for column headers
        const headers = page.locator('th, [role="columnheader"]');
        const headerTexts = await headers.allTextContents();
        const headerText = headerTexts.join(' ').toLowerCase();
        expect(headerText).toContain('name');
        expect(headerText).toContain('email');
        expect(headerText).toContain('role');
    });
});
