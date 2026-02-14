import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: {
        timeout: 5_000,
    },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    use: {
        baseURL: 'http://127.0.0.1:8001',
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: 'http://127.0.0.1:8001',
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            ...process.env,
            APP_ENV: 'e2e',
        },
    },
})
