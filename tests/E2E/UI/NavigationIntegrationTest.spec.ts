import { test, expect } from '@playwright/test';

/**
 * Navigation Integration Tests
 * Verifies all sidebar nav items are present and navigate to the correct routes.
 * @group e2e, ui
 */
test.describe('Sidebar Navigation', () => {
    test('all 5 sidebar nav items are present and visible', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        await expect(page.getByTestId('nav-dashboard')).toBeVisible();
        await expect(page.getByTestId('nav-reports')).toBeVisible();
        await expect(page.getByTestId('nav-campaigns')).toBeVisible();
        await expect(page.getByTestId('nav-attribution')).toBeVisible();
        await expect(page.getByTestId('nav-settings')).toBeVisible();
    });

    test('Dashboard nav item navigates to /dashboard', async ({ page }) => {
        await page.goto('/reports');
        await page.waitForLoadState('domcontentloaded');

        await page.getByTestId('nav-dashboard').click();
        await page.waitForURL('**/dashboard');

        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('Reports nav item navigates to /reports', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        await page.getByTestId('nav-reports').click();
        await page.waitForURL('**/reports');

        await expect(page).toHaveURL(/\/reports/);
    });

    test('Campaigns nav item navigates to /campaigns', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        await page.getByTestId('nav-campaigns').click();
        await page.waitForURL('**/campaigns');

        await expect(page).toHaveURL(/\/campaigns/);
    });

    test('Attribution nav item navigates to /attribution', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        await page.getByTestId('nav-attribution').click();
        await page.waitForURL('**/attribution');

        await expect(page).toHaveURL(/\/attribution/);
    });

    test('Settings nav item navigates to /settings/profile', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        await page.getByTestId('nav-settings').click();
        await page.waitForURL('**/settings/profile');

        await expect(page).toHaveURL(/\/settings\/profile/);
    });
});
