import { expect, test } from '@playwright/test'
import { hideObstructiveUi } from './helpers/ui'

test('register flow with existing user email proceeds to login-token step', async ({ page }) => {
    await page.goto('/homepage/register?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('register-step-email')).toBeVisible()
    await page.locator('[data-testid="register-email"] input').fill('e2e.register@example.test')
    await page.getByTestId('register-email-continue').click()

    await expect(page.getByTestId('register-step-login-token')).toBeVisible()
    await expect(page.getByText('Wir haben Ihnen einen Anmeldecode per E-Mail gesendet.')).toBeVisible()
})

test('register flow rejects invalid email format and stays on email step', async ({ page }) => {
    await page.goto('/homepage/register?school=E2E')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('register-step-email')).toBeVisible()
    await page.locator('[data-testid="register-email"] input').fill('invalid-email')
    await page.getByTestId('register-email-continue').click()

    await expect(page.getByTestId('register-step-email')).toBeVisible()
    await expect(page.getByTestId('register-step-login-token')).toHaveCount(0)
    await expect(page.getByTestId('register-step-email-token')).toHaveCount(0)
})
