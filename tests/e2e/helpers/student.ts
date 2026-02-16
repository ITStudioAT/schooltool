import { expect, Page } from '@playwright/test'
import { hideObstructiveUi } from './ui'

export async function loginAsStudentWithPassword(
    page: Page,
    email = 'e2e.student@example.test',
    password = 'password123',
): Promise<void> {
    await page.goto('/homepage/student')
    await hideObstructiveUi(page)

    const schoolCard = page.locator('.school-item', { hasText: 'E2E School' })
    if (await schoolCard.isVisible()) {
        await schoolCard.click()
    }

    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
    await page.locator('[data-testid="student-login-email"] input').fill(email)
    await page.getByTestId('student-login-continue-password').click()

    await expect(page.locator('[data-testid="student-login-password"]')).toBeVisible()
    await page.locator('[data-testid="student-login-password"] input').fill(password)
    await page.getByTestId('student-login-submit-password').click()
    await expect(page).toHaveURL(/\/student\/overview$/)
}
