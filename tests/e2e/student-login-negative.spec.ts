import { expect, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

test('student sees an error dialog on wrong password and can return to email step', async ({ page }) => {
    await page.goto('/homepage/student')
    await hideObstructiveUi(page)

    const schoolCard = page.locator('.school-item', { hasText: 'E2E School' })
    if (await schoolCard.isVisible()) {
        await schoolCard.click()
    }

    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
    await page.locator('[data-testid="student-login-email"] input').fill('e2e.student@example.test')
    await page.getByTestId('student-login-continue-password').click()

    await expect(page.locator('[data-testid="student-login-password"]')).toBeVisible()
    await page.locator('[data-testid="student-login-password"] input').fill('wrong-password')
    await page.getByTestId('student-login-submit-password').click()

    await expect(page.getByText('Anmeldefehler')).toBeVisible()
    await expect(page.getByText('Das eingegebene Passwort ist ungültig. Bitte versuche es erneut.')).toBeVisible()

    await page.getByRole('button', { name: 'OK', exact: true }).click()
    await expect(page.locator('[data-testid="student-login-email"]')).toBeVisible()
})
