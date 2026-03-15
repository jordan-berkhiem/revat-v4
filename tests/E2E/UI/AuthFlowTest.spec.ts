import { test, expect } from '@playwright/test';

/**
 * Authentication Flow Tests
 * Tests login, logout, register, and forgot password pages.
 * Runs WITHOUT storageState since these test unauthenticated flows.
 *
 * Note: Auth routes have rate limiting (throttle middleware). Tests that
 * only render pages (GET requests) are ordered first to minimize rate
 * limit consumption before form submission tests.
 *
 * @group e2e, ui, auth
 */
test.describe('Authentication Flows', () => {
    // Clear auth state so we're unauthenticated
    test.use({ storageState: { cookies: [], origins: [] } });

    // --- Page render tests first (minimal rate limit impact) ---

    test('Register page renders with name, email, password fields', async ({ page }) => {
        const response = await page.goto('/register');
        await page.waitForLoadState('domcontentloaded');

        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();

        // Verify form fields
        await expect(page.getByLabel('Full name')).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Confirm password')).toBeVisible();

        // Verify submit button
        await expect(page.getByRole('button', { name: 'Create account' })).toBeVisible();

        // Verify link to login
        await expect(page.getByText('Log in')).toBeVisible();
    });

    test('Forgot password page renders with email field', async ({ page }) => {
        const response = await page.goto('/forgot-password');
        await page.waitForLoadState('domcontentloaded');

        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Forgot your password?' })).toBeVisible();

        // Verify email input
        await expect(page.getByLabel('Email address')).toBeVisible();

        // Verify submit button
        await expect(page.getByRole('button', { name: 'Send reset link' })).toBeVisible();

        // Verify back to login link
        await expect(page.getByText('Back to login')).toBeVisible();
    });

    test('Login page renders with email and password fields', async ({ page }) => {
        const response = await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');

        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        // Verify heading
        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

        // Verify email and password inputs exist
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();

        // Verify submit button
        await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();

        // Verify links
        await expect(page.getByText('Forgot password?')).toBeVisible();
        await expect(page.getByText('Register')).toBeVisible();
    });

    // --- Form submission tests (higher rate limit impact) ---

    test('Can login with valid credentials and redirect to dashboard', async ({ page }) => {
        const response = await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');

        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        // Fill in login form
        await page.getByLabel('Email address').fill('owner@acmemarketing.test');
        await page.getByLabel('Password').fill('password');

        // Submit
        await page.getByRole('button', { name: 'Log in' }).click();

        // Wait for redirect to dashboard
        await page.waitForURL('**/dashboard', { timeout: 15000 });
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('Login fails with wrong password and shows error message', async ({ page }) => {
        const response = await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');

        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        // Fill in login form with wrong password
        await page.getByLabel('Email address').fill('owner@acmemarketing.test');
        await page.getByLabel('Password').fill('wrongpassword');

        // Submit
        await page.getByRole('button', { name: 'Log in' }).click();

        // Wait for error message to appear (may appear in both callout and inline error)
        await expect(page.getByText('These credentials do not match our records').first()).toBeVisible({ timeout: 10000 });

        // Should still be on login page
        await expect(page).toHaveURL(/\/login/);
    });

    test('Logout works — redirects away from dashboard', async ({ page }) => {
        // Set desktop viewport first
        await page.setViewportSize({ width: 1280, height: 720 });

        // Log in via the test route (user 2 = owner@acmemarketing.test with org access)
        await page.goto('/_test/login/2');
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        await page.waitForLoadState('domcontentloaded');

        // Verify we're authenticated and header actions are visible
        await expect(page.getByTestId('header-actions')).toBeVisible();

        // Open user menu dropdown
        const userMenuButton = page.getByTestId('user-menu').locator('button').first();
        await userMenuButton.click();
        await page.waitForTimeout(500);

        // Find and click "Sign out"
        const signOutButton = page.getByText('Sign out');
        await expect(signOutButton).toBeVisible({ timeout: 3000 });
        await signOutButton.click();

        // Logout redirects to the root URL
        await page.waitForTimeout(3000);
        const currentUrl = page.url();
        // Should have left the dashboard
        expect(currentUrl).not.toContain('/dashboard');

        // Verify we're logged out by navigating to a protected page
        await page.goto('/dashboard');
        await page.waitForURL('**/login', { timeout: 10000 });
        await expect(page).toHaveURL(/\/login/);
    });
});
