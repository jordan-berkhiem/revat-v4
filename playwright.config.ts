import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 30000,
    retries: 0,
    use: {
        baseURL: 'http://127.0.0.1:8001',
        headless: true,
    },
    projects: [
        {
            name: 'layouts',
            testDir: './tests/Browser',
            use: { browserName: 'chromium' },
        },
        {
            name: 'ui-integration',
            testDir: './tests/E2E/UI',
            use: {
                browserName: 'chromium',
                storageState: './tests/E2E/UI/.auth/user.json',
            },
            dependencies: ['auth-setup'],
        },
        {
            name: 'auth-setup',
            testDir: './tests/E2E/UI',
            testMatch: /global-setup\.ts/,
            use: { browserName: 'chromium' },
        },
    ],
    webServer: {
        command: 'php artisan serve --port=8001',
        port: 8001,
        reuseExistingServer: true,
        timeout: 10000,
    },
});
