import { expect, Page, test } from '@playwright/test'
import { fetchUserToken } from './helpers/tokens'
import { hideObstructiveUi } from './helpers/ui'

async function fillOtpInput(page: Page, testId: string, token: string): Promise<void> {
    const digits = token.trim().split('')
    const inputs = page.locator(`[data-testid="${testId}"] input`)
    await expect(inputs.first()).toBeVisible()
    const count = await inputs.count()

    if (count === 1) {
        await inputs.first().fill(token.trim())
        return
    }

    for (let i = 0; i < digits.length; i++) {
        await inputs.nth(i).fill(digits[i])
    }
}

test('admin with 2FA reaches token step and can complete login', async ({ page }) => {
    const email = 'e2e.admin2fa@example.test'

    await page.goto('/admin/login')
    await hideObstructiveUi(page)

    await expect(page.getByTestId('admin-login-email')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill(email)
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.getByTestId('admin-login-password')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()

    await expect(page.getByTestId('admin-login-token')).toBeVisible({ timeout: 15000 })

    const token = await fetchUserToken(email, 'token_2fa')
    await fillOtpInput(page, 'admin-login-token', token)
    await page.getByTestId('admin-login-submit-token').click()

    await expect(page).toHaveURL(/\/admin\/?$/)
})
