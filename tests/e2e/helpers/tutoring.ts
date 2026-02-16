import { expect, Page } from '@playwright/test'
import { hideObstructiveUi } from './ui'

export async function loginAsTutoringUser(page: Page, email: string, password = 'password123'): Promise<void> {
    await page.goto('/homepage/tutoring_overview?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('tutoring-overview-start-login')).toBeVisible()
    await page.getByTestId('tutoring-overview-start-login').click()
    await expect(page.getByTestId('tutoring-login-step-email')).toBeVisible()

    await page.locator('[data-testid="tutoring-login-email"] input').fill(email)
    await page.getByTestId('tutoring-login-continue-password').click()
    await expect(page.getByTestId('tutoring-login-step-password')).toBeVisible()

    await page.locator('[data-testid="tutoring-login-password"] input').fill(password)
    await page.getByTestId('tutoring-login-submit-password').click()

    await expect(page.getByTestId('tutoring-login-step-success')).toBeVisible()
    await page.getByTestId('tutoring-login-continue-after-success').click()
    await expect(page.getByTestId('tutoring-overview-go-personal-area')).toBeVisible()
    await page.getByTestId('tutoring-overview-go-personal-area').click()
    await expect(page).toHaveURL(/\/homepage\/tutoring$/)
}

export async function logoutTutoringOverview(page: Page): Promise<void> {
    await expect(page.getByTestId('tutoring-overview-logout')).toBeVisible()
    await page.getByTestId('tutoring-overview-logout').click()
    await expect(page).toHaveURL(/\/homepage\/tutoring_overview/)
}
