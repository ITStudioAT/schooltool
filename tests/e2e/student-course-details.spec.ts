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

test('student course details shows free reason and no placeholder dashes', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.locator('.course-card', { hasText: 'E2E Mathematik' }).click()
    await expect(page).toHaveURL(/\/student\/course\/\d+$/)

    await page.getByRole('tab', { name: 'Termine' }).click()
    await expect(page.getByText('E2E Lehrerfortbildung')).toBeVisible()
    await expect(page.locator('text=---')).toHaveCount(0)
});

test('leistungen marks open and finished entries with correct row state', async ({ page }) => {
    await loginAsE2EStudent(page)

    await page.locator('.course-card', { hasText: 'E2E Mathematik' }).click()
    await expect(page).toHaveURL(/\/student\/course\/\d+$/)

    await page.getByRole('tab', { name: 'Leistungen' }).click()
    await page.locator('.entries-semester-filter').getByRole('button', { name: '1+2' }).click()

    const openRow = page.locator('.entry-row', { has: page.locator('.entry-grade', { hasText: 'offen' }) }).first()
    await expect(openRow).toBeVisible()
    await expect(openRow).toHaveClass(/entry-row--open/)

    const finishedRow = page.locator('.entry-row', { has: page.locator('.entry-grade', { hasText: '2' }) }).first()
    await expect(finishedRow).toBeVisible()
    await expect(finishedRow).not.toHaveClass(/entry-row--open/)
});
