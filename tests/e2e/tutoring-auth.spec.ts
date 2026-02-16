import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function loginAsTutoringUser(page: Page): Promise<void> {
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()
    await page.getByTestId('tutoring-overview-start-login').click()
    await expect(page.getByTestId('tutoring-login-step-email')).toBeVisible()

    await page.locator('[data-testid="tutoring-login-email"] input').fill('e2e.tutoring@example.test')
    await page.getByTestId('tutoring-login-continue-password').click()
    await expect(page.getByTestId('tutoring-login-step-password')).toBeVisible()

    await page.locator('[data-testid="tutoring-login-password"] input').fill('password123')
    await page.getByTestId('tutoring-login-submit-password').click()

    await expect(page.getByTestId('tutoring-login-step-success')).toBeVisible()
    await page.getByTestId('tutoring-login-continue-after-success').click()
    await expect(page.getByTestId('tutoring-overview-go-personal-area')).toBeVisible()
    await page.getByTestId('tutoring-overview-go-personal-area').click()
    await expect(page).toHaveURL(/\/homepage\/tutoring$/)
}

test('tutoring logout invalidates session and protects personal area', async ({ page }) => {
    await loginAsTutoringUser(page)
    await hideObstructiveUi(page)

    const logoutCard = page.locator('.menu-card.card-logout').first()
    await expect(logoutCard).toBeVisible()
    await logoutCard.click()

    await expect(page).toHaveURL(/\/homepage\/tutoring_overview/)
    await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()

    await page.goto('/homepage/tutoring')
    await page.waitForURL(/\/(homepage\/tutoring_overview|admin\/login)/)

    const protectedPath = new URL(page.url()).pathname
    expect(['/homepage/tutoring_overview', '/homepage/tutoring_overview/', '/admin/login']).toContain(protectedPath)

    if (protectedPath.startsWith('/homepage/tutoring_overview')) {
        await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()
    } else {
        await expect(page.getByTestId('admin-login-email')).toBeVisible()
    }
})
