import { expect, Page, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

async function openTutoringLogin(page: Page): Promise<void> {
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()
    await page.getByTestId('tutoring-overview-start-login').click()
    await expect(page.getByTestId('tutoring-login-step-email')).toBeVisible()
}

test('tutoring user can log in with password and open personal area', async ({ page }) => {
    await openTutoringLogin(page)

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
})

test('tutoring login with wrong password shows retry state', async ({ page }) => {
    await openTutoringLogin(page)

    await page.locator('[data-testid="tutoring-login-email"] input').fill('e2e.tutoring@example.test')
    await page.getByTestId('tutoring-login-continue-password').click()

    await expect(page.getByTestId('tutoring-login-step-password')).toBeVisible()
    await page.locator('[data-testid="tutoring-login-password"] input').fill('wrong-password')
    await page.getByTestId('tutoring-login-submit-password').click()

    await expect(page.getByTestId('tutoring-login-password-retry-alert')).toBeVisible()
    await expect(page.getByText('Das Kennwort war falsch.')).toBeVisible()
})
