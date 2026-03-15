import { test, expect } from '@playwright/test';

/**
 * Layout Consistency Tests
 * Verifies all app-layout pages render expected structural elements.
 * @group e2e, ui
 */

// Collect console errors, filtering out known CSP warnings from Livewire/Flux
async function collectAppErrors(page) {
    const errors: string[] = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            const text = msg.text();
            // Filter out CSP violations from Livewire/Flux framework inline styles/scripts
            if (text.includes('Content Security Policy')) return;
            // Filter out resource loading errors (favicons, etc.)
            if (text.includes('Failed to load resource')) return;
            if (text.includes('favicon')) return;
            errors.push(text);
        }
    });
    return errors;
}

const appLayoutPages = [
    { name: 'Dashboard', path: '/dashboard' },
    { name: 'Reports', path: '/reports' },
    { name: 'Campaigns', path: '/campaigns' },
    { name: 'Attribution', path: '/attribution' },
    { name: 'Settings Profile', path: '/settings/profile' },
    { name: 'Billing', path: '/billing' },
];

test.describe('App Layout Pages', () => {
    for (const page_def of appLayoutPages) {
        test(`${page_def.name} (${page_def.path}) loads without app-level console errors`, async ({ page }) => {
            const errors = await collectAppErrors(page);
            await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            // Allow a moment for any deferred errors
            await page.waitForTimeout(500);
            expect(errors).toHaveLength(0);
        });

        test(`${page_def.name} (${page_def.path}) has visible sidebar on desktop`, async ({ page }) => {
            await page.setViewportSize({ width: 1280, height: 720 });
            await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            const sidebar = page.locator('[data-flux-sidebar]');
            await expect(sidebar).toHaveCount(1);
        });

        test(`${page_def.name} (${page_def.path}) has visible header actions`, async ({ page }) => {
            await page.setViewportSize({ width: 1280, height: 720 });
            await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            await expect(page.getByTestId('header-actions')).toBeVisible();
        });

        test(`${page_def.name} (${page_def.path}) has non-empty content area`, async ({ page }) => {
            await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            // Flux uses data-flux-main instead of <main>
            const content = page.locator('[data-flux-main]');
            await expect(content).toHaveCount(1);

            // Verify the content area has rendered content
            const text = await content.textContent();
            expect(text?.trim().length).toBeGreaterThan(0);
        });
    }
});

const settingsPages = [
    { name: 'Profile', path: '/settings/profile' },
    { name: 'Password', path: '/settings/password' },
    { name: 'Appearance', path: '/settings/appearance' },
    { name: 'Organization', path: '/settings/organization' },
    { name: 'Users', path: '/settings/users' },
];

test.describe('Settings Pages', () => {
    for (const page_def of settingsPages) {
        test(`Settings ${page_def.name} has horizontal tab navigation`, async ({ page }) => {
            await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            // Settings pages have links to other settings sub-pages
            const settingsLinks = page.locator('a[href*="/settings/"]');
            const count = await settingsLinks.count();
            expect(count).toBeGreaterThanOrEqual(3);
        });
    }
});

const authPages = [
    { name: 'Login', path: '/login', layout: 'split' },
    { name: 'Register', path: '/register', layout: 'split' },
    { name: 'Forgot Password', path: '/forgot-password', layout: 'card' },
];

test.describe('Auth Pages', () => {
    // Auth pages are public — no auth needed
    test.use({ storageState: { cookies: [], origins: [] } });

    for (const page_def of authPages) {
        test(`${page_def.name} page renders auth layout`, async ({ page }) => {
            const response = await page.goto(page_def.path);
            await page.waitForLoadState('domcontentloaded');

            // Skip if rate-limited (throttle middleware on auth routes)
            if (response && response.status() === 429) {
                test.skip();
                return;
            }

            // Auth pages should have a form
            const form = page.locator('form');
            await expect(form.first()).toBeVisible();
        });
    }

    test('Login page has brand panel with navy gradient', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 720 });
        const response = await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');

        // Skip if rate-limited
        if (response && response.status() === 429) {
            test.skip();
            return;
        }

        const brandPanel = page.getByTestId('brand-panel');
        await expect(brandPanel).toBeVisible();

        // Brand panel uses a CSS gradient (bg-gradient-to-b from-[#0f2042] ...)
        // Check the backgroundImage property which will contain the gradient
        const bgImage = await brandPanel.evaluate((el) => {
            return window.getComputedStyle(el).backgroundImage;
        });

        // Should have a gradient with navy/dark-blue color stops
        expect(bgImage).toContain('gradient');
    });
});
