import { test, expect } from '@playwright/test';

/**
 * Dark Mode Tests
 * Verifies the dark/light mode toggle elements and appearance settings.
 *
 * Uses the Flux Alpine magic property ($flux.appearance) for theme switching.
 * These tests verify the UI elements are present, correctly configured,
 * and that dark-mode responsive styling works.
 *
 * @group e2e, ui, appearance
 */
test.describe('Dark Mode', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 720 });
    });

    test('Light mode toggle button (sun icon) is visible in header', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // The sun icon button should be visible in light mode (it has class dark:hidden)
        const sunButton = page.getByTestId('appearance-toggle');
        await expect(sunButton).toBeVisible();

        // Verify it has the correct x-on:click handler to switch to dark mode
        const onclick = await sunButton.evaluate(el => el.getAttribute('x-on:click'));
        expect(onclick).toContain("$flux.appearance = 'dark'");
    });

    test('Dark mode toggle button (moon icon) exists and is configured for dark mode', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // The moon icon button exists (has class hidden dark:flex - visible only in dark mode)
        const moonButton = page.getByTestId('appearance-toggle-dark');
        await expect(moonButton).toBeAttached();

        // Verify it has the correct x-on:click handler to switch to light mode
        const onclick = await moonButton.evaluate(el => el.getAttribute('x-on:click'));
        expect(onclick).toContain("$flux.appearance = 'light'");
    });

    test('Dark mode class toggles dark: CSS utilities on body', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // Manually toggle dark mode via class (since CSP blocks Flux handler)
        // This verifies the Tailwind dark: utilities respond correctly
        const lightBg = await page.evaluate(() => {
            return window.getComputedStyle(document.body).backgroundColor;
        });

        // Add .dark class to html element
        await page.evaluate(() => document.documentElement.classList.add('dark'));

        // Wait for dark background to apply
        await expect.poll(async () => {
            return page.evaluate(() => window.getComputedStyle(document.body).backgroundColor);
        }).not.toBe(lightBg);

        const darkBg = await page.evaluate(() => {
            return window.getComputedStyle(document.body).backgroundColor;
        });

        // Background color should change between light and dark modes
        // Light mode: bg-white (rgb(255,255,255)), Dark mode: bg-zinc-800 (approx rgb(39,39,42))
        expect(lightBg).not.toEqual(darkBg);

        // Clean up
        await page.evaluate(() => document.documentElement.classList.remove('dark'));
    });

    test('Dark mode class shows moon button and hides sun button', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('domcontentloaded');

        // In light mode, sun should be visible
        await expect(page.getByTestId('appearance-toggle')).toBeVisible();

        // Add dark class
        await page.evaluate(() => document.documentElement.classList.add('dark'));

        // Wait for moon button to become visible in dark mode
        await expect(page.getByTestId('appearance-toggle-dark')).toBeVisible();
        await expect(page.getByTestId('appearance-toggle')).toBeHidden();

        // Clean up
        await page.evaluate(() => document.documentElement.classList.remove('dark'));
    });

    test('Settings/appearance page has light, dark, and system options', async ({ page }) => {
        await page.goto('/settings/appearance');
        await page.waitForLoadState('domcontentloaded');

        // Verify the heading
        await expect(page.getByRole('heading', { name: 'Appearance' })).toBeVisible();

        // Verify the three theme buttons via data-testid
        await expect(page.getByTestId('theme-light')).toBeVisible();
        await expect(page.getByTestId('theme-dark')).toBeVisible();
        await expect(page.getByTestId('theme-system')).toBeVisible();

        // Verify labels
        await expect(page.getByTestId('theme-light').getByText('Light')).toBeVisible();
        await expect(page.getByTestId('theme-dark').getByText('Dark')).toBeVisible();
        await expect(page.getByTestId('theme-system').getByText('System')).toBeVisible();
    });

    test('Theme buttons have correct x-on:click handlers', async ({ page }) => {
        await page.goto('/settings/appearance');
        await page.waitForLoadState('domcontentloaded');

        const lightHandler = await page.getByTestId('theme-light').evaluate(el => el.getAttribute('x-on:click'));
        expect(lightHandler).toContain("$flux.appearance = 'light'");

        const darkHandler = await page.getByTestId('theme-dark').evaluate(el => el.getAttribute('x-on:click'));
        expect(darkHandler).toContain("$flux.appearance = 'dark'");

        const systemHandler = await page.getByTestId('theme-system').evaluate(el => el.getAttribute('x-on:click'));
        expect(systemHandler).toContain("$flux.appearance = 'system'");
    });
});
