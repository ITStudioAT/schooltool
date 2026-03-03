import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('admin-login-email')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.getByTestId('admin-login-password')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()
    await expect(page).toHaveURL(/\/admin\/?$/)
}

test('admin teaching page supports overview navigation without js runtime errors', async ({ page }) => {
    const runtimeErrors: string[] = []

    page.on('pageerror', (error) => {
        runtimeErrors.push(`pageerror: ${error.message}`)
    })

    page.on('console', (message) => {
        if (message.type() !== 'error') {
            return
        }

        const text = message.text()
        if (text.includes('Failed to load resource')) {
            return
        }

        runtimeErrors.push(`console: ${text}`)
    })

    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    await expect(page).toHaveURL(/\/admin\/teaching/)
    await expect(page.getByText('Lehrbereich und Kurssteuerung')).toBeVisible()
    await expect(page.getByRole('button', { name: /Übersicht/ })).toBeVisible()
    await expect(page.getByRole('button', { name: /Einstellungen/ })).toBeVisible()
    await expect(page.getByRole('button', { name: /Suche/ })).toBeVisible()

    await page.getByRole('button', { name: /Suche/ }).click()
    await expect(page.locator('.its-grid-box__title-text', { hasText: 'Personen & Klassen' }).first()).toBeVisible()
    await expect(page.locator('.its-grid-box__title-text', { hasText: 'Trefferliste' }).first()).toBeVisible()

    await page.getByRole('button', { name: /Übersicht/ }).click()
    await expect(page.locator('.teaching-overview-panel-switcher')).toBeVisible()
    await expect(page.getByRole('button', { name: /Meine Fächer/ })).toBeVisible()

    expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([])
})
