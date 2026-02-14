import { expect, test } from '@playwright/test'

test('admin can log in with password and reaches admin dashboard', async ({ page }) => {
    await page.goto('/admin/login')

    await expect(page.locator('[data-testid="admin-login-email"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-email"] input').fill('e2e.admin@example.test')
    await page.getByTestId('admin-login-continue-password').click()

    await expect(page.locator('[data-testid="admin-login-password"]')).toBeVisible()
    await page.locator('[data-testid="admin-login-password"] input').fill('password123')
    await page.getByTestId('admin-login-submit-password').click()

    await expect(page).toHaveURL(/\/admin\/?$/)
})
