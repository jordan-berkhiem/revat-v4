import { test as setup, expect } from '@playwright/test';

const authFile = './tests/E2E/UI/.auth/user.json';

/**
 * Global setup: authenticates via the login form using real user behavior,
 * then persists session cookies so subsequent tests can reuse the state.
 *
 * Uses owner@acmemarketing.test — the Acme Marketing org owner created by
 * FoundationSeeder. This user has the 'owner' role with all permissions
 * (billing, manage, integrate, view), an active organization, and workspace access.
 */
setup('authenticate', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('domcontentloaded');

    // Fill login form like a real user
    await page.getByLabel('Email address').click();
    await page.getByLabel('Email address').pressSequentially('owner@acmemarketing.test', { delay: 30 });
    await page.getByLabel('Password').click();
    await page.getByLabel('Password').pressSequentially('password', { delay: 30 });

    // Submit
    await page.getByRole('button', { name: 'Log in' }).click();

    // Wait for redirect to dashboard
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await expect(page).toHaveURL(/\/dashboard/);

    // Save storage state
    await page.context().storageState({ path: authFile });
});
