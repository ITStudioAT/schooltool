import { expect, Page, test } from '@playwright/test'

async function loginAsE2EStudent(page: Page): Promise<void> {
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
}

test('logged-in user visiting /homepage/student is redirected to /student/overview', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.goto('/homepage/student')
    await expect(page).toHaveURL(/\/student\/overview$/)
    await expect(page.getByRole('heading', { name: /meine fächer/i })).toBeVisible()
})

test('student can logout from overview and returns to student login', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.locator('.hero-logout-row').getByRole('button', { name: 'Abmelden' }).click()
    await expect(page).toHaveURL(/\/student$/)
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})
