import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: '.',
    testMatch: 'sepp-responsive.browser.ts',
    timeout: 30_000,
    expect: { timeout: 15_000 },
    workers: 1,
    retries: 0,
    use: {
        channel: 'chrome',
        baseURL: 'http://127.0.0.1:5183',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'node tests/e2e/sepp-responsive.server.mjs',
        cwd: '../..',
        url: 'http://127.0.0.1:5183',
        reuseExistingServer: false,
    },
})
