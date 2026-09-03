import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()
    await expect(page).toHaveURL(/\/admin\/?$/)
}

async function expectNoHorizontalPageOverflow(page: Page): Promise<void> {
    const dimensions = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }))

    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1)
}

test('all teaching menu selections stay reachable on a handset', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await loginAsAdmin(page)

    await page.goto('/admin/teaching')
    await hideObstructiveUi(page)

    const topLevelSections = ['search', 'schoolyear', 'settings', 'curricula', 'datensicherung', 'overview']

    for (const section of topLevelSections) {
        const menuButton = page.getByTestId(`teaching-nav-${section}`)

        await expect(menuButton).toBeVisible()
        await expect(menuButton).toHaveCSS('min-height', '44px')
        await menuButton.click()
        await expectNoHorizontalPageOverflow(page)
    }

    await page.getByRole('button', { name: 'E2E Mathematik', exact: true }).click()

    const coursePanels = [
        'table',
        'attendance',
        'dates',
        'students',
        'infos',
        'works',
        'performances',
        'performances_plus',
        'print',
    ]

    for (const panel of coursePanels) {
        const panelButton = page.getByTestId(`teaching-overview-panel-${panel}`)

        await expect(panelButton).toBeVisible()
        await expect(panelButton).toHaveCSS('min-height', '48px')
        await panelButton.click()
        await expectNoHorizontalPageOverflow(page)
    }
})
