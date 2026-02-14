import { expect, test } from '@playwright/test'

test('student can log in with password and reaches overview', async ({ page }) => {
    await page.goto('/homepage/student')

    const schoolCard = page.locator('.school-item', { hasText: 'E2E School' })
    if (await schoolCard.isVisible()) {
        await schoolCard.click()
    }

    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
    await page.locator('[data-testid="student-login-email"] input').fill('e2e.student@example.test')
    await page.getByTestId('student-login-continue-password').click()

    await expect(page.locator('[data-testid="student-login-password"]')).toBeVisible()
    await page.locator('[data-testid="student-login-password"] input').fill('password123')
    await page.getByTestId('student-login-submit-password').click()

    await expect(page).toHaveURL(/\/student\/overview$/)
    await expect(page.getByRole('heading', { name: /meine fächer/i })).toBeVisible({ timeout: 15_000 })
})
