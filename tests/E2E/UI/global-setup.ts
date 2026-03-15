import { test as setup, expect } from '@playwright/test';

const authFile = './tests/E2E/UI/.auth/user.json';

/**
 * Global setup: seeds the test database and authenticates via the
 * dev-only /_test/login/:userId route, then persists session cookies
 * so subsequent tests can reuse the authenticated state.
 *
 * Uses user ID 2 (owner@acmemarketing.test) — the Acme Marketing org owner
 * created by FoundationSeeder. This user has the 'owner' role with all
 * permissions (billing, manage, integrate, view), an active organization,
 * and workspace access. User ID 1 (test@example.com) has no organization
 * or roles and gets redirected by the onboarded/organization middleware.
 */
setup('authenticate', async ({ page }) => {
    // User 2 = owner@acmemarketing.test (owner role in Acme Marketing org)
    // Created by TestDataSeeder -> FoundationSeeder
    await page.goto('/_test/login/2');
    await page.waitForURL('**/dashboard');

    // Verify we are authenticated
    await expect(page).toHaveURL(/\/dashboard/);

    // Save storage state
    await page.context().storageState({ path: authFile });
});
