import { expect, Page, test } from '@playwright/test'
import { loginAsStudentWithPassword } from './helpers/student'

async function loginAsE2EStudent(page: Page): Promise<void> {
    await loginAsStudentWithPassword(page, 'e2e.student@example.test', 'password123')
}

test('logged-in user visiting /homepage/student is redirected to /student/overview', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.goto('/homepage/student')
    await expect(page).toHaveURL(/\/student\/overview$/)
    await expect(page.getByRole('heading', { name: /meine fächer/i })).toBeVisible()
})

test('student can logout from overview and returns to student login', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.getByTestId('student-overview-logout').click()
    await expect(page).toHaveURL(/\/student$/)
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})
