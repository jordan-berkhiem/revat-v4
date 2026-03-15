import { test, expect } from '@playwright/test';

/**
 * Settings Pages Tests
 * Verifies all settings pages render correctly with expected form elements.
 * @group e2e, ui, settings
 */
test.describe('Settings Pages', () => {
    test('Profile page renders with name and email fields', async ({ page }) => {
        await page.goto('/settings/profile');
        await page.waitForLoadState('domcontentloaded');

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Profile' })).toBeVisible();

        // Verify form fields
        await expect(page.getByLabel('Name')).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();

        // Verify the fields are pre-filled (user is logged in)
        const nameValue = await page.getByLabel('Name').inputValue();
        expect(nameValue.length).toBeGreaterThan(0);

        const emailValue = await page.getByLabel('Email address').inputValue();
        expect(emailValue).toContain('@');

        // Verify save button
        await expect(page.getByRole('button', { name: 'Save changes' })).toBeVisible();
    });

    test('Password page renders with current password, new password, and confirm fields', async ({ page }) => {
        await page.goto('/settings/password');
        await page.waitForLoadState('domcontentloaded');

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Password' })).toBeVisible();

        // Verify form fields
        await expect(page.getByLabel('Current password')).toBeVisible();
        await expect(page.getByLabel('New password', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Confirm new password')).toBeVisible();

        // Verify submit button
        await expect(page.getByRole('button', { name: 'Update password' })).toBeVisible();
    });

    test('Password form renders and fields are fillable (without submitting)', async ({ page }) => {
        await page.goto('/settings/password');
        await page.waitForLoadState('domcontentloaded');

        // Click and type into form fields like a real user
        await page.getByLabel('Current password').click();
        await page.getByLabel('Current password').pressSequentially('password', { delay: 30 });
        await page.getByLabel('New password', { exact: true }).click();
        await page.getByLabel('New password', { exact: true }).pressSequentially('newpassword123', { delay: 30 });
        await page.getByLabel('Confirm new password').click();
        await page.getByLabel('Confirm new password').pressSequentially('newpassword123', { delay: 30 });

        // Verify fields accepted input
        await expect(page.getByLabel('Current password')).toHaveValue('password');
        await expect(page.getByLabel('New password', { exact: true })).toHaveValue('newpassword123');
        await expect(page.getByLabel('Confirm new password')).toHaveValue('newpassword123');

        // Do NOT submit — this would change the password and break other tests
    });

    test('Appearance page renders with light, dark, and system options', async ({ page }) => {
        await page.goto('/settings/appearance');
        await page.waitForLoadState('domcontentloaded');

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Appearance' })).toBeVisible();

        // Verify theme options
        await expect(page.getByTestId('theme-light')).toBeVisible();
        await expect(page.getByTestId('theme-dark')).toBeVisible();
        await expect(page.getByTestId('theme-system')).toBeVisible();
    });

    test('Organization page renders with org name and timezone', async ({ page }) => {
        await page.goto('/settings/organization');
        await page.waitForLoadState('domcontentloaded');

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Organization' })).toBeVisible();

        // Verify organization name field
        await expect(page.getByLabel('Organization name')).toBeVisible();

        // The org name should be pre-filled
        const orgName = await page.getByLabel('Organization name').inputValue();
        expect(orgName.length).toBeGreaterThan(0);

        // Verify timezone field
        await expect(page.getByLabel('Timezone')).toBeVisible();

        // Verify save button
        await expect(page.getByRole('button', { name: 'Save changes' })).toBeVisible();
    });

    test('Security page renders', async ({ page }) => {
        await page.goto('/settings/security');
        await page.waitForLoadState('domcontentloaded');

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Account Security' })).toBeVisible();

        // Verify two-factor authentication section
        await expect(page.getByRole('heading', { name: 'Two-Factor Authentication' })).toBeVisible();
    });

    test('Users page renders', async ({ page }) => {
        await page.goto('/settings/users');
        await page.waitForLoadState('domcontentloaded');

        // Verify we're on the users page — it should have settings tabs
        const settingsLinks = page.locator('a[href*="/settings/"]');
        const count = await settingsLinks.count();
        expect(count).toBeGreaterThanOrEqual(3);

        // The page should have content
        const content = page.locator('[data-flux-main]');
        const text = await content.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });

    test('Workspaces page renders', async ({ page }) => {
        await page.goto('/settings/workspaces');
        await page.waitForLoadState('domcontentloaded');

        // Verify we're on the workspaces page
        const settingsLinks = page.locator('a[href*="/settings/"]');
        const count = await settingsLinks.count();
        expect(count).toBeGreaterThanOrEqual(3);

        // The page should have content
        const content = page.locator('[data-flux-main]');
        const text = await content.textContent();
        expect(text?.trim().length).toBeGreaterThan(0);
    });

    test('Support access page renders', async ({ page }) => {
        const response = await page.goto('/settings/support-access');
        await page.waitForLoadState('domcontentloaded');

        // Verify page loads successfully
        expect(response?.status()).toBeLessThan(500);
        await expect(page.locator('body')).not.toBeEmpty();
    });
});
