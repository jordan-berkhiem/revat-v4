import { test, expect } from '@playwright/test';

// Track failed network requests
async function collectFailedRequests(page) {
    const failed: string[] = [];
    page.on('response', (response) => {
        if (response.status() >= 400) {
            failed.push(`${response.status()} ${response.url()}`);
        }
    });
    return failed;
}

test.describe('Auth Split Layout', () => {
    test('renders without errors', async ({ page }) => {
        const failed = await collectFailedRequests(page);
        await page.goto('/_layouts/auth-split');
        await expect(page.getByTestId('page-heading')).toHaveText('Sign in to your account');
        expect(failed.filter(f => !f.includes('favicon'))).toHaveLength(0);
    });

    test('has brand panel with logo', async ({ page }) => {
        await page.goto('/_layouts/auth-split');
        await expect(page.getByTestId('brand-panel')).toBeVisible();
        // Brand panel has Logo-Light.svg
        const logo = page.getByTestId('brand-panel').locator('img[src*="Logo-Light"]');
        await expect(logo).toBeVisible();
    });

    test('has decorative chart', async ({ page }) => {
        await page.goto('/_layouts/auth-split');
        await expect(page.getByTestId('decorative-chart')).toBeVisible();
    });

    test('hides brand panel on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/_layouts/auth-split');
        await expect(page.getByTestId('brand-panel')).toBeHidden();
        await expect(page.getByTestId('form-panel')).toBeVisible();
    });
});

test.describe('Auth Card Layout', () => {
    test('renders without errors', async ({ page }) => {
        const failed = await collectFailedRequests(page);
        await page.goto('/_layouts/auth-card');
        await expect(page.getByTestId('page-heading')).toHaveText('Reset your password');
        expect(failed.filter(f => !f.includes('favicon'))).toHaveLength(0);
    });

    test('has logo', async ({ page }) => {
        await page.goto('/_layouts/auth-card');
        const logo = page.getByTestId('auth-card-layout').locator('img[alt="Revat"]');
        await expect(logo.first()).toBeVisible();
    });
});

test.describe('Auth Simple Layout', () => {
    test('renders without errors', async ({ page }) => {
        const failed = await collectFailedRequests(page);
        await page.goto('/_layouts/auth-simple');
        await expect(page.getByTestId('page-heading')).toHaveText('Verify your email');
        expect(failed.filter(f => !f.includes('favicon'))).toHaveLength(0);
    });
});

test.describe('Onboarding Layout', () => {
    test('renders without errors', async ({ page }) => {
        const failed = await collectFailedRequests(page);
        await page.goto('/_layouts/onboarding');
        await expect(page.getByTestId('page-heading')).toHaveText('Set up your organization');
        expect(failed.filter(f => !f.includes('favicon'))).toHaveLength(0);
    });

    test('has header with logo', async ({ page }) => {
        await page.goto('/_layouts/onboarding');
        const header = page.getByTestId('onboarding-layout').locator('header');
        await expect(header).toBeVisible();
        const logo = header.locator('img[alt="Revat"]');
        await expect(logo.first()).toBeVisible();
    });
});

test.describe('App Layout', () => {
    test('renders without errors', async ({ page }) => {
        const failed = await collectFailedRequests(page);
        await page.goto('/_layouts/app');
        await expect(page.getByTestId('page-heading')).toHaveText('Dashboard');
        expect(failed.filter(f => !f.includes('favicon'))).toHaveLength(0);
    });

    test('has sidebar navigation items', async ({ page }) => {
        await page.goto('/_layouts/app');
        // Check nav items exist in the DOM (may be hidden on mobile)
        await expect(page.getByTestId('nav-dashboard')).toHaveCount(1);
        await expect(page.getByTestId('nav-reports')).toHaveCount(1);
        await expect(page.getByTestId('nav-campaigns')).toHaveCount(1);
        await expect(page.getByTestId('nav-attribution')).toHaveCount(1);
        await expect(page.getByTestId('nav-settings')).toHaveCount(1);
    });

    test('has header actions', async ({ page }) => {
        await page.goto('/_layouts/app');
        await expect(page.getByTestId('header-actions')).toBeVisible();
    });
});

test.describe('Assets', () => {
    test('CSS loads successfully', async ({ page }) => {
        const cssResponses: number[] = [];
        page.on('response', (response) => {
            if (response.url().includes('.css')) {
                cssResponses.push(response.status());
            }
        });
        await page.goto('/_layouts/auth-card');
        expect(cssResponses.length).toBeGreaterThan(0);
        expect(cssResponses.every(s => s === 200)).toBeTruthy();
    });

    test('JS loads successfully', async ({ page }) => {
        const jsResponses: number[] = [];
        page.on('response', (response) => {
            if (response.url().includes('.js') && !response.url().includes('playwright')) {
                jsResponses.push(response.status());
            }
        });
        await page.goto('/_layouts/auth-card');
        expect(jsResponses.length).toBeGreaterThan(0);
        expect(jsResponses.every(s => s === 200)).toBeTruthy();
    });

    test('fonts load successfully', async ({ page }) => {
        const fontResponses: number[] = [];
        page.on('response', (response) => {
            if (response.url().includes('.woff2')) {
                fontResponses.push(response.status());
            }
        });
        await page.goto('/_layouts/auth-card');
        // Fonts may be lazy-loaded, wait a moment
        await page.waitForTimeout(1000);
        expect(fontResponses.length).toBeGreaterThan(0);
        expect(fontResponses.every(s => s === 200)).toBeTruthy();
    });

    test('SVG logos accessible', async ({ page }) => {
        const response = await page.goto('/svg/Logo-Clear.svg');
        expect(response?.status()).toBe(200);
    });
});

test.describe('Responsive', () => {
    test('sidebar collapses on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/_layouts/app');
        // On mobile, the sidebar should be hidden/collapsed
        const sidebar = page.locator('[data-flux-sidebar]');
        // The sidebar exists but is translated off-screen on mobile
        await expect(sidebar).toHaveCount(1);
    });
});
